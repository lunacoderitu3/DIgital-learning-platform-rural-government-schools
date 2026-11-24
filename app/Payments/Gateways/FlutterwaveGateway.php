<?php

namespace App\Payments\Gateways;

use App\Payments\DTO\PaymentRequest;
use App\Payments\DTO\PaymentResult;
use App\Payments\Exceptions\GatewayUnavailableException;
use App\Payments\Exceptions\InvalidWebhookSignatureException;
use App\Payments\Exceptions\PaymentException;
use App\Payments\Exceptions\PaymentVerificationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Flutterwave gateway.
 *
 * createPayment posts a Standard payment and returns the hosted-link redirect.
 * The tx_ref is the reference and must be persisted on the payment transaction
 * by the caller so the webhook can be correlated. Mirrors the legacy
 * SubscriptionService::flutterwave_payment() and the flutterwave() webhook.
 */
class FlutterwaveGateway extends AbstractPaymentGateway
{
    private const PAYMENTS_URL = 'https://api.flutterwave.com/v3/payments';
    private const VERIFY_URL   = 'https://api.flutterwave.com/v3/transactions/verify_by_reference?tx_ref=';

    public function name(): string
    {
        return 'Flutterwave';
    }

    public function createPayment(PaymentRequest $request): PaymentResult
    {
        $secret = $this->secretKey();
        if (empty($secret)) {
            throw (new GatewayUnavailableException('No API key provided for Flutterwave'))->setGateway($this->name());
        }

        $currency = strtoupper($this->currency());
        $checkAmount = (float) $this->checkMinimumAmount($currency, $request->amount);
        $txRef = $request->reference ?: ('tx_' . time());

        $payload = [
            'tx_ref'    => $txRef,
            'amount'    => $checkAmount,
            'currency'  => $currency,
            'email'     => $request->customerEmail,
            'order_name' => $request->productName,
            'customer'  => [
                'email'  => $request->customerEmail,
                'name'   => $request->customerName,
                'mobile' => $request->customerMobile,
            ],
            'meta'         => $request->metadata,
            'redirect_url' => $request->successUrl ?? $request->callbackUrl,
            'cancel_url'   => $request->cancelUrl,
        ];

        $this->log('Payment Created request', ['tx_ref' => $txRef, 'amount' => $checkAmount, 'currency' => $currency]);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $secret,
            'Content-Type'  => 'application/json',
        ])->post(self::PAYMENTS_URL, $payload);

        $res = $response->json();

        if (!$response->successful() || ($res['status'] ?? null) !== 'success') {
            $this->logError('Payment Created failed', ['response' => $res]);
            throw (new PaymentException('Flutterwave error: ' . ($res['message'] ?? 'Unknown error')))->setGateway($this->name());
        }

        $this->log('Payment Created', ['tx_ref' => $txRef]);

        return new PaymentResult(
            gateway: $this->name(),
            status: PaymentResult::STATUS_PENDING,
            reference: $txRef,
            amount: $checkAmount,
            currency: $currency,
            metadata: $request->metadata,
            raw: $res,
            redirectUrl: $res['data']['link'] ?? null,
        );
    }

    public function verifyPayment(string $reference): PaymentResult
    {
        $secret = $this->secretKey();
        if (empty($secret)) {
            throw (new GatewayUnavailableException('No API key provided for Flutterwave'))->setGateway($this->name());
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $secret,
        ])->get(self::VERIFY_URL . urlencode($reference));

        $res = $response->json();

        if (!$response->successful() || ($res['status'] ?? null) !== 'success') {
            $this->logError('Verification Failed', ['response' => $res]);
            throw (new PaymentVerificationException($res['message'] ?? 'Flutterwave verification failed'))->setGateway($this->name());
        }

        $data = $res['data'] ?? [];

        return new PaymentResult(
            gateway: $this->name(),
            status: ($data['status'] ?? null) === 'successful' ? PaymentResult::STATUS_SUCCEED : PaymentResult::STATUS_PENDING,
            transactionId: isset($data['id']) ? (string) $data['id'] : null,
            reference: $data['tx_ref'] ?? $reference,
            amount: (float) ($data['amount'] ?? 0),
            currency: $data['currency'] ?? $this->currency(),
            metadata: $this->normalizeMeta($data['meta'] ?? []),
            raw: $res,
        );
    }

    public function handleWebhook(Request $request): PaymentResult
    {
        $this->verifySignature($request->header('verif-hash'));

        $payload = $request->getContent();
        $data = json_decode($payload, true);
        if (!$data) {
            throw (new PaymentException('Invalid webhook payload'))->setGateway($this->name());
        }

        $event = $data['event'] ?? '';
        $body = $data['data'] ?? [];
        $txRef = $body['tx_ref'] ?? '';

        if (empty($txRef)) {
            throw (new PaymentException('Transaction reference not found in webhook data'))->setGateway($this->name());
        }

        $this->log('Webhook Received', ['event' => $event, 'tx_ref' => $txRef]);

        // Legacy treats charge.completed as captured; an explicit "failed" status
        // marks failure; everything else stays pending.
        $status = $event === 'charge.completed'
            ? PaymentResult::STATUS_SUCCEED
            : ((($body['status'] ?? '') === 'failed') ? PaymentResult::STATUS_FAILED : PaymentResult::STATUS_PENDING);

        return new PaymentResult(
            gateway: $this->name(),
            status: $status,
            transactionId: isset($body['id']) ? (string) $body['id'] : null,
            reference: $txRef,
            amount: (float) ($body['amount'] ?? 0),
            currency: $body['currency'] ?? $this->currency(),
            metadata: $this->normalizeMeta($data['meta_data'] ?? ($body['meta'] ?? [])),
            raw: $data,
            event: $event,
        );
    }

    /**
     * Flutterwave signs webhooks with a static secret hash echoed in the
     * "verif-hash" header. Skipped (with a warning) when unset, matching the
     * legacy handler which performed no verification.
     *
     * @throws InvalidWebhookSignatureException
     */
    private function verifySignature(?string $signature): void
    {
        $secret = $this->webhookSecret();
        if (empty($secret)) {
            $this->logError('Webhook signature not verified: no webhook secret configured');
            return;
        }

        if (!$signature || !hash_equals($secret, $signature)) {
            $this->logError('Signature Verification Failed');
            throw (new InvalidWebhookSignatureException('Flutterwave webhook signature verification failed'))->setGateway($this->name());
        }
    }

    private function normalizeMeta($meta): array
    {
        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($meta) ? $meta : [];
    }
}
