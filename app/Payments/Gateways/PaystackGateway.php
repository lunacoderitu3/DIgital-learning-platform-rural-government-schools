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
 * Paystack gateway — reference implementation of {@see PaymentGateway}.
 *
 * Behaviour is extracted verbatim from the legacy
 * SubscriptionService::paystack_payment() (create) and
 * SubscriptionWebhookController::paystack() (webhook) so the charged amount,
 * currency handling, metadata shape and reference are byte-for-byte compatible
 * during the phased cut-over. The one deliberate addition is webhook signature
 * verification (the legacy handler had none), which the refactor mandates.
 */
class PaystackGateway extends AbstractPaymentGateway
{
    private const INITIALIZE_URL = 'https://api.paystack.co/transaction/initialize';
    private const VERIFY_URL      = 'https://api.paystack.co/transaction/verify/';

    public function name(): string
    {
        return 'Paystack';
    }

    public function createPayment(PaymentRequest $request): PaymentResult
    {
        $secret = $this->secretKey();
        if (empty($secret)) {
            throw (new GatewayUnavailableException('No API key provided'))->setGateway($this->name());
        }

        $currency = $this->currency();
        $amount = round((float) $this->checkMinimumAmount(strtoupper($currency), $request->amount), 2);

        $payload = [
            'amount'       => $amount * 100, // Paystack expects the minor unit (kobo/cents)
            'email'        => $request->customerEmail,
            'currency'     => $currency,
            'metadata'     => $request->metadata,
        ];
        if ($request->successUrl) {
            $payload['redirect_url'] = $request->successUrl;
        }
        if ($request->callbackUrl) {
            $payload['callback_url'] = $request->callbackUrl;
        }

        $this->log('Payment Created request', ['amount' => $amount, 'currency' => $currency]);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $secret,
            'Content-Type'  => 'application/json',
            'Cache-Control' => 'no-cache',
        ])->post(self::INITIALIZE_URL, $payload);

        $res = $response->json();

        if (!$response->successful() || empty($res['status'])) {
            $message = $res['message'] ?? 'Paystack initialization failed';
            $this->logError('Payment Created failed', ['response' => $res]);
            throw (new PaymentException('Paystack error: ' . $message))->setGateway($this->name());
        }

        $this->log('Payment Created', ['reference' => $res['data']['reference'] ?? null]);

        return new PaymentResult(
            gateway: $this->name(),
            status: PaymentResult::STATUS_PENDING,
            reference: $res['data']['reference'] ?? null,
            amount: $amount,
            currency: $currency,
            metadata: $request->metadata,
            raw: $res,
            redirectUrl: $res['data']['authorization_url'] ?? null,
        );
    }

    public function verifyPayment(string $reference): PaymentResult
    {
        $secret = $this->secretKey();
        if (empty($secret)) {
            throw (new GatewayUnavailableException('No API key provided'))->setGateway($this->name());
        }

        $this->log('Verification Started', ['reference' => $reference]);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $secret,
            'Cache-Control' => 'no-cache',
        ])->get(self::VERIFY_URL . $reference);

        $res = $response->json();

        if (!$response->successful() || empty($res['status'])) {
            $this->logError('Verification Failed', ['response' => $res]);
            throw (new PaymentVerificationException($res['message'] ?? 'Paystack verification failed'))->setGateway($this->name());
        }

        $data = $res['data'] ?? [];

        return new PaymentResult(
            gateway: $this->name(),
            status: $this->mapStatus($data['status'] ?? null),
            transactionId: isset($data['id']) ? (string) $data['id'] : null,
            reference: $data['reference'] ?? $reference,
            amount: isset($data['amount']) ? ((float) $data['amount']) / 100 : 0.0,
            currency: $data['currency'] ?? $this->currency(),
            metadata: $this->normalizeMetadata($data['metadata'] ?? []),
            raw: $res,
        );
    }

    public function handleWebhook(Request $request): PaymentResult
    {
        $payload = $request->getContent();

        $this->verifySignature($payload, $request->header('x-paystack-signature'));

        $data = json_decode($payload, true);
        if (!$data) {
            throw (new PaymentException('Invalid webhook payload'))->setGateway($this->name());
        }

        $event = $data['event'] ?? '';
        $body  = $data['data'] ?? [];
        $reference = $body['reference'] ?? '';

        if (empty($reference)) {
            throw (new PaymentException('Transaction reference not found in webhook data'))->setGateway($this->name());
        }

        $this->log('Webhook Received', ['event' => $event, 'reference' => $reference]);

        // charge.success is the only event that confirms money captured.
        $status = $event === 'charge.success'
            ? $this->mapStatus($body['status'] ?? null)
            : ((($body['status'] ?? '') === 'failed') ? PaymentResult::STATUS_FAILED : PaymentResult::STATUS_PENDING);

        return new PaymentResult(
            gateway: $this->name(),
            status: $status,
            transactionId: isset($body['id']) ? (string) $body['id'] : null,
            reference: $reference,
            amount: isset($body['amount']) ? ((float) $body['amount']) / 100 : 0.0,
            currency: $body['currency'] ?? $this->currency(),
            metadata: $this->normalizeMetadata($body['metadata'] ?? []),
            raw: $data,
            event: $event,
        );
    }

    /**
     * Verify the Paystack HMAC-SHA512 signature of the raw body. Skipped (with a
     * warning) when no secret is configured so a misconfigured environment fails
     * open exactly as the legacy handler did, rather than hard-blocking.
     *
     * @throws InvalidWebhookSignatureException
     */
    private function verifySignature(string $payload, ?string $signature): void
    {
        $secret = $this->secretKey();
        if (empty($secret)) {
            $this->logError('Webhook signature not verified: no secret key configured');
            return;
        }

        $expected = hash_hmac('sha512', $payload, $secret);
        if (!$signature || !hash_equals($expected, $signature)) {
            $this->logError('Signature Verification Failed');
            throw (new InvalidWebhookSignatureException('Paystack webhook signature verification failed'))->setGateway($this->name());
        }
    }

    /**
     * Paystack metadata can arrive as a JSON string; normalize to an array.
     */
    private function normalizeMetadata($metadata): array
    {
        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($metadata) ? $metadata : [];
    }

    private function mapStatus(?string $providerStatus): string
    {
        return match ($providerStatus) {
            'success' => PaymentResult::STATUS_SUCCEED,
            'failed'  => PaymentResult::STATUS_FAILED,
            default   => PaymentResult::STATUS_PENDING,
        };
    }
}
