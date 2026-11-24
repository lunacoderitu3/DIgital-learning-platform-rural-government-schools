<?php

namespace App\Http\Controllers;

use App\Models\Addon;
use App\Models\Feature;
use App\Models\Package;
use App\Models\PaymentConfiguration;
use App\Repositories\Announcement\AnnouncementInterface;
use App\Repositories\ClassSchool\ClassSchoolInterface;
use App\Repositories\ClassSection\ClassSectionInterface;
use App\Repositories\Exam\ExamInterface;
use App\Repositories\Fees\FeesInterface;
use App\Repositories\FeesPaid\FeesPaidInterface;
use App\Repositories\Holiday\HolidayInterface;
use App\Repositories\Leave\LeaveInterface;
use App\Repositories\PaymentTransaction\PaymentTransactionInterface;
use App\Repositories\School\SchoolInterface;
use App\Repositories\SessionYear\SessionYearInterface;
use App\Repositories\Stream\StreamInterface;
use App\Repositories\Student\StudentInterface;
use App\Repositories\Subscription\SubscriptionInterface;
use App\Repositories\Timetable\TimetableInterface;
use App\Repositories\User\UserInterface;
use App\Services\CachingService;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use dacoto\EnvSet\Facades\EnvSet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private UserInterface $user;
    private AnnouncementInterface $announcement;
    private SubscriptionInterface $subscription;
    private SchoolInterface $school;
    private LeaveInterface $leave;
    private HolidayInterface $holiday;
    private CachingService $cache;
    private ClassSchoolInterface $class;
    private TimetableInterface $timetable;
    private SubscriptionService $subscriptionService;
    private ExamInterface $exam;
    private SessionYearInterface $sessionYear;
    private StreamInterface $stream;
    private FeesInterface $fees;
    private FeesPaidInterface $feesPaid;
    private PaymentTransactionInterface $paymentTransaction;
    private ClassSectionInterface $classSection;
    private StudentInterface $student;

    public function __construct(UserInterface $user, AnnouncementInterface $announcement, SubscriptionInterface $subscription, SchoolInterface $school, LeaveInterface $leave, HolidayInterface $holiday, CachingService $cache, ClassSchoolInterface $class, TimetableInterface $timetable, SubscriptionService $subscriptionService, ExamInterface $exam, SessionYearInterface $sessionYear, StreamInterface $stream, FeesInterface $fees, FeesPaidInterface $feesPaid, PaymentTransactionInterface $paymentTransaction, ClassSectionInterface $classSection, StudentInterface $student)
    {
        // $this->middleware('auth');
        $this->user = $user;
        $this->announcement = $announcement;
        $this->subscription = $subscription;
        $this->school = $school;
        $this->leave = $leave;
        $this->holiday = $holiday;
        $this->cache = $cache;
        $this->class = $class;
        $this->timetable = $timetable;
        $this->subscriptionService = $subscriptionService;
        $this->exam = $exam;
        $this->sessionYear = $sessionYear;
        $this->stream = $stream;
        $this->fees = $fees;
        $this->feesPaid = $feesPaid;
        $this->paymentTransaction = $paymentTransaction;
        $this->classSection = $classSection;
        $this->student = $student;
    }

    public function index()
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (($user->hasRole('Super Admin') || $user->hasRole('School Admin')) && $user->two_factor_enabled == 1 && ! $user->two_factor_expires_at && $user->email != 'superadmin@gmail.com' && $user->email != 'demo@school.com') {
            DB::table('users')->where('email', $user->email)->update(['two_factor_secret' => null, 'two_factor_expires_at' => null]);
            Auth::logout();
            return view('auth.login');
        }

        $teacher = $student = $parent = $teachers = $subscription = $prepiad_upcoming_plan = $prepiad_upcoming_plan_type = $check_payment = null;
        $boys = $girls = $license_expire = 0;
        $previous_subscriptions = array();
        $announcement = array();
        $holiday = array();
        $total_students = $male_students = $female_students = $timetables = $classData = $fees_detail = array();
        $paymentConfiguration = '';
        $settings = app(CachingService::class)->getSystemSettings();
        $system_settings = $settings;
        $isSchoolAdmin = $user->hasRole('School Admin');
        $hasSchoolContext = ! empty($user->school_id);

        // School Admin Dashboard
        if ($isSchoolAdmin || $hasSchoolContext) {
            // Counters
            $teacher = $this->user->builder()->role("Teacher")->withTrashed()->count();
            $student = $this->student->builder()->whereHas('user', function ($q) {
                $q->withTrashed();
            })->withTrashed()->count();
            $parent = $this->student->builder()->where('application_status', 1)->groupBy('guardian_id')->get()->count();

            if ($student > 0) {
                $boys_count = $this->student->builder()
                    ->whereHas('user', function ($q) {
                        $q->where('gender', 'male')->withTrashed();
                    })
                    ->withTrashed()
                    ->count();

                $girls_count = $this->student->builder()
                    ->whereHas('user', function ($q) {
                        $q->where('gender', 'female')->withTrashed();
                    })
                    ->withTrashed()
                    ->count();
                $boys = round((($boys_count * 100) / $student), 2);
                $girls = round(($girls_count * 100) / $student, 2);
                $total_students = $student;
            }
            $classes_counter = $this->class->builder()->count();
            $streams = $this->stream->builder()->count();
            // End Counters

            $subscription = $this->subscriptionService->active_subscription($user->school_id);
            $schoolSettings = $this->cache->getSchoolSettings();

            if ($subscription) {
                $license_expire = Carbon::now()->diffInDays(Carbon::parse($subscription->end_date)) + 1;
            }

            $sessionYears = $this->sessionYear->builder()->select('id', 'name', 'default')->get();
            $paymentConfiguration = '';
            // For prepaid upcoming plans, please make the payment before your current subscription expires.
            if ($license_expire <= ($settings['current_plan_expiry_warning_days'] ?? 7) && $subscription) {
                if (isset($schoolSettings['auto_renewal_plan']) && $schoolSettings['auto_renewal_plan']) {
                    $next_plan_start_date = Carbon::parse($subscription->end_date)->addDay()->format('Y-m-d');

                    $prepiad_upcoming_plan = $this->subscription->builder()->with('package')->whereDate('start_date', $next_plan_start_date)->first();
                    $prepiad_upcoming_plan_type = 1;

                    if (! $prepiad_upcoming_plan) {
                        $prepiad_upcoming_plan = $subscription;
                        $prepiad_upcoming_plan_type = 0;
                    }

                    if ($prepiad_upcoming_plan->package->type == 0 && $subscription->id != $prepiad_upcoming_plan->id) {
                        $check_payment = $this->subscription->builder()->where('id', $prepiad_upcoming_plan->id)->whereHas('subscription_bill.transaction', function ($q) {
                            $q->where('payment_status', "succeed");
                        })->first();
                    }

                    DB::setDefaultConnection('mysql');
                    $paymentConfiguration = PaymentConfiguration::where('school_id', null)->where('status', 1)->first();
                    DB::setDefaultConnection('school');
                }
            }

            $previous_subscriptions = $this->subscription->builder()->with('subscription_bill.transaction')
                ->where(function ($query) {
                    $query->whereHas('subscription_bill.transaction', function ($q) {
                        $q->whereIn('payment_status', ['failed', 'pending']);
                    });

                    $query->orWhere(function ($q) {
                        $q->whereHas('subscription_bill', function ($subQ) {
                            $subQ->where(function ($inner) {
                                $inner->whereNull('payment_transaction_id')
                                    ->orWhereDoesntHave('transaction');
                            })
                                ->where('amount', '>', 0)
                                ->whereDate('due_date', '<', now());
                        });
                    });
                })
                ->get();

            $defaultSessionYear = $this->cache->getDefaultSessionYear();

            $holiday = $this->holiday->builder()->whereDate('date', '>=', Carbon::now()->format('Y-m-d'))->whereDate('date', '<=', $defaultSessionYear->original_end_date ?? Carbon::now()->format('Y-m-d'))->orderBy('date', 'ASC')->get();

            $announcement = $this->announcement->builder()->whereHas('announcement_class', function ($q) {
                $q->where('class_subject_id', null);
            })->limit(5)->orderBy('id', 'desc')->get();

            $class_names = $this->class->builder()->with('medium', 'stream', 'shift')->get()->pluck('full_name', 'id');
            $class_section_names = $this->classSection->builder()->with('class', 'medium', 'section', 'class.stream', 'class.shift')->get()->pluck('full_name', 'id');
            $exams = $this->exam->builder()->groupBy('name')->get();

            $student_ids = $this->feesPaid->builder()->whereHas('fees', function ($q) use ($defaultSessionYear) {
                $q->where('session_year_id', $defaultSessionYear->id ?? null);
            })->has('compulsory_fee')->groupBy('student_id')->pluck('student_id');

            $unPaidFees = $this->user->builder()->role('Student')->whereNotIn('id', $student_ids)->count();

            $partialPaidFees = $this->feesPaid->builder()->whereHas('fees', function ($q) use ($defaultSessionYear) {
                $q->where('session_year_id', $defaultSessionYear->id ?? null);
            })->has('compulsory_fee')->where('is_fully_paid', 0)->groupBy('student_id')->get()->count();

            $fullPaidFees = $this->feesPaid->builder()->whereHas('fees', function ($q) use ($defaultSessionYear) {
                $q->where('session_year_id', $defaultSessionYear->id ?? null);
            })->has('compulsory_fee')->where('is_fully_paid', 1)->groupBy('student_id')->orderBy('id')->get()->count();

            if ($partialPaidFees == 0 && $fullPaidFees == 0) {
                $unPaidFees = 0;
            }

            $fees_detail = [
                'unPaidFees' => $unPaidFees,
                'partialPaidFees' => $partialPaidFees,
                'fullPaidFees' => $fullPaidFees,
            ];
        }

        $super_admin = [
            'total_school' => 0,
            'active_school' => 0,
            'deactive_school' => 0,
        ];
        if ($user->hasRole('Super Admin') || empty($user->school_id)) {
            $school = $this->school->builder()->get();
            $total_school = $school->count();
            $active_school = $school->where('status', 1)->count();
            $deactive_school = $school->where('status', 0)->count();

            $new_schools_this_month = $this->school->builder()
                ->whereMonth('created_at', Carbon::now()->month)
                ->whereYear('created_at', Carbon::now()->year)
                ->count();

            $active_plans_count = $this->subscription->builder()
                ->whereDate('start_date', '<=', Carbon::now())
                ->whereDate('end_date', '>=', Carbon::now())
                ->distinct('school_id')
                ->count('school_id');

            $current_month_revenue = $this->paymentTransaction->builder()
                ->where('payment_status', 'succeed')
                ->whereMonth('created_at', Carbon::now()->month)
                ->whereYear('created_at', Carbon::now()->year)
                ->sum('amount');

            $previous_month_revenue = $this->paymentTransaction->builder()
                ->where('payment_status', 'succeed')
                ->whereMonth('created_at', Carbon::now()->subMonth()->month)
                ->whereYear('created_at', Carbon::now()->subMonth()->year)
                ->sum('amount');

            $revenue_growth = 0;
            if ($previous_month_revenue > 0) {
                $revenue_growth = (($current_month_revenue - $previous_month_revenue) / $previous_month_revenue) * 100;
            } elseif ($current_month_revenue > 0) {
                $revenue_growth = 100;
            }

            $active_packages_count = Package::where('is_trial', 0)->where('status', 1)->count();
            $total_addons_count = Addon::where('status', 1)->count();

            $super_admin = [
                'total_school' => $total_school,
                'active_schools' => $active_school,
                'inactive_schools' => $deactive_school,
                'new_schools_this_month' => $new_schools_this_month,
                'active_plans_count' => $active_plans_count,
                'current_month_revenue' => $current_month_revenue,
                'revenue_growth' => $revenue_growth,
                'active_packages_count' => $active_packages_count,
                'total_addons_count' => $total_addons_count
            ];

            $expiring_soon = $this->subscription->builder()->with(['school', 'package'])
                ->whereDate('start_date', '<=', Carbon::now())
                ->whereDate('end_date', '>=', Carbon::now())
                ->orderBy('end_date', 'asc')
                ->whereHas('school', function ($q) {
                    $q->whereNull('deleted_at');
                })
                ->take(5)
                ->get();

            $recent_schools = $this->school->builder()->with('user')
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get();

            $start_year = Carbon::now()->format('Y');
            if ($user->hasRole('Super Admin')) {
                $paymentTransaction = $this->paymentTransaction->builder()->has('subscription_bill')->select(DB::raw('YEAR(MIN(created_at)) as min_year'))->value('min_year');
                if ($paymentTransaction) {
                    $start_year = $paymentTransaction;
                }
            }

            $schools = $this->school->builder()->select('id', 'name', 'admin_id', 'logo')->with('user:id,first_name,last_name')->orderBy('id', 'DESC')->take(5)->get();
            $addons = Addon::select('id', 'name', 'feature_id')->withCount('addon_subscription_count')->with('feature')->get();

            $labels = [];
            $data = [];
            foreach ($addons as $key => $addon) {
                $labels[] = $addon->feature->short_name;
                $data[] = $addon->addon_subscription_count_count;
            }
            $addon_graph = [
                $labels,
                $data
            ];

            $packages = Package::select('id', 'name')->where('is_trial', 0)->withCount('subscription')->get();

            $package_labels = [];
            $package_data = [];
            foreach ($packages as $key => $package) {
                $package_labels[] = $package->name;
                $package_data[] = $package->subscription_count;
            }
            $package_graph = [
                $package_labels,
                $package_data
            ];
        }

        if ($user->hasRole('Teacher')) {
            $date = Carbon::now();
            $fullDayName = $date->format('l');
            $timetables = $this->timetable->builder()
                ->whereHas('subject_teacher', function ($q) use ($user) {
                    $q->where('teacher_id', $user->id);
                })
                ->where('day', $fullDayName)->orderBy('start_time', 'ASC')
                ->with('subject:id,name,type', 'class_section.class', 'class_section.section', 'class_section.medium')->get();
        }

        if (($isSchoolAdmin || $hasSchoolContext) && (! $user->hasRole('Teacher') && ! $user->hasRole('Super Admin'))) {
            return view('school_admin_dashboard', compact('teacher', 'parent', 'student', 'announcement', 'teachers', 'boys', 'girls', 'total_students', 'license_expire', 'subscription', 'previous_subscriptions', 'holiday', 'classData', 'prepiad_upcoming_plan', 'prepiad_upcoming_plan_type', 'check_payment', 'sessionYears', 'classes_counter', 'streams', 'exams', 'fees_detail', 'settings', 'class_names', 'paymentConfiguration', 'system_settings', 'class_section_names', 'super_admin'));
        }
        if ($user->hasRole('Teacher')) {
            return view('teacher_dashboard', compact('teacher', 'parent', 'student', 'announcement', 'teachers', 'boys', 'girls', 'holiday', 'timetables', 'classData', 'sessionYears', 'classes_counter', 'streams', 'class_names', 'total_students', 'exams'));
        }

        if ($user->hasRole('Super Admin') || $user->school_id == null) {
            $server_configuration = [
                'wildcard_domain' => ['title' => trans('subdomain_support'), 'link' => 'rituschool.com', 'description' => trans('Enables each school to have its own subdomain (e.g., schoolname.yourdomain.com). Needed if schools want their own branded web portal.')],
                'notification_settings' => ['title' => trans('push_notification'), 'link' => 'rituschool.com', 'description' => trans('Enables app push notifications using Firebase Cloud Messaging (FCM) for alerts, updates, and reminders.')],
            ];

            return view('super_admin_dashboard', compact('settings', 'super_admin', 'boys', 'girls', 'fees_detail', 'start_year', 'schools', 'addon_graph', 'package_graph', 'paymentConfiguration', 'server_configuration', 'expiring_soon', 'recent_schools'));
        }
    }
}
