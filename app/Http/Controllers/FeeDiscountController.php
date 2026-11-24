<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Fee;
use App\Models\FeeDiscount;
use App\Models\FeeDiscountItem;
use App\Models\FeeDiscountLog;
use App\Models\SessionYear;
use App\Models\ClassSchool;
use App\Models\CompulsoryFee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\OptionalFee;
use App\Models\User;
use App\Models\Student;
use App\Services\CachingService;
use App\Services\ResponseService;
use App\Services\BootstrapTableService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Throwable;
use Carbon\Carbon;

class FeeDiscountController extends Controller
{
    private CachingService $cache;

    public function __construct(CachingService $cache)
    {
        $this->cache = $cache;
    }

    public function index()
    {
        ResponseService::noFeatureThenRedirect('Fees Management');
        ResponseService::noPermissionThenRedirect('fees-paid');

        $classes = ClassSchool::with('medium', 'sections', 'shift')->get();
        $months = sessionYearWiseMonthYear();

        $totalDiscountsApplied = FeeDiscount::count();
        $totalDiscountValue = FeeDiscount::sum('discount_value');
        $activeDiscounts = FeeDiscount::count();
        $pendingApproval = 0; // Assuming 0 for now as there's no approval workflow yet
        $schoolSettings = $this->cache->getSchoolSettings();
        $currencySymbol = $schoolSettings['currency_symbol'] ?? '';

        return view('fees.discount', compact(
            'classes',
            'months',
            'totalDiscountsApplied',
            'totalDiscountValue',
            'activeDiscounts',
            'pendingApproval',
            'currencySymbol'
        ));
    }

    public function getStudentFees(Request $request)
    {
        $studentId = $request->student_id;
        $sessionYearId = $this->cache->getSessionYear()->id;

        $student = User::with('student')->findOrFail($studentId);
        $classId = $student->student->class_section->class_id;

        $fees = Fee::where('session_year_id', $sessionYearId)
            ->where('class_id', $classId)
            ->with('installments', 'fees_class_type')
            ->with(['fees_paid' => function ($q) use ($studentId) {
                $q->where('student_id', $studentId);
            }])
            ->get();

        $existingDiscounts = FeeDiscount::where('student_id', $studentId)
            ->with('items')
            ->get();

        return response()->json([
            'error' => false,
            'data' => $fees,
            'existing_discounts' => $existingDiscounts
        ]);
    }

    public function getFeeDetails(Request $request)
    {
        $feeId = $request->fee_id;
        $studentId = $request->student_id;

        $fee = Fee::with([
            'fees_class_type.fees_type',
            'installments'
        ])->findOrFail($feeId);

        // Fetch paid data to calculate pending amounts
        $existingDiscounts = FeeDiscount::where('student_id', $studentId)
            ->where('fees_id', $feeId)
            ->with('items')
            ->get();

        // Fetch paid installments
        $paidInstallments = CompulsoryFee::where('student_id', $studentId)
            ->whereNull('deleted_at')
            ->where('status', CompulsoryFee::CHEQUE_SUCCESS)
            ->whereHas('installment_fee', function ($q) use ($feeId) {
                $q->where('fees_id', $feeId);
            })
            ->select('installment_id', \DB::raw('SUM(amount + IFNULL(due_charges, 0)) as total_paid'))
            ->groupBy('installment_id')
            ->get();

        // Fetch paid optional fees
        $paidOptionals = OptionalFee::where('student_id', $studentId)
            ->whereNull('deleted_at')
            ->where('status', OptionalFee::CHEQUE_SUCCESS)
            ->whereHas('fees_class_type', function ($q) use ($feeId) {
                $q->where('fees_id', $feeId);
            })
            ->select('fees_class_id', \DB::raw('SUM(amount) as total_paid'))
            ->groupBy('fees_class_id')
            ->get();

        return response()->json([
            'error' => false,
            'data' => $fee,
            'discounts' => $existingDiscounts,
            'paid_installments' => $paidInstallments,
            'paid_optionals' => $paidOptionals
        ]);
    }

    public function list(Request $request)
    {
        ResponseService::noPermissionThenRedirect('fees-paid');
        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $search = request('search');

        $sessionYearId = $this->cache->getSessionYear()->id;

        $sql = FeeDiscount::with([
            'student.student.class_section.class.stream',
            'student.student.class_section.section',
            'student.student.class_section.medium',
            'fee.fees_class_type.fees_type',
            'items.optional_fee_type.fees_type',
            'items.installment'
        ])->whereHas('fee', function ($q) use ($sessionYearId) {
            $q->where('session_year_id', $sessionYearId);
        });

        if (!empty($search)) {
            $sql->whereHas('student', function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%$search%")
                    ->orWhere('last_name', 'LIKE', "%$search%");
            });
        }

        $total = $sql->count();
        if ($offset >= $total && $total > 0) {
            $offset = floor(($total - 1) / $limit) * $limit;
        }

        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();

        $rows = [];
        $no = $offset + 1;
        foreach ($res as $row) {
            $tempRow = $row->toArray();
            $tempRow['no'] = $no++;

            // $operate = '<button type="button" class="btn btn-xs btn-icon btn-action-view view-breakdown-btn mr-2" title="' . trans('View Breakdown') . '"><i class="fa fa-eye"></i></button>';
            $operate = BootstrapTableService::viewButton('#', ['view-breakdown-btn']);
            $operate .= BootstrapTableService::deleteButton(url('fees/discount/rollback/' . $row->id));
            $tempRow['operate'] = $operate;

            // Format amounts and strings
            $tempRow['student_name'] = $row->student->full_name;
            $tempRow['class_name'] = $row->student->student->class_section->full_name ?? '';
            $tempRow['fee_name'] = $row->fee->name;
            $tempRow['discount_amount'] = $row->discount_value;

            $rows[] = $tempRow;
        }

        return response()->json(['total' => $total, 'rows' => $rows]);
    }

    public function logs(Request $request)
    {
        ResponseService::noPermissionThenRedirect('fees-paid');
        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');

        $sql = FeeDiscountLog::with(['fee_discount.student', 'fee_discount.fee', 'user']);

        $total = $sql->count();
        if ($offset >= $total && $total > 0) {
            $offset = floor(($total - 1) / $limit) * $limit;
        }

        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();

        $rows = [];
        $no = $offset + 1;
        foreach ($res as $row) {
            $tempRow = $row->toArray();
            $tempRow['no'] = $no++;
            $tempRow['logged_date'] = date('Y-m-d', strtotime($row->getRawOriginal('created_at')));
            $tempRow['student_name'] = $row->fee_discount->student->full_name ?? '-';
            $tempRow['fee_name'] = $row->fee_discount->fee->name ?? '-';
            $tempRow['action_name'] = ucfirst($row->action);
            $tempRow['done_by'] = $row->user->full_name ?? '-';

            $rows[] = $tempRow;
        }

        return response()->json(['total' => $total, 'rows' => $rows]);
    }

    public function store(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Fees Management');
        ResponseService::noPermissionThenSendJson('fees-paid');

        $request->validate([
            'student_id' => 'required|numeric',
            'fees_id' => 'required|numeric',
            'discount_scope' => 'required|in:compulsory,optional,entire',
            'discount_name' => 'required|string',
            'discount_type' => 'required|in:fixed,percentage',
            'discount_value' => 'required|numeric|min:1',
            'discount_date' => 'required'
        ]);

        try {
            DB::beginTransaction();

            $discount = FeeDiscount::create([
                'school_id' => Auth::user()->school_id,
                'student_id' => $request->student_id,
                'fees_id' => $request->fees_id,
                'discount_scope' => $request->discount_scope,
                'discount_name' => $request->discount_name,
                'discount_type' => $request->discount_type,
                'discount_value' => $request->discount_value,
                'discount_date' => date('Y-m-d', strtotime($request->discount_date)),
                'description' => $request->description,
                'created_by' => Auth::id()
            ]);

            // Save items if optional fees or installments are selected
            $items = [];
            if ($request->discount_scope == 'optional' && !empty($request->optional_fees)) {
                foreach ($request->optional_fees as $opt) {
                    $items[] = [
                        'fee_discount_id' => $discount->id,
                        'fee_type' => 'optional',
                        'fee_type_id' => is_array($opt) ? ($opt['id'] ?? 0) : $opt,
                        'original_amount' => is_array($opt) ? ($opt['original_amount'] ?? 0) : 0,
                        'discount_amount' => is_array($opt) ? ($opt['discount_amount'] ?? 0) : 0,
                        'final_amount' => is_array($opt) ? ($opt['final_amount'] ?? 0) : 0,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }
            }

            if ($request->include_installment && !empty($request->installments)) {
                foreach ($request->installments as $inst) {
                    $items[] = [
                        'fee_discount_id' => $discount->id,
                        'fee_type' => 'installment',
                        'fee_type_id' => is_array($inst) ? ($inst['id'] ?? 0) : $inst,
                        'original_amount' => is_array($inst) ? ($inst['original_amount'] ?? 0) : 0,
                        'discount_amount' => is_array($inst) ? ($inst['discount_amount'] ?? 0) : 0,
                        'final_amount' => is_array($inst) ? ($inst['final_amount'] ?? 0) : 0,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }
            }

            if (!empty($items)) {
                FeeDiscountItem::insert($items);
            }

            // Log
            FeeDiscountLog::create([
                'fee_discount_id' => $discount->id,
                'action' => 'created',
                'new_data' => $discount->toArray(),
                'user_id' => Auth::id()
            ]);

            // Create Expense Category if not found
            $expenseCategory = ExpenseCategory::firstOrCreate(
                ['name' => 'Fees Relief', 'school_id' => Auth::user()->school_id],
                ['description' => 'Automatically created for fee discounts/reliefs']
            );

            $user = User::select('first_name', 'last_name')->find($request->student_id);

            // Create Expense Entry
            $sessionYearId = app(CachingService::class)->getSessionYear()->id;

            Expense::create([
                'category_id' => $expenseCategory->id,
                'ref_no' => 'FD-' . $discount->id,
                'title' => 'Fee Relief - ' . $request->discount_name,
                'amount' => $request->discount_value,
                'date' => date('Y-m-d', strtotime($request->discount_date)),
                'description' => 'Fee discount given to student: ' . $user->full_name . '. ' . $request->description,
                'school_id' => Auth::user()->school_id,
                'session_year_id' => $sessionYearId,
                'created_by' => Auth::id()
            ]);

            DB::commit();
            ResponseService::successResponse('Fee Relief / Discount applied successfully.');
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::errorResponse('Failed to apply discount: ' . $e->getMessage());
        }
    }

    public function rollback(Request $request, $id)
    {
        ResponseService::noFeatureThenSendJson('Fees Management');
        ResponseService::noPermissionThenSendJson('fees-paid');

        try {
            DB::beginTransaction();

            $discount = FeeDiscount::findOrFail($id);

            FeeDiscountLog::create([
                'fee_discount_id' => $discount->id,
                'action' => 'rollback',
                'old_data' => $discount->toArray(),
                'user_id' => Auth::id()
            ]);

            $discount->delete();

            // Find and delete the associated expense if it exists
            \App\Models\Expense::where('ref_no', 'FD-' . $discount->id)->delete();

            DB::commit();
            return ResponseService::successResponse('Discount rolled back successfully.');
        } catch (Throwable $e) {
            DB::rollBack();
            return ResponseService::errorResponse('Failed to rollback discount: ' . $e->getMessage());
        }
    }
}
