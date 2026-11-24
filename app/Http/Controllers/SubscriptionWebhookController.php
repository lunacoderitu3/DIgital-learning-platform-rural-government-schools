<?php

namespace App\Http\Controllers;

use App\Models\PaymentTransaction;
use App\Payments\DTO\PaymentResult;
use App\Payments\Exceptions\InvalidWebhookSignatureException;
use App\Payments\Exceptions\PaymentException;
use App\Repositories\PaymentTransaction\PaymentTransactionInterface;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Standardized webhook entry point for every payment gateway.
 *
 * Each endpoint is intentionally thin and does only three things:
 *   1. verify + parse the request   -> PaymentService::handleWebhook (gateway layer)
 *   2. call the business service     -> SubscriptionService::fulfillFromPayment
 *   3. return an HTTP response
 *
 * All subscription/billing/activation rules live in {@see SubscriptionService};
 * all provider-specific parsing/signature checks live in the gateway classes.
 * Adding a new provider needs no change here beyond a one-line endpoint that
 * delegates to {@see self::process()}.
 */
class SubscriptionWebhookController extends Controller
{
    public function __construct(
        private PaymentTransactionInterface $paymentTransaction,
        private PaymentService $paymentService,
        private SubscriptionService $subscriptionService,
    ) {
    }

    /**
     * Stripe activates the plan in the success redirect, so its webhook is
     * confirm-only: it just reconciles the transaction's payment status.
     */
    public function stripe(Request $request)
    {
        return $this->confirmOnly('Stripe', $request);
    }

    public function razorpay(Request $request)
    {
        return $this->process('Razorpay', $request);
    }

    public function paystack(Request $request)
    {
        return $this->process('Paystack', $request);
    }

    public function flutterwave(Request $request)
    {
        return $this->process('Flutterwave', $request);
    }

    /**
     * Unified, idempotent webhook pipeline for the fully webhook-driven gateways
     * (Razorpay, Paystack, Flutterwave).
     */
    private function process(string $method, Request $request)
    {
        DB::setDefaultConnection('mysql');

        try {
            $result = $this->paymentService->handleWebhook($method, $request);
        } catch (InvalidWebhookSignatureException $e) {
            Log::error("[Webhook][{$method}] Signature Verification Failed");
            return response()->json(['error' => 'Invalid signature'], 400);
        } catch (PaymentException | Throwable $e) {
            Log::error("[Webhook][{$method}] Verification Failed: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 400);
        }

        $transaction = $this->resolveTransaction($result);

        // Idempotency: a transaction already marked succeed is never re-processed.
        if ($transaction && $transaction->payment_status === PaymentResult::STATUS_SUCCEED) {
            Log::info("[Webhook][{$method}] Duplicate Webhook Ignored", ['reference' => $result->reference]);
            return response()->json(['status' => 'success', 'message' => 'Payment already processed']);
        }

        if ($result->isFailed()) {
            if ($transaction) {
                $this->paymentTransaction->update($transaction->id, ['payment_status' => PaymentResult::STATUS_FAILED]);
            }
            Log::info("[Webhook][{$method}] Payment Failed", ['reference' => $result->reference]);
            return response()->json(['status' => 'failed'], 400);
        }

        if (!$result->isSuccessful()) {
            // Authorized / pending events — acknowledge without acting.
            return response()->json(['status' => 'received'], 200);
        }

        try {
            DB::beginTransaction();
            $transaction = $this->recordSuccess($result, $transaction);
            $this->subscriptionService->fulfillFromPayment($result, $transaction);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error("[Webhook][{$method}] Fulfillment error: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json(['error' => $e->getMessage()], 400);
        }

        Log::info("[Webhook][{$method}] Subscription Activated", ['reference' => $result->reference]);
        return response()->json(['status' => 'success']);
    }

    /**
     * Confirm-only pipeline: verify the webhook and reconcile the transaction's
     * status. No subscription is created here (Stripe activates in the redirect).
     */
    private function confirmOnly(string $method, Request $request)
    {
        DB::setDefaultConnection('mysql');

        try {
            $result = $this->paymentService->handleWebhook($method, $request);
        } catch (InvalidWebhookSignatureException $e) {
            Log::error("[Webhook][{$method}] Signature Verification Failed");
            return response('', 400);
        } catch (PaymentException | Throwable $e) {
            Log::error("[Webhook][{$method}] Verification Failed: " . $e->getMessage());
            return response('', 400);
        }

        $transaction = $result->reference ? $this->paymentService->findByReference($result->reference) : null;

        if (!$transaction) {
            Log::error("[Webhook][{$method}] Payment Transaction not found", ['reference' => $result->reference]);
            return response('', 200);
        }

        if ($transaction->payment_status === PaymentResult::STATUS_SUCCEED) {
            Log::info("[Webhook][{$method}] Duplicate Webhook Ignored");
            return response('', 200);
        }

        $this->paymentTransaction->update($transaction->id, [
            'payment_status' => $result->status,
            'school_id'      => $transaction->school_id,
        ]);

        return response('', $result->isFailed() ? 400 : 200);
    }

    /**
     * Find the transaction a webhook refers to.
     *
     * Razorpay pre-creates the transaction and references its id in the webhook
     * notes; the other gateways correlate by gateway reference (order_id/tx_ref).
     */
    private function resolveTransaction(PaymentResult $result): ?PaymentTransaction
    {
        $transactionId = $result->meta('payment_transaction_id');
        if ($transactionId) {
            $existing = PaymentTransaction::on('mysql')->find($transactionId);
            if ($existing) {
                return $existing;
            }
        }

        return $result->reference ? $this->paymentService->findByReference($result->reference) : null;
    }

    /**
     * Mark the resolved transaction succeeded (or create one when the gateway did
     * not pre-create it), filling in the gateway reference and payment id.
     */
    private function recordSuccess(PaymentResult $result, ?PaymentTransaction $transaction): PaymentTransaction
    {
        $data = array_filter([
            'payment_status'  => PaymentResult::STATUS_SUCCEED,
            'payment_gateway' => $result->gateway,
            'order_id'        => $result->reference,
            'payment_id'      => $result->transactionId,
            'school_id'       => $result->meta('school_id'),
        ], static fn ($value) => !is_null($value));

        if ($transaction) {
            $this->paymentTransaction->update($transaction->id, $data);
            return $transaction->fresh();
        }

        return $this->paymentTransaction->create(array_merge([
            'user_id' => $result->meta('user_id'),
            'amount'  => $result->amount,
        ], $data));
    }
}
