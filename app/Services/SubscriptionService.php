<?php

namespace App\Services;

use App\Models\PaymentConfiguration;
use App\Models\Students;
use App\Models\Subscription;
use App\Models\SubscriptionBill;
use App\Models\User;
use App\Repositories\AddonSubscription\AddonSubscriptionInterface;
use App\Repositories\Package\PackageInterface;
use App\Repositories\PaymentTransaction\PaymentTransactionInterface;
use App\Repositories\Staff\StaffInterface;
use App\Repositories\Subscription\SubscriptionInterface;
use App\Repositories\SubscriptionBill\SubscriptionBillInterface;
use App\Repositories\SubscriptionFeature\SubscriptionFeatureInterface;
use App\Repositories\User\UserInterface;
use Auth;
use Carbon\Carbon;
use DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Stripe\Stripe;
use Stripe\Checkout\Session as StripeSession;
use Unicodeveloper\Paystack\Facades\Paystack;
use Illuminate\Support\Facades\Log;
use App\Services\ResponseService;
use App\Models\AddonSubscription;
use App\Models\Package;
use App\Models\PaymentTransaction;
use App\Models\SubscriptionFeature;
use App\Payments\DTO\PaymentRequest;
use App\Payments\DTO\PaymentResult;
use App\Payments\Exceptions\GatewayUnavailableException;
use App\Payments\Exceptions\PaymentException;

class SubscriptionService
{
    private UserInterface $user;
    private SubscriptionInterface $subscription;
    private PackageInterface $package;
    private SubscriptionFeatureInterface $subscriptionFeature;
    private CachingService $cache;
    private AddonSubscriptionInterface $addonSubscription;
    private StaffInterface $staff;
    private SubscriptionBillInterface $subscriptionBill;
    private PaymentTransactionInterface $paymentTransaction;

    public function __construct(UserInterface $user, SubscriptionInterface $subscription, PackageInterface $package, SubscriptionFeatureInterface $subscriptionFeature, CachingService $cache, AddonSubscriptionInterface $addonSubscription, StaffInterface $staff, SubscriptionBillInterface $subscriptionBill, PaymentTransactionInterface $paymentTransaction)
    {
        $this->user = $user;
        $this->subscription = $subscription;
        $this->package = $package;
        $this->subscriptionFeature = $subscriptionFeature;
        $this->cache = $cache;
        $this->addonSubscription = $addonSubscription;
        $this->staff = $staff;
        $this->subscriptionBill = $subscriptionBill;
        $this->paymentTransaction = $paymentTransaction;
    }


    /**
     * @param $package_id
     * @param $school_id
     * @param $isCurrentPlan
     * @param string $status  Lifecycle status for the row. Defaults to "pending":
     *                        a plan only becomes "active" when the gateway webhook
     *                        confirms a successful payment. Non-payment flows
     *                        (postpaid, admin assign, cron renewal) pass "active".
     * @return Model|null
     */
    public function createSubscription($package_id, $school_id = null, $subscription_id = null, $isCurrentPlan = null, $status = 'pending')
    {
        // package_id => Create that package
        // school_id => if super admin can assign package, then school id is compulsory
        // subscription_id => if school admin already set upcoming plan update only that plan
        // isCurrentPlan => school admin can set current plan & upcoming plan also

        $settings = $this->cache->getSystemSettings();
        $package = $this->package->builder()->with('package_feature')->where('id', $package_id)->first();
        $end_date = '';
        if (!$school_id) {
            $school_id = Auth::user()->school_id;
        }
        if ($package->is_trial) {
            $end_date = Carbon::now()->addDays(($settings['trial_days']))->format('Y-m-d');
        } else {
            $end_date = Carbon::now()->addDays(($package->days - 1))->format('Y-m-d');
        }
        $start_date = Carbon::now()->format('Y-m-d');

        // $status comes from the caller and defaults to "pending". The webhook /
        // payment-success path passes "active"; an upcoming plan is forced to
        // "pending" in the branch below.

        // If not current subscription plan
        if (!$isCurrentPlan) {
            // Attempt to get the current active subscription
            $current_subscription = $this->active_subscription($school_id);

            // Check if a current subscription was found
            if ($current_subscription) {
                $start_date = Carbon::parse($current_subscription->end_date)->addDays()->format('Y-m-d');
                $end_date = Carbon::parse($start_date)->addDays(($package->days - 1))->format('Y-m-d');
                $status = 'pending';
            } else {
                // Handle the case where there is no active subscription
                Log::warning("No active subscription found for school_id: {$school_id}");
                // You might want to set default dates or handle this case differently
                $start_date = Carbon::now()->format('Y-m-d');
                $end_date = Carbon::now()->addDays($package->days - 1)->format('Y-m-d');
            }
        }

        $subscription_data = [
            'package_id' => $package->id,
            'name' => $package->name,
            'student_charge' => $package->student_charge,
            'staff_charge' => $package->staff_charge,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'package_type' => $package->type,
            'no_of_students' => $package->no_of_students,
            'no_of_staffs' => $package->no_of_staffs,
            'billing_cycle' => $package->days,
            'school_id' => $school_id,
            'charges' => $package->charges,
            'status' => $status
        ];

        // Check subscription update or create
        // If school has already set upcoming plan
        if ($subscription_id) {
            $subscription = $this->subscription->update($subscription_id, $subscription_data);
        } else {
            $subscription = $this->subscription->create($subscription_data);
        }


        // If current subscription plan then set package features
        if ($isCurrentPlan) {
            $subscription_features = array();
            foreach ($package->package_feature as $key => $feature) {
                $subscription_features[] = [
                    'subscription_id' => $subscription->id,
                    'feature_id' => $feature->feature_id
                ];
            }
            $this->subscriptionFeature->upsert($subscription_features, ['subscription_id', 'feature_id'], ['subscription_id', 'feature_id']);
            $this->cache->removeSchoolCache(config('constants.CACHE.SCHOOL.FEATURES'), $subscription->school_id);


            // If prepaid plan generate bill first
            if ($package->type == 0) {
                $subscription_bill[] = [
                    'subscription_id' => $subscription->id,
                    'amount' => $package->charges,
                    'total_student' => $package->no_of_students,
                    'total_staff' => $package->no_of_staffs,
                    'due_date' => Carbon::now(),
                    'school_id' => $subscription->school_id
                ];
                if (Auth::user() && !Auth::user()->hasRole('School Admin')) {
                    $billData = [
                        'user_id' => $subscription->school->admin_id,
                        'amount' => $package->charges,
                        'payment_gateway' => 'Cash',
                        'school_id' => $subscription->school_id,
                        'payment_status' => 'succeed'
                    ];

                    $paymentTransaction = $this->paymentTransaction->create($billData);
                    $subscription_bill[0]['payment_transaction_id'] = $paymentTransaction->id;
                }
                // $subscription_bill = $this->subscriptionBill->create($subscription_bill);
                SubscriptionBill::upsert($subscription_bill, ['subscription_id', 'school_id'], ['amount', 'total_student', 'total_staff', 'due_date']);
                // return $subscription_bill = $this->subscriptionBill->upsert($subscription_bill,['subscription_id','school_id'],['amount','total_student','total_staff','due_date']);
            }
        }

        return $subscription;
    }

    /**
     * @param $generateBill
     * @return Model|null
     */
    public function createSubscriptionBill($subscription, $generateBill = null)
    {
        // GenerateBill [ null => Generate immediate bill, 1 => Generate regular bill ]

        // Set school database connection for getting user counts
        Config::set('database.connections.school.database', $subscription->school->database_name);
        DB::purge('school');
        DB::connection('school')->reconnect();
        DB::setDefaultConnection('school');

        // $students = User::on('school')->withTrashed()->where(function ($q) use ($subscription) {
        //     $q->whereBetween('deleted_at', [$subscription->start_date, $subscription->end_date]);
        // })->orWhereNull('deleted_at')->role('Student')->where('school_id', $subscription->school_id)->count();

        $students = Students::on('school')->whereHas('user', function ($q) use ($subscription) {
            $q->withTrashed()->where(function ($q) use ($subscription) {
                $q->whereBetween('deleted_at', [$subscription->start_date, $subscription->end_date]);
            })->orWhereNull('deleted_at')->where('school_id', $subscription->school_id);
        })->has('user')->count();

        $staffs = $this->staff->builder()->whereHas('user', function ($q) use ($subscription) {
            $q->where(function ($q) use ($subscription) {
                $q->withTrashed()->whereBetween('deleted_at', [$subscription->start_date, $subscription->end_date])
                    ->orWhereNull('deleted_at');
            })->where('school_id', $subscription->school_id);
        })->count();

        DB::setDefaultConnection('mysql');

        $today_date = Carbon::now()->format('Y-m-d');
        $start_date = Carbon::parse($subscription->start_date);
        if ($generateBill) {
            $usage_days = $start_date->diffInDays($subscription->end_date) + 1;
        } else {
            $usage_days = $start_date->diffInDays($today_date) + 1;
        }
        $bill_cycle_days = $subscription->billing_cycle;


        // Get addon total
        $addons = $this->addonSubscription->builder()->where('subscription_id', $subscription->id)->sum('price');

        $student_charges = (($usage_days * $subscription->student_charge) / $bill_cycle_days) * $students;
        $staff_charges = (($usage_days * $subscription->staff_charge) / $bill_cycle_days) * $staffs;

        $systemSettings = $this->cache->getSystemSettings();

        $subscription_bill = [
            'subscription_id' => $subscription->id,
            'amount' => number_format(($student_charges + $staff_charges + $addons), 2, '.', ''),
            'total_student' => $students,
            'total_staff' => $staffs,
            'due_date' => Carbon::now()->addDays($systemSettings['additional_billing_days'])->format('Y-m-d'),
            'school_id' => $subscription->school_id
        ];
        // Create bill for active plan
        return $subscription_bill = $this->subscriptionBill->create($subscription_bill);
    }

    // Check subscription pending bills
    public function subscriptionPendingBill()
    {
        $subscriptionBill = $this->subscriptionBill->builder()->whereHas('transaction', function ($q) {
            $q->whereNot('payment_status', "succeed");
        })->orDoesntHave('transaction')->where('school_id', Auth::user()->school_id)->whereNot('amount', 0)->first();
        return $subscriptionBill;
    }


    /**
     * ====================================================================
     *  PAYMENT FULFILLMENT — centralized subscription business logic
     * ====================================================================
     *
     * Single, idempotent-at-the-caller entry point used by every gateway once a
     * payment is confirmed (the webhook controller for Razorpay/Paystack/
     * Flutterwave, and the success-redirect for Stripe). It replaces the five
     * historical copies of this logic:
     *   1. SubscriptionController::payment_success()      (Stripe redirect)
     *   2. SubscriptionWebhookController::razorpay()      (inline)
     *   3-5. handlePackagePayment / processUpcomingPrepaidPlan /
     *        processImmediatePackageChange / handleAddonPayment (Paystack+Flutterwave)
     *
     * The metadata bag carried by {@see PaymentResult} drives the routing and is
     * tolerant of the legacy key names so in-flight payments created before the
     * cut-over still fulfill correctly:
     *   type         => "package" | "addon"
     *   package_type => "new" | "upcoming" | "immediate" | "bill"   (plan action)
     *   package_id / subscription_id / subscription_bill_id / school_id / ...
     *
     * The caller is responsible for (a) the idempotency guard — do not call this
     * twice for the same already-succeeded transaction — and (b) wrapping it in a
     * DB transaction so partial fulfillment rolls back completely.
     *
     * @param PaymentResult      $result      Standardized, confirmed payment result.
     * @param PaymentTransaction $transaction The recorded payment transaction.
     * @return Subscription|null              The created/affected subscription, if any.
     */
    public function fulfillFromPayment(PaymentResult $result, PaymentTransaction $transaction): ?Subscription
    {
        DB::setDefaultConnection('mysql');

        $meta = $result->metadata;
        $type = $meta['type'] ?? 'package';
        $schoolId = $meta['school_id'] ?? $transaction->school_id;
        $transactionId = $transaction->id;

        Log::info('[Subscription] Fulfilling payment', [
            'type'           => $type,
            'gateway'        => $result->gateway,
            'transaction_id' => $transactionId,
            'school_id'      => $schoolId,
        ]);

        $subscription = null;

        if ($type === 'addon') {
            $this->fulfillAddon($meta, $transactionId);
        } else {
            $action = $meta['package_type'] ?? 'new'; // new | upcoming | immediate | bill
            $packageId = $meta['package_id'] ?? null;
            $subscriptionId = $meta['subscription_id'] ?? null;
            $billId = $meta['subscription_bill_id'] ?? null;

            switch ($action) {
                case 'upcoming':
                    $subscription = $this->scheduleUpcomingPrepaid($schoolId, $packageId, $subscriptionId, $transactionId);
                    break;

                case 'immediate':
                    $subscription = $this->immediatePlanChange($schoolId, $packageId, $transactionId);
                    break;

                case 'bill':
                    // Paying an existing bill. Legacy Razorpay passes the bill id as subscription_id.
                    $this->linkBillToTransaction($billId ?? $subscriptionId, $transactionId);
                    break;

                case 'new':
                default:
                    $subscription = $this->activatePlan($packageId, $schoolId, $transactionId);
                    if ($billId) {
                        $this->linkBillToTransaction($billId, $transactionId);
                    }
                    break;
            }
        }

        if ($schoolId) {
            $this->cache->removeSchoolCache(config('constants.CACHE.SCHOOL.FEATURES'), $schoolId);
        }

        return $subscription;
    }

    /**
     * Activate a freshly purchased plan as the current subscription and link the
     * (prepaid) bill that {@see createSubscription} generates to the transaction.
     * Postpaid plans create no bill at activation (their bill is usage-based and
     * generated by the cron), so the link is a no-op there.
     */
    public function activatePlan($packageId, $schoolId, $transactionId): Subscription
    {
        // Payment is confirmed at this point (the webhook only calls fulfillment
        // on success), so the plan is created directly as "active".
        $subscription = $this->createSubscription($packageId, $schoolId, null, 1, 'active');

        SubscriptionBill::where('subscription_id', $subscription->id)
            ->update(['payment_transaction_id' => $transactionId]);

        Log::info('[Subscription] Plan activated', ['subscription_id' => $subscription->id]);

        return $subscription;
    }

    /**
     * Schedule a paid upcoming prepaid plan that starts the day after the current
     * plan ends. The plan is stored with status "pending" and becomes active by
     * date when the current plan expires (see {@see active_subscription}).
     */
    public function scheduleUpcomingPrepaid($schoolId, $packageId, $subscriptionId, $transactionId): ?Subscription
    {
        $active_plan = $this->active_subscription($schoolId);
        if (!$active_plan) {
            Log::error("[Subscription] Upcoming plan: no active plan for school {$schoolId}");
            return null;
        }

        $package = $this->package->findById($packageId);
        if (!$package) {
            Log::error("[Subscription] Upcoming plan: package {$packageId} not found");
            return null;
        }

        $start_date = Carbon::parse($active_plan->end_date)->addDays()->format('Y-m-d');
        $end_date = Carbon::parse($start_date)->addDays($package->days - 1)->format('Y-m-d');

        $subscription_data = $this->buildSubscriptionData($package, $schoolId, $start_date, $end_date, 'pending');

        if ($subscriptionId && $active_plan->id == $subscriptionId) {
            // The "upcoming" slot is currently the active plan itself — create a new row.
            $subscription = Subscription::create($subscription_data);
        } elseif ($subscriptionId) {
            $existing = Subscription::find($subscriptionId);
            if ($existing) {
                $existing->update($subscription_data);
                $subscription = $existing->fresh();
            } else {
                $subscription = Subscription::create($subscription_data);
            }
        } else {
            $subscription = Subscription::create($subscription_data);
        }

        $this->syncSubscriptionFeatures($subscription);
        $this->createScheduledBill($subscription, $schoolId, $transactionId);

        Log::info('[Subscription] Upcoming plan scheduled', ['subscription_id' => $subscription->id]);

        return $subscription;
    }

    /**
     * Immediately replace the current plan with a new one: bill the outgoing
     * postpaid usage, expire the current plan + its features/addons, drop any
     * scheduled upcoming plan, then create the new current plan with its bill.
     */
    public function immediatePlanChange($schoolId, $packageId, $transactionId): ?Subscription
    {
        $subscription = $this->active_subscription($schoolId);
        if (!$subscription) {
            Log::error("[Subscription] Immediate change: no active plan for school {$schoolId}");
            return null;
        }

        $package = $this->package->findById($packageId);
        if (!$package) {
            Log::error("[Subscription] Immediate change: package {$packageId} not found");
            return null;
        }

        // Postpaid: bill the partial usage of the outgoing plan before expiring it.
        if ($subscription->package_type == 1) {
            $this->createSubscriptionBill($subscription, null);
        }

        $today = Carbon::now()->format('Y-m-d');

        // Expire the current plan and remove its features.
        Subscription::where('id', $subscription->id)->update(['end_date' => $today, 'status' => 'expired']);
        $this->subscriptionFeature->builder()->where('subscription_id', $subscription->id)->delete();

        // Drop any scheduled (unbilled, future) upcoming plan.
        $this->subscription->builder()->with('package')->doesntHave('subscription_bill')
            ->whereDate('start_date', '>', $today)->delete();

        // Expire + remove the outgoing plan's addons.
        $addons = $this->addonSubscription->builder()->where('subscription_id', $subscription->id)->get();
        $addonIds = [];
        foreach ($addons as $addon) {
            AddonSubscription::where('id', $addon->id)->update(['end_date' => $today]);
            $addonIds[] = $addon->id;
        }
        if (!empty($addonIds)) {
            $this->addonSubscription->builder()->whereIn('id', $addonIds)->delete();
        }

        // Create the new current plan.
        $start_date = $today;
        $end_date = Carbon::now()->addDays($package->days - 1)->format('Y-m-d');
        $new_subscription = Subscription::create(
            $this->buildSubscriptionData($package, $schoolId, $start_date, $end_date, 'active')
        );

        $this->syncSubscriptionFeatures($new_subscription);
        $this->createScheduledBill($new_subscription, $schoolId, $transactionId);

        Log::info('[Subscription] Immediate plan change completed', [
            'old_subscription_id' => $subscription->id,
            'new_subscription_id' => $new_subscription->id,
        ]);

        return $new_subscription;
    }

    /**
     * Register a purchased addon against the subscription.
     */
    public function fulfillAddon(array $meta, $transactionId): AddonSubscription
    {
        $endDate = !empty($meta['end_date'])
            ? $meta['end_date']
            : Carbon::now()->addDays(30)->format('Y-m-d');

        $addon = AddonSubscription::create([
            'subscription_id'        => $meta['subscription_id'] ?? null,
            'school_id'              => $meta['school_id'] ?? null,
            'feature_id'             => $meta['feature_id'] ?? null,
            'price'                  => $meta['amount'] ?? $meta['price'] ?? 0,
            'start_date'             => Carbon::now(),
            'end_date'               => $endDate,
            'status'                 => 1,
            'payment_transaction_id' => $transactionId,
        ]);

        Log::info('[Subscription] Addon registered', ['addon_subscription_id' => $addon->id]);

        return $addon;
    }

    /**
     * Link a subscription bill to its paying transaction.
     */
    public function linkBillToTransaction($billId, $transactionId): void
    {
        if (!$billId) {
            return;
        }
        $bill = SubscriptionBill::find($billId);
        if ($bill) {
            $bill->update(['payment_transaction_id' => $transactionId]);
            Log::info('[Subscription] Bill linked to transaction', ['bill_id' => $billId, 'transaction_id' => $transactionId]);
        } else {
            Log::warning("[Subscription] Bill {$billId} not found for linking");
        }
    }

    /**
     * Build the canonical subscriptions row payload for a package.
     */
    private function buildSubscriptionData(Package $package, $schoolId, string $startDate, string $endDate, string $status): array
    {
        return [
            'package_id'     => $package->id,
            'name'           => $package->name,
            'student_charge' => $package->student_charge,
            'staff_charge'   => $package->staff_charge,
            'start_date'     => $startDate,
            'end_date'       => $endDate,
            'package_type'   => $package->type,
            'no_of_students' => $package->no_of_students,
            'no_of_staffs'   => $package->no_of_staffs,
            'billing_cycle'  => $package->days,
            'school_id'      => $schoolId,
            'charges'        => $package->charges,
            'status'         => $status,
        ];
    }

    /**
     * Upsert a subscription's feature rows from its package definition.
     */
    private function syncSubscriptionFeatures(Subscription $subscription): void
    {
        $subscription->loadMissing('package.package_feature');

        $features = [];
        foreach (optional($subscription->package)->package_feature ?? [] as $feature) {
            $features[] = [
                'subscription_id' => $subscription->id,
                'feature_id'      => $feature->feature_id,
            ];
        }

        if (!empty($features)) {
            SubscriptionFeature::upsert($features, ['subscription_id', 'feature_id'], ['subscription_id', 'feature_id']);
            Log::info('[Subscription] Features synced', ['subscription_id' => $subscription->id, 'count' => count($features)]);
        }
    }

    /**
     * Create the flat scheduled bill (amount = package charges) used by the
     * upcoming/immediate flows, linked to the paying transaction.
     */
    private function createScheduledBill(Subscription $subscription, $schoolId, $transactionId): SubscriptionBill
    {
        $settings = $this->cache->getSystemSettings();

        return SubscriptionBill::create([
            'subscription_id'        => $subscription->id,
            'amount'                 => $subscription->charges,
            'total_student'          => 0,
            'total_staff'            => 0,
            'due_date'               => Carbon::now()->addDays($settings['additional_billing_days'] ?? 7)->format('Y-m-d'),
            'school_id'              => $schoolId,
            'payment_transaction_id' => $transactionId,
        ]);
    }

    // Stripe payment gateway
    public function stripe_payment($subscriptionBill_id = null, $package_id = null, $type = null, $subscription_id = null, $isCurrentPlan = null)
    {
        try {
            Log::info('Starting Stripe payment process');

            $name = 'Subscription';
            $amount = 0;
            if ($subscriptionBill_id) {
                $subscriptionBill = $this->subscriptionBill->findById($subscriptionBill_id);
                $name = $subscriptionBill->subscription->name;
                $amount = $subscriptionBill->amount;
                $package_id = -1;
            }

            if ($package_id && $package_id != -1) {
                $package = $this->package->findById($package_id);
                $name = $package->name;
                $amount = $package->charges;
                $subscriptionBill_id = -1;
            }

            if ($type == null) {
                $type = -1;
            }
            if (!$subscription_id) {
                $subscription_id = -1;
            }
            if (!$isCurrentPlan) {
                $isCurrentPlan = -1;
            }

            // Build the gateway-agnostic request. The success URL keeps its
            // {CHECKOUT_SESSION_ID}/<ids> encoding because Stripe activates the
            // plan in the success redirect (payment_success), not the webhook.
            $request = new PaymentRequest(
                amount: (float) $amount,
                currency: '', // StripeGateway uses the configured currency
                productName: $name,
                schoolId: Auth::user()->school_id ?? null,
                userId: Auth::user()->id ?? null,
                metadata: [
                    'subscription_bill_id' => $subscriptionBill_id,
                    'package_id'           => $package_id,
                    'type'                 => $type,
                    'subscription_id'      => $subscription_id,
                    'is_current_plan'      => $isCurrentPlan,
                    'school_id'            => Auth::user()->school_id ?? null,
                ],
                successUrl: url('subscriptions/payment/success') . '/{CHECKOUT_SESSION_ID}' . '/' . $subscriptionBill_id . '/' . $package_id . '/' . $type . '/' . $subscription_id . '/' . $isCurrentPlan,
                cancelUrl: url('subscriptions/payment/cancel') . '/' . $subscriptionBill_id,
            );

            $result = app(PaymentService::class)->createPayment('Stripe', $request);

            return redirect()->away($result->redirectUrl)->with('success', trans('The stripe payment has been successful'));
        } catch (GatewayUnavailableException $e) {
            Log::error('Stripe unavailable: ' . $e->getMessage());
            return redirect()->back()->with('error', trans('Stripe payment is not available'));
        } catch (PaymentException $e) {
            Log::error('Stripe error: ' . $e->getMessage());
            return redirect()->back()->with('error', trans('Stripe error: ') . $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('General Error in Stripe Payment: ' . $e->getMessage());
            return redirect()->back()->with('error', trans('server_not_responding'));
        }
    }

    // Paystack Payment
    public function paystack_payment($subscriptionBill_id = null, $package_id = null, $type = null, $subscription_id = null, $isCurrentPlan = null)
    {
        try {
            $name = '';
            $amount = 0;

            if ($subscriptionBill_id) {
                $subscriptionBill = $this->subscriptionBill->findById($subscriptionBill_id);
                $name = $subscriptionBill->subscription->name;
                $amount = $subscriptionBill->amount;
            }

            if ($package_id && $package_id != -1) {
                $package = $this->package->findById($package_id);
                $name = $package->name;
                $amount = $package->charges;
                $subscriptionBill_id = -1;
            }

            if ($type == null) {
                $type = -1;
            }
            if (!$subscription_id) {
                $subscription_id = -1;
            }
            if (!$isCurrentPlan) {
                $isCurrentPlan = -1;
            }

            $request = new PaymentRequest(
                amount: (float) $amount,
                currency: '', // PaystackGateway uses the configured currency
                productName: $name,
                customerEmail: Auth::user()->email,
                schoolId: Auth::user()->school_id ?? null,
                userId: Auth::user()->id,
                metadata: [
                    'package_id'             => $package_id,
                    'type'                   => 'package',
                    'name'                   => 'package',
                    'subscription_id'        => $subscription_id,
                    'is_current_plan'        => $isCurrentPlan,
                    'school_id'              => Auth::user()->school_id ?? null,
                    'user_id'                => Auth::user()->id,
                    'payment_transaction_id' => null,
                ],
                successUrl: url('subscriptions/payment/success') . '/{CHECKOUT_SESSION_ID}' . '/' . $subscriptionBill_id . '/' . $package_id . '/' . $type . '/' . $subscription_id . '/' . $isCurrentPlan,
                callbackUrl: url('subscriptions/history'),
            );

            $result = app(PaymentService::class)->createPayment('Paystack', $request);

            return redirect()->away($result->redirectUrl)->with('success', trans('The paystack payment has been successful'));
        } catch (GatewayUnavailableException $e) {
            Log::error('Paystack unavailable: ' . $e->getMessage());
            return redirect()->back()->with('error', trans('Current Paystack payment not available'));
        } catch (PaymentException $e) {
            Log::error('Paystack API Error: ' . $e->getMessage());
            return redirect()->back()->with('error', trans('Paystack error: ') . $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('General Error in Paystack Payment: ' . $e->getMessage());
            return redirect()->back()->with('error', trans('server_not_responding'));
        }
    }

    // Flutterwave Payment
    public function flutterwave_payment($subscriptionBill_id = null, $package_id = null, $type = null, $subscription_id = null, $isCurrentPlan = null)
    {
        try {
            DB::beginTransaction();

            $name = '';
            $amount = 0;

            if ($subscriptionBill_id) {
                $subscriptionBill = $this->subscriptionBill->findById($subscriptionBill_id);
                $name = $subscriptionBill->subscription->name;
                $amount = $subscriptionBill->amount;
                $package_id = -1;
            }

            if ($package_id != -1) {
                $package = $this->package->findById($package_id);
                $name = $package->name;
                $amount = $package->charges;
                $subscriptionBill_id = -1;
            }

            if ($type == null) {
                $type = -1;
            }
            if (!$subscription_id) {
                $subscription_id = -1;
            }
            if (!$isCurrentPlan) {
                $isCurrentPlan = -1;
            }

            // Flutterwave correlates the webhook by tx_ref, so the transaction is
            // pre-created here (status pending) and linked to the bill, exactly as
            // before. Only the API call itself is delegated to the gateway.
            $txRef = 'tx_' . time();
            $paymentTransaction = $this->paymentTransaction->create([
                'user_id'         => Auth::user()->id,
                'amount'          => $amount,
                'payment_gateway' => 'Flutterwave',
                'order_id'        => $txRef,
                'payment_status'  => 'pending',
                'school_id'       => Auth::user()->school_id,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            if (!$paymentTransaction) {
                throw new \Exception(trans('Failed to create payment transaction'));
            }

            if ($subscriptionBill_id != -1) {
                $this->subscriptionBill->update($subscriptionBill_id, [
                    'payment_transaction_id' => $paymentTransaction->id
                ]);
            }

            $request = new PaymentRequest(
                amount: (float) $amount,
                currency: '', // FlutterwaveGateway uses the configured currency
                productName: $name,
                customerEmail: Auth::user()->email,
                customerName: Auth::user()->full_name,
                customerMobile: Auth::user()->mobile,
                schoolId: Auth::user()->school_id ?? null,
                userId: Auth::user()->id,
                reference: $txRef,
                metadata: [
                    'subscription_bill_id'   => $subscriptionBill_id,
                    'package_id'             => $package_id,
                    'type'                   => 'package',
                    'subscription_id'        => $subscription_id,
                    'is_current_plan'        => $isCurrentPlan,
                    'school_id'              => Auth::user()->school_id ?? null,
                    'user_id'                => Auth::user()->id,
                    'payment_transaction_id' => $paymentTransaction->id,
                ],
                successUrl: url('subscriptions/history'),
                cancelUrl: url('subscriptions/payment/cancel') . '/' . $subscriptionBill_id,
            );

            $result = app(PaymentService::class)->createPayment('Flutterwave', $request);

            DB::commit();
            return redirect()->away($result->redirectUrl)->with('success', trans('The flutterwave payment has been successful'));
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Flutterwave Payment Error: ' . $e->getMessage());
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function active_subscription($schoolId)
    {
        $today_date = Carbon::now()->format('Y-m-d');

        // Date-authoritative active-plan detection.
        //
        // The `status` enum column is a denormalized cache written on every
        // create/expire path, but selection MUST stay date-driven: a scheduled
        // "upcoming" plan carries status="pending" and has to auto-activate by
        // date the moment the current plan ends. Gating purely on
        // status="active" (the half-finished migration's behavior) would return
        // null in that window because nothing flips pending->active at expiry.
        // We therefore pick the current-by-date plan, excluding only explicitly
        // cancelled rows, then run the precise per-type requery below.
        $subscription = Subscription::where('school_id', $schoolId)
            ->where('status', 'active')
            ->with('subscription_feature.feature', 'addons.feature', 'package.package_feature.feature')
            ->latest('id')
            ->first();

        if ($subscription) {
            return $subscription;
            // if ($subscription->package_type == 1) {
            //     // Postpaid
            //     $subscription = Subscription::where('school_id', $schoolId)->where('package_type', 1)->whereDate('start_date', '<=', $today_date)->whereDate('end_date', '>=', $today_date)->with('subscription_feature.feature', 'addons.feature', 'package.package_feature.feature')->doesntHave('subscription_bill')->has('subscription_feature')->latest()->first();
            // } else {
            //     // Prepaid
            //     $subscription = Subscription::where('school_id', $schoolId)->where('package_type', 0)->whereDate('start_date', '<=', $today_date)->whereDate('end_date', '>=', $today_date)->with('package.package_feature.feature')->has('subscription_bill')->with('subscription_feature.feature')->with([
            //         'addons' => function ($q) {
            //             $q->has('transaction')->with('feature')->whereHas('transaction', function ($q) {
            //                 $q->where('payment_status', "succeed");
            //             });
            //         }
            //     ])->whereHas('subscription_bill.transaction', function ($q) {
            //         $q->where('payment_status', "succeed");
            //     })->has('subscription_feature')->latest()->first();
            // }
        } else {
            return null;
        }

        return $subscription;
    }

    public function check_user_limit($subscription, $type)
    {
        // type [ Students / Staffs ]
        if ($type == "Students") {
            $students = $this->user->builder()->where('status', 1)->role('Student')->where('school_id', $subscription->school_id)->count();
            if ($students >= $subscription->no_of_students) {
                return false;
            }
            return true;
        } else {
            $staffs = $this->staff->builder()->whereHas('user', function ($q) use ($subscription) {
                $q->where('status', 1)->where('school_id', $subscription->school_id);
            })->count();
            if ($staffs >= $subscription->no_of_staffs) {
                return false;
            }
            return true;
        }
    }

    public function prepaid_addon_payment($addonSubscriptionId)
    {
        try {
            $settings = app(CachingService::class)->getSystemSettings();
            // $subscriptionBill = $this->subscriptionBill->findById($subscriptionBill_id);
            $addonSubscription = $this->addonSubscription->findById($addonSubscriptionId);

            // Access the model directly via data for super admin data, use the interface builder for school-specific data.
            DB::setDefaultConnection('mysql');
            $paymentConfiguration = PaymentConfiguration::where('school_id', null)->where('status', 1)->first();

            if ($paymentConfiguration && !$paymentConfiguration->status) {
                return redirect()->back()->with('error', trans('Current payment method not available'));
            }

            // Stripe Payment
            if ($paymentConfiguration->payment_method == 'Stripe') {
                $stripe_secret_key = $paymentConfiguration->secret_key ?? null;

                if (empty($stripe_secret_key)) {
                    return redirect()->back()->with('error', trans('No API key provided'));
                }

                $amount = $addonSubscription->price;
                $currency = $paymentConfiguration->currency_code;

                // Validate minimum amount
                $checkAmount = $this->checkMinimumAmount(strtoupper($currency), $amount);
                $checkAmount = (float) $checkAmount;
                $checkAmount = round($checkAmount, 2);

                Log::info('Validated amount: ' . $checkAmount * 100);

                Stripe::setApiKey($stripe_secret_key);
                $session = StripeSession::create([
                    'line_items' => [
                        [
                            'price_data' => [
                                'currency' => $currency,
                                'product_data' => [
                                    'name' => $addonSubscription->feature->name,
                                    'images' => [$settings['horizontal_logo'] ?? 'logo.svg'],
                                ],
                                'unit_amount' => $checkAmount * 100,
                            ],
                            'quantity' => 1,
                        ],
                    ],
                    'mode' => 'payment',
                    'success_url' => url('addons/payment/success') . '/{CHECKOUT_SESSION_ID}' . '/' . $addonSubscriptionId,
                    'cancel_url' => url('addons/payment/cancel'),
                ]);

                return redirect()->away($session->url);
            }

            // Paystack Payment
            if ($paymentConfiguration->payment_method == 'Paystack') {

                try {
                    $paystack_secret_key = $paymentConfiguration->secret_key ?? null;
                    $currency = $paymentConfiguration->currency_code;
                    $checkAmount = $this->checkMinimumAmount(strtoupper($currency), $addonSubscription->price);
                    $amount = (float) str_replace(',', '', number_format($checkAmount, 2));


                    if (empty($paystack_secret_key)) {
                        return redirect()->back()->with('error', trans('No API key provided'));
                    }

                    $data = [
                        'amount' => $amount * 100,
                        'email' => Auth::user()->email,
                        'currency' => strtoupper($currency),
                        'metadata' => [
                            'type' => 'addon',
                            'subscription_id' => $addonSubscription->subscription_id,
                            'feature_id' => $addonSubscription->feature_id,
                            'addon_subscription_id' => $addonSubscriptionId,
                            'school_id' => Auth::user()->school_id,
                            'user_id' => Auth::user()->id,
                            'price' => $addonSubscription->price,
                            'end_date' => $addonSubscription->end_date,
                        ],
                        'callback_url' => route('addons.payment.success_callback')
                    ];

                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $paystack_secret_key,
                        'Content-Type' => 'application/json',
                    ])->post('https://api.paystack.co/transaction/initialize', $data);

                    $res = $response->json();

                    \Log::info('Paystack response: ' . json_encode($res));

                    if (!$res['status']) {
                        return redirect()->back()->with('error', $res['message']);
                    }

                    if ($res['status']) {
                        return redirect()->away($res['data']['authorization_url'])->with('reload', true)->with('success', trans('The paystack payment has been successful'));
                    }

                    return redirect('addons/plan')->back()->with('error', trans('Paystack payment failed'));
                } catch (\Throwable $th) {
                    \Log::error('Paystack Payment Error: ' . $th->getMessage());
                    DB::rollBack();
                    return redirect()->back()->with('error', trans('server_not_responding'));
                }
            }

            // Flutterwave Payment
            if ($paymentConfiguration->payment_method == 'Flutterwave') {
                $flutterwave_secret_key = $paymentConfiguration->secret_key ?? null;
                $currency = $paymentConfiguration->currency_code;
                $amount = (float) number_format((float) ceil((float) $addonSubscription->price * 100) / 100, 2);

                if (empty($flutterwave_secret_key)) {
                    return redirect()->back()->with('error', trans('No API key provided'));
                }

                $data = [
                    'tx_ref' => 'tx_' . time(),
                    'amount' => $amount * 100,
                    'currency' => strtoupper($currency),
                    'email' => Auth::user()->email,
                    'customer' => [
                        'email' => Auth::user()->email,
                        'name' => Auth::user()->full_name,
                        'mobile' => Auth::user()->mobile,
                    ],
                    'meta' => [
                        'type' => 'addon',
                        'subscription_id' => $addonSubscription->subscription_id,
                        'feature_id' => $addonSubscription->feature_id,
                        'addon_subscription_id' => $addonSubscriptionId,
                        'school_id' => Auth::user()->school_id,
                        'user_id' => Auth::user()->id,
                    ],
                    'redirect_url' => url('addons/payment/success'),
                ];


                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $flutterwave_secret_key,
                    'Content-Type' => 'application/json',
                ])->post('https://api.flutterwave.com/v3/payments', $data);

                $res = $response->json();
                \Log::info('Flutterwave response: ' . json_encode($res));

                if (!$response->successful()) {
                    throw new \Exception('Flutterwave API request failed: ' . ($res['message'] ?? 'Unknown error'));
                }

                if (!$res['status']) {
                    return redirect()->back()->with('error', $res['message']);
                }

                if ($res['status']) {
                    return redirect()->away($res['data']['link'])->with('reload', true)->with('success', trans('The flutterwave payment has been successful'));
                }
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            return redirect()->back()->with('error', trans('server_not_responding'));
        }
    }


    /**
     * @param string|float $currency
     * @param string|float $amount
     * @return float
     */
    public function checkMinimumAmount($currency, $amount)
    {
        $currencies = array(
            'USD' => 0.50,
            'AED' => 2.00,
            'AUD' => 0.50,
            'BGN' => 1.00,
            'BRL' => 0.50,
            'CAD' => 0.50,
            'CHF' => 0.50,
            'CZK' => 15.00,
            'DKK' => 2.50,
            'EUR' => 0.50,
            'GBP' => 0.30,
            'HKD' => 4.00,
            'HUF' => 175.00,
            'INR' => 0.50,
            'JPY' => 50,
            'MXN' => 10,
            'MYR' => 2.00,
            'NOK' => 3.00,
            'NZD' => 0.50,
            'PLN' => 2.00,
            'RON' => 2.00,
            'SEK' => 3.00,
            'SGD' => 0.50,
            'THB' => 10,
            'ZAR' => 10,
        );
        if ($amount != 0) {
            if (array_key_exists($currency, $currencies)) {
                if ($currencies[$currency] >= $amount) {
                    return $currencies[$currency];
                } else {
                    return $amount;
                }
            } else {
                return $amount;
            }
        }
        return 0;
    }
}
