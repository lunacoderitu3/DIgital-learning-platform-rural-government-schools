<?php

namespace App\Payments\Gateways;

use App\Payments\DTO\PaymentRequest;
use App\Payments\DTO\PaymentResult;
use App\Payments\Exceptions\GatewayUnavailableException;
use App\Payments\Exceptions\InvalidWebhookSignatureException;
use App\Payments\Exceptions\PaymentException;
use App\Payments\Exceptions\PaymentVerificationException;
use Illuminate\Http\Request;
use Razorpay\Api\Api;

/**
 * Razorpay gateway.
 *
 * Razorpay is client-side driven: createPayment builds an *order* (no redirect
 * URL) which the frontend checkout.js consumes. The order id is the reference;
 * the actual capture arrives via the payment.captured webhook.
 *
 * Mirrors the legacy SubscriptionController::razorpay_order_id() (order
 * creation) and SubscriptionWebhookController::razorpay() (webhook parsing).
 * Persisting the payment transaction stays in the business layer — the gateway
 * only talks to Razorpay.
 */
class RazorpayGateway extends AbstractPaymentGateway
{
    public function name(): string
    {
        return 'Razorpay';
    }

    private function api(): Api
    {
        $key = $this->apiKey();
        $secret = $this->secretKey();
        if (empty($key) || empty($secret)) {
            throw (new GatewayUnavailableException('Razorpay keys are not configured'))->setGateway($this->name());
        }
        return new Api($key, $secret);
    }

    public function createPayment(PaymentRequest $request): PaymentResult
    {
        $currency = $request->currency ?: ($this->currency() ?: 'INR');
        // Razorpay works in the minor unit and enforces a ₹1.00 (100 paise) floor.
        $amountMinor = max(100, (int) round($request->amount * 100));

        $this->log('Payment Created request', ['amount' => $amountMinor, 'currency' => $currency]);

        $order = $this->api()->order->create([
            'receipt'         => $request->reference ?: (string) (time() . mt_rand(0, 999999)),
            'amount'          => $amountMinor,
            'currency'        => $currency,
            'notes'           => $request->metadata,
            'payment_capture' => 1,
        ])->toArray();

        $this->log('Payment Created', ['order_id' => $order['id'] ?? null]);

        return new PaymentResult(
            gateway: $this->name(),
            status: PaymentResult::STATUS_PENDING,
            reference: $order['id'] ?? null,
            amount: $request->amount,
            currency: $currency,
            metadata: $request->metadata,
            raw: $order,
            extra: ['order' => $order],
        );
    }

    public function verifyPayment(string $reference): PaymentResult
    {
        try {
            // Reference is the Razorpay payment id here.
            $payment = $this->api()->payment->fetch($reference);
            $data = $payment->toArray();
        } catch (\Throwable $e) {
            $this->logError('Verification Failed', ['error' => $e->getMessage()]);
            throw (new PaymentVerificationException('Razorpay verification failed: ' . $e->getMessage()))->setGateway($this->name());
        }

        return new PaymentResult(
            gateway: $this->name(),
            status: ($data['status'] ?? null) === 'captured' ? PaymentResult::STATUS_SUCCEED : PaymentResult::STATUS_PENDING,
            transactionId: $data['id'] ?? $reference,
            reference: $data['order_id'] ?? null,
            amount: isset($data['amount']) ? ((float) $data['amount']) / 100 : 0.0,
            currency: $data['currency'] ?? $this->currency(),
            metadata: $this->normalizeNotes($data['notes'] ?? []),
            raw: $data,
        );
    }

    public function handleWebhook(Request $request): PaymentResult
    {
        $payload = $request->getContent();

        $this->verifySignature($payload, $request->header('X-Razorpay-Signature'));

        $data = json_decode($payload, true);
        if (!$data || !isset($data['event'])) {
            throw (new PaymentException('Invalid webhook payload'))->setGateway($this->name());
        }

        $event = $data['event'];
        $entity = $data['payload']['payment']['entity'] ?? [];
        $notes = $this->normalizeNotes($entity['notes'] ?? []);

        $this->log('Webhook Received', ['event' => $event, 'order_id' => $entity['order_id'] ?? null]);

        $status = match ($event) {
            'payment.captured' => PaymentResult::STATUS_SUCCEED,
            'payment.failed'   => PaymentResult::STATUS_FAILED,
            default            => PaymentResult::STATUS_PENDING,
        };

        return new PaymentResult(
            gateway: $this->name(),
            status: $status,
            transactionId: $entity['id'] ?? null,
            reference: $entity['order_id'] ?? null,
            amount: isset($entity['amount']) ? ((float) $entity['amount']) / 100 : 0.0,
            currency: $entity['currency'] ?? $this->currency(),
            metadata: $notes,
            raw: $data,
            event: $event,
        );
    }

    /**
     * Verify the Razorpay webhook HMAC-SHA256 signature. Skipped (with a warning)
     * when no webhook secret is configured — matching the legacy handler, which
     * had verification commented out, rather than hard-failing live traffic.
     *
     * @throws InvalidWebhookSignatureException
     */
    private function verifySignature(string $payload, ?string $signature): void
    {
        $secret = $this->webhookSecret();
        if (empty($secret)) {
            $this->logError('Webhook signature not verified: no webhook secret configured');
            return;
        }

        $expected = hash_hmac('sha256', $payload, $secret);
        if (!$signature || !hash_equals($expected, $signature)) {
            $this->logError('Signature Verification Failed');
            throw (new InvalidWebhookSignatureException('Razorpay webhook signature verification failed'))->setGateway($this->name());
        }
    }

    private function normalizeNotes($notes): array
    {
        if (is_string($notes)) {
            $decoded = json_decode($notes, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($notes) ? $notes : [];
    }
}
