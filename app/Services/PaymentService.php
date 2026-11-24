<?php

namespace App\Services;

use App\Models\PaymentTransaction;
use App\Payments\DTO\PaymentRequest;
use App\Payments\DTO\PaymentResult;
use App\Payments\PaymentGatewayFactory;
use App\Repositories\PaymentTransaction\PaymentTransactionInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Gateway-facing orchestrator.
 *
 * PaymentService is the only collaborator the application talks to for
 * create / verify / webhook concerns. It resolves the right gateway through the
 * {@see PaymentGatewayFactory}, delegates the provider-specific work, and owns
 * the unified payment_transactions history.
 *
 * It contains ZERO subscription/billing business rules — those live in
 * {@see SubscriptionService}. This is the clean seam the refactor introduces:
 *   Controller -> SubscriptionService -> PaymentService -> Gateway.
 */
class PaymentService
{
    public function __construct(
        private PaymentGatewayFactory $factory,
        private PaymentTransactionInterface $paymentTransaction,
    ) {
    }

    /**
     * Create a payment with the given method and return the standardized result
     * (carrying the redirect URL / order payload).
     *
     * @throws \App\Payments\Exceptions\PaymentException
     */
    public function createPayment(string $method, PaymentRequest $request): PaymentResult
    {
        $gateway = $this->factory->make($method);
        $result = $gateway->createPayment($request);

        Log::info('[Payment] Payment Created', [
            'gateway'   => $result->gateway,
            'reference' => $result->reference,
            'amount'    => $result->amount,
            'school_id' => $request->schoolId,
        ]);

        return $result;
    }

    /**
     * Verify a payment by reference through the named gateway.
     *
     * @throws \App\Payments\Exceptions\PaymentVerificationException
     */
    public function verify(string $method, string $reference): PaymentResult
    {
        return $this->factory->make($method)->verifyPayment($reference);
    }

    /**
     * Parse + signature-verify an inbound webhook and return the standardized
     * result. Business fulfillment is the caller's responsibility
     * (SubscriptionService), keeping the webhook controller thin.
     *
     * @throws \App\Payments\Exceptions\InvalidWebhookSignatureException
     * @throws \App\Payments\Exceptions\PaymentException
     */
    public function handleWebhook(string $method, Request $request): PaymentResult
    {
        $gateway = $this->factory->make($method);
        $result = $gateway->handleWebhook($request);

        Log::info('[Payment] Webhook Received', [
            'gateway'   => $result->gateway,
            'event'     => $result->event,
            'reference' => $result->reference,
            'status'    => $result->status,
        ]);

        return $result;
    }

    /**
     * Find an existing transaction by its gateway reference (order_id).
     */
    public function findByReference(string $reference): ?PaymentTransaction
    {
        DB::setDefaultConnection('mysql');
        return PaymentTransaction::on('mysql')->where('order_id', $reference)->first();
    }

    /**
     * Idempotency guard: has this reference already been marked succeed?
     */
    public function alreadySucceeded(string $reference): bool
    {
        $transaction = $this->findByReference($reference);
        return $transaction && $transaction->payment_status === PaymentResult::STATUS_SUCCEED;
    }

    /**
     * Record (insert or update) the unified payment history row for a result.
     *
     * Every gateway flows through here so payment_transactions is consistent
     * regardless of provider: gateway, reference, transaction id, amount,
     * status, owning school/user and type.
     *
     * @param array $overrides Extra/explicit columns (user_id, school_id, type, ...).
     */
    public function recordTransaction(PaymentResult $result, array $overrides = []): PaymentTransaction
    {
        DB::setDefaultConnection('mysql');

        $data = array_merge([
            'amount'          => $result->amount,
            'payment_gateway' => $result->gateway,
            'order_id'        => $result->reference,
            'payment_id'      => $result->transactionId,
            'payment_status'  => $result->status,
            'school_id'       => $result->meta('school_id'),
            'user_id'         => $result->meta('user_id'),
        ], $overrides);

        $existing = $result->reference ? $this->findByReference($result->reference) : null;

        if ($existing) {
            $this->paymentTransaction->update($existing->id, $data);
            Log::info('[Payment] Transaction updated', ['id' => $existing->id, 'status' => $result->status]);
            return $existing->fresh();
        }

        $transaction = $this->paymentTransaction->create($data);
        Log::info('[Payment] Transaction created', ['id' => $transaction->id, 'status' => $result->status]);
        return $transaction;
    }
}
