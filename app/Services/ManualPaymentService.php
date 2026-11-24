<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CompulsoryFee;
use App\Models\Fee;
use App\Models\FeeDiscount;
use App\Models\FeesPaid;
use App\Models\OptionalFee;
use App\Models\PaymentTransaction;
use App\Models\SchoolSetting;
use App\Repositories\CompulsoryFee\CompulsoryFeeInterface;
use App\Repositories\FeesPaid\FeesPaidInterface;
use App\Repositories\Files\FilesInterface;
use App\Repositories\OptionalFee\OptionalFeeInterface;
use Exception;
use App\Models\FeesClassType;
use App\Models\FeesInstallment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ManualPaymentService
{
    private CompulsoryFeeInterface $compulsoryFee;
    private OptionalFeeInterface $optionalFee;
    private FeesPaidInterface $feesPaid;
    private FilesInterface $files;
    private CachingService $cache;

    public function __construct(
        CompulsoryFeeInterface $compulsoryFee,
        OptionalFeeInterface $optionalFee,
        FeesPaidInterface $feesPaid,
        FilesInterface $files,
        CachingService $cache
    ) {
        $this->compulsoryFee = $compulsoryFee;
        $this->optionalFee = $optionalFee;
        $this->feesPaid = $feesPaid;
        $this->files = $files;
        $this->cache = $cache;
    }

    public function isManualPaymentEnabled(int $schoolId): bool
    {
        // return (bool) SchoolSetting::where('school_id', $schoolId)
        //     ->where('name', 'manual_payment')
        //     ->value('data');
        return \App\Models\PaymentConfiguration::where('school_id', $schoolId)
            ->where('payment_method', 'Manual')
            ->where('status', 1)
            ->exists();
    }

    public function isCompulsoryFeeLocked(int $studentId, ?array $installmentIds = null, ?int $feesPaidId = null): bool
    {
        $query = $this->compulsoryFee->builder()
            ->where('student_id', $studentId)
            ->where('mode', CompulsoryFee::MODE_MANUAL)
            ->where('status', CompulsoryFee::MANUAL_UNDER_REVIEW) // 'Pending'
            ->whereNull('deleted_at');

        if (!empty($installmentIds)) {
            $query->whereIn('installment_id', $installmentIds);
        } elseif ($feesPaidId) {
            $query->where('fees_paid_id', $feesPaidId);
        }

        return $query->exists();
    }

    public function isCompulsoryFeeFullyPaid(int $studentId, ?int $feesId = null): bool
    {
        $query = $this->feesPaid->builder()
            ->where('student_id', $studentId)
            ->where('is_fully_paid', 1)
            ->whereNull('deleted_at');

        if ($feesId) {
            $query->where('fees_id', $feesId);
        }

        return $query->exists();
    }

    public function isOptionalFeeLocked(int $studentId, array $feesClassIds): bool
    {
        return $this->optionalFee->builder()
            ->where('student_id', $studentId)
            ->whereIn('fees_class_id', $feesClassIds)
            ->where('mode', OptionalFee::MODE_MANUAL)
            ->where('status', OptionalFee::MANUAL_UNDER_REVIEW) // 'Pending'
            ->whereNull('deleted_at')
            ->exists();
    }

    public function isOptionalFeeFullyPaid(int $studentId, array $feesClassIds): bool
    {
        $count = $this->optionalFee->builder()
            ->where('student_id', $studentId)
            ->whereIn('fees_class_id', $feesClassIds)
            ->where('status', OptionalFee::MANUAL_APPROVED) // 'Success'
            ->whereNull('deleted_at')
            ->count();
        return $count > 0;
    }

    /**
     * Build the internal reference of a manual payment.
     *
     * Parents never supply a cheque number, so cheque_no stays NULL on the fee rows and
     * this reference (stored on payment_transactions.order_id) is what identifies the
     * payment. Every fee row of one submission points at that single transaction, which
     * is how the admin side groups them into a single reviewable payment.
     */
    private function generateManualPaymentReference(): string
    {
        do {
            $reference = 'MANUAL-' . date('Ymd') . '-' . strtoupper(Str::random(4));
        } while (PaymentTransaction::where('order_id', $reference)->exists());

        return $reference;
    }

    /**
     * Create the single payment transaction that represents one manual submission.
     */
    private function createManualPaymentTransaction(array $data, float $amount, string $type, UploadedFile $receipt, string $uploadedPath): PaymentTransaction
    {
        $paymentTransaction = PaymentTransaction::create([
            'user_id'         => $data['student_id'],
            'amount'          => round($amount, 2),
            'payment_gateway' => 'manual',
            'order_id'        => $this->generateManualPaymentReference(),
            'payment_status'  => 'pending',
            'type'            => $type,
            'school_id'       => $data['school_id'],
        ]);

        // The proof belongs to the payment as a whole, not to a single installment
        $this->files->create([
            'modal_type' => PaymentTransaction::class,
            'modal_id'   => $paymentTransaction->id,
            'file_name'  => $receipt->getClientOriginalName(),
            'file_url'   => $uploadedPath,
            'type'       => 1,
            'school_id'  => $data['school_id']
        ]);

        return $paymentTransaction;
    }

    public function submitCompulsoryManualPayment(array $data, UploadedFile $receipt)
    {
        try {
            return DB::transaction(function () use ($data, $receipt) {
                $feesPaidId = $data['fees_paid_id'] ?? null;
                $hasInstallments = !empty($data['installments']) && is_array($data['installments']);

                // Create or locate summary fees_paid record if fees_id provided
                if (!$feesPaidId && !empty($data['fees_id'])) {
                    $feesPaid = $this->feesPaid->builder()
                        ->where('fees_id', $data['fees_id'])
                        ->where('student_id', $data['student_id'])
                        ->whereNull('deleted_at')
                        ->first();

                    if (!$feesPaid) {
                        $feesPaid = $this->feesPaid->create([
                            'fees_id'             => $data['fees_id'],
                            'student_id'          => $data['student_id'],
                            'is_fully_paid'       => 0,
                            'is_used_installment' => $hasInstallments ? 1 : 0,
                            'amount'              => 0,
                            'date'                => date('Y-m-d'),
                            'school_id'           => $data['school_id']
                        ]);
                    }
                    $feesPaidId = $feesPaid->id;
                }

                if ($feesPaidId) {
                    $feesPaid = $this->feesPaid->builder()
                        ->where('id', $feesPaidId)
                        ->where('student_id', $data['student_id'])
                        ->whereNull('deleted_at')
                        ->first();

                    if (!$feesPaid) {
                        throw new Exception('Invalid fees_paid_id for this student.');
                    }
                }

                $uploadedPath = UploadService::upload($receipt, 'manual_receipt');
                $createdFees = [];

                // Whole payment amount : every installment of this submission plus its due charges
                $paymentAmount = $hasInstallments
                    ? collect($data['installments'])->sum(fn($inst) => (float) $inst['amount'] + (float) ($inst['due_charges'] ?? 0))
                    : (float) $data['amount'];

                $paymentTransaction = $this->createManualPaymentTransaction(
                    $data,
                    (float) $paymentAmount,
                    'Compulsory Fees',
                    $receipt,
                    $uploadedPath
                );

                if ($hasInstallments) {
                    foreach ($data['installments'] as $inst) {
                        $compulsoryFee = $this->compulsoryFee->create([
                            'student_id'             => $data['student_id'],
                            'fees_paid_id'           => $feesPaidId,
                            'payment_transaction_id' => $paymentTransaction->id,
                            'type'                   => 'Installment Payment',
                            'installment_id'         => $inst['id'],
                            'mode'                   => CompulsoryFee::MODE_MANUAL,
                            // The parent never provides a cheque number : the payment is
                            // identified by the transaction reference instead.
                            'cheque_no'              => null,
                            'amount'                 => $inst['amount'],
                            'due_charges'            => $inst['due_charges'] ?? 0,
                            'status'                 => CompulsoryFee::MANUAL_UNDER_REVIEW,
                            'notes'                  => $data['notes'] ?? null,
                            'date'                   => date('Y-m-d'),
                            'school_id'              => $data['school_id']
                        ]);

                        $this->files->create([
                            'modal_type' => CompulsoryFee::class,
                            'modal_id'   => $compulsoryFee->id,
                            'file_name'  => $receipt->getClientOriginalName(),
                            'file_url'   => $uploadedPath,
                            'type'       => 1,
                            'school_id'  => $data['school_id']
                        ]);
                        $createdFees[] = $compulsoryFee;
                    }
                } else {
                    $compulsoryFee = $this->compulsoryFee->create([
                        'student_id'             => $data['student_id'],
                        'fees_paid_id'           => $feesPaidId,
                        'payment_transaction_id' => $paymentTransaction->id,
                        'type'                   => 'Full Payment',
                        'installment_id'         => null,
                        'mode'                   => CompulsoryFee::MODE_MANUAL,
                        'cheque_no'              => null,
                        'amount'                 => $data['amount'],
                        'due_charges'            => $data['due_charges'] ?? 0,
                        'status'                 => CompulsoryFee::MANUAL_UNDER_REVIEW,
                        'notes'                  => $data['notes'] ?? null,
                        'date'                   => date('Y-m-d'),
                        'school_id'              => $data['school_id']
                    ]);

                    $this->files->create([
                        'modal_type' => CompulsoryFee::class,
                        'modal_id'   => $compulsoryFee->id,
                        'file_name'  => $receipt->getClientOriginalName(),
                        'file_url'   => $uploadedPath,
                        'type'       => 1,
                        'school_id'  => $data['school_id']
                    ]);
                    $createdFees[] = $compulsoryFee;
                }

                return [
                    'payment_transaction' => $paymentTransaction,
                    'reference'           => $paymentTransaction->order_id,
                    'amount'              => (float) $paymentTransaction->amount,
                    'receipt_url'         => $uploadedPath,
                    'fees'                => $createdFees,
                ];
            });
        } catch (Throwable $e) {
            Log::error('Manual compulsory receipt upload failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function submitOptionalManualPayment(array $data, UploadedFile $receipt)
    {
        try {
            return DB::transaction(function () use ($data, $receipt) {
                $feesPaidId = null;
                if (!empty($data['fees_id'])) {
                    $feesPaid = $this->feesPaid->builder()
                        ->where(['fees_id' => $data['fees_id'], 'student_id' => $data['student_id']])
                        ->whereNull('deleted_at')
                        ->first();

                    if (!$feesPaid) {
                        $feesPaid = $this->feesPaid->create([
                            'fees_id'       => $data['fees_id'],
                            'student_id'    => $data['student_id'],
                            'is_fully_paid' => 0,
                            'amount'        => 0,
                            'date'          => date('Y-m-d'),
                            'school_id'     => $data['school_id']
                        ]);
                    }
                    $feesPaidId = $feesPaid->id;
                }

                $uploadedPath = UploadService::upload($receipt, 'manual_receipt');
                $createdFees = [];

                // Every optional fee of this submission belongs to one payment
                $paymentAmount = !empty($data['optional_fees']) && is_array($data['optional_fees'])
                    ? collect($data['optional_fees'])->sum(fn($opt) => (float) $opt['amount'])
                    : (float) ($data['amount'] ?? 0);

                $paymentTransaction = $this->createManualPaymentTransaction(
                    $data,
                    (float) $paymentAmount,
                    'Optional Fees',
                    $receipt,
                    $uploadedPath
                );

                if (!empty($data['optional_fees']) && is_array($data['optional_fees'])) {
                    foreach ($data['optional_fees'] as $opt) {
                        $optionalFee = $this->optionalFee->create([
                            'student_id'             => $data['student_id'],
                            'class_id'               => $data['class_id'],
                            'fees_class_id'          => $opt['id'],
                            'fees_paid_id'           => $feesPaidId,
                            'payment_transaction_id' => $paymentTransaction->id,
                            'mode'                   => OptionalFee::MODE_MANUAL,
                            // Parents never provide a cheque number for manual payments
                            'cheque_no'              => null,
                            'amount'                 => $opt['amount'],
                            'status'                 => OptionalFee::MANUAL_UNDER_REVIEW,
                            'notes'                  => $data['notes'] ?? null,
                            'date'                   => date('Y-m-d'),
                            'school_id'              => $data['school_id']
                        ]);

                        $this->files->create([
                            'modal_type' => OptionalFee::class,
                            'modal_id'   => $optionalFee->id,
                            'file_name'  => $receipt->getClientOriginalName(),
                            'file_url'   => $uploadedPath,
                            'type'       => 1,
                            'school_id'  => $data['school_id']
                        ]);
                        $createdFees[] = $optionalFee;
                    }
                }

                return [
                    'payment_transaction' => $paymentTransaction,
                    'reference'           => $paymentTransaction->order_id,
                    'amount'              => (float) $paymentTransaction->amount,
                    'receipt_url'         => $uploadedPath,
                    'fees'                => $createdFees,
                ];
            });
        } catch (Throwable $e) {
            Log::error('Manual optional receipt upload failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Calculate remaining payable amount for a specific installment.
     */
    public function getInstallmentRemainingAmount(int $studentId, int $installmentId): float
    {
        $installment = FeesInstallment::find($installmentId);
        if (!$installment) {
            throw new Exception('Invalid installment ID.');
        }

        $fee = Fee::with(['installments'])->find($installment->fees_id);
        if (!$fee) {
            throw new Exception('Invalid fee ID for installment.');
        }

        // Base installment amount (fallback to equal division if installment_amount is 0)
        $instAmount = (float)$installment->installment_amount;
        if ($instAmount <= 0 && $fee->installments->count() > 0) {
            $instAmount = (float)($fee->total_compulsory_fees / $fee->installments->count());
        }

        // Fetch installment-specific discounts
        $discounts = FeeDiscount::where('student_id', $studentId)
            ->where('fees_id', $fee->id)
            ->whereIn('discount_scope', ['compulsory', 'entire'])
            ->with('items')
            ->get();

        $installmentDiscount = 0;
        foreach ($discounts as $discount) {
            foreach ($discount->items as $item) {
                if ($item->fee_type === 'installment' && $item->fee_type_id == $installmentId) {
                    $installmentDiscount += (float)$item->discount_amount;
                }
            }
        }

        // Fetch already paid amount for this specific installment
        $alreadyPaid = (float)$this->compulsoryFee->builder()
            ->where('student_id', $studentId)
            ->where('installment_id', $installmentId)
            ->where('status', CompulsoryFee::MANUAL_APPROVED)
            ->whereNull('deleted_at')
            ->sum('amount');

        $netInstallmentAmount = max(0, $instAmount - $installmentDiscount);
        $remaining = max(0, $netInstallmentAmount - $alreadyPaid);

        return round((float)$remaining, 2);
    }

    /**
     * Calculate remaining payable amount for non-installment (full payment) compulsory fee.
     */
    public function getNonInstallmentCompulsoryFeeRemainingAmount(int $studentId, int $feesId): float
    {
        $fee = Fee::with(['fees_class_type'])->find($feesId);
        if (!$fee) {
            throw new Exception('Invalid fee ID.');
        }

        $totalCompulsoryFees = (float)($fee->total_compulsory_fees ?? 0);

        // Fetch compulsory discounts
        $discounts = FeeDiscount::where('student_id', $studentId)
            ->where('fees_id', $feesId)
            ->whereIn('discount_scope', ['compulsory', 'entire'])
            ->with('items')
            ->get();

        $totalDiscount = 0;
        if ($fee->installments()->exists()) {
            foreach ($discounts as $discount) {
                foreach ($discount->items as $item) {
                    if ($item->fee_type === 'installment') {
                        $totalDiscount += (float)$item->discount_amount;
                    }
                }
            }
        } else {
            $totalDiscount = (float)$discounts->sum('discount_value');
        }

        // Fetch all previously paid compulsory amounts for this fee
        $alreadyPaid = (float)$this->compulsoryFee->builder()
            ->where('student_id', $studentId)
            ->whereHas('fees_paid', function ($q) use ($feesId) {
                $q->where('fees_id', $feesId);
            })
            ->where('status', CompulsoryFee::MANUAL_APPROVED)
            ->whereNull('deleted_at')
            ->sum('amount');

        $netTotal = max(0, $totalCompulsoryFees - $totalDiscount);
        $remaining = max(0, $netTotal - $alreadyPaid);

        return round((float)$remaining, 2);
    }
    /**
     * Calculate remaining payable amount for an optional fee.
     */
    public function getOptionalFeeRemainingAmount(int $studentId, int $feesClassId): float
    {
        $feesClassType = FeesClassType::find($feesClassId);
        if (!$feesClassType) {
            throw new Exception('Invalid optional fee ID.');
        }

        $discounts = FeeDiscount::where('student_id', $studentId)
            ->where('fees_id', $feesClassType->fees_id)
            ->whereIn('discount_scope', ['optional', 'entire'])
            ->with('items')
            ->get();

        $discountAmount = 0;
        foreach ($discounts as $discount) {
            foreach ($discount->items as $item) {
                if ($item->fee_type === 'optional' && $item->fee_type_id == $feesClassId) {
                    $discountAmount += (float)$item->discount_amount;
                }
            }
        }

        $alreadyPaid = (float)$this->optionalFee->builder()
            ->where('student_id', $studentId)
            ->where('fees_class_id', $feesClassId)
            ->where('status', OptionalFee::MANUAL_APPROVED)
            ->whereNull('deleted_at')
            ->sum('amount');

        $netAmount = max(0, (float)$feesClassType->amount - $discountAmount);
        $remaining = max(0, $netAmount - $alreadyPaid);

        return round((float)$remaining, 2);
    }
}
