<?php

namespace App\Payments\Gateways;

use App\Payments\DTO\PaymentRequest;
use App\Payments\DTO\PaymentResult;
use App\Payments\Exceptions\GatewayUnavailableException;
use App\Payments\Exceptions\InvalidWebhookSignatureException;
use App\Payments\Exceptions\PaymentException;
use App\Payments\Exceptions\PaymentVerificationException;
use Illuminate\Http\Request;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Stripe;

/**
 * Stripe gateway.
 *
 * Stripe is the odd one out historically: the subscription is activated in the
 * success *redirect* ({@see verifyPayment} feeds that), while the webhook only
 * confirms the payment status. That asymmetry is preserved — the webhook here
 * normalizes status only and carries no business metadata, so the caller treats
 * Stripe webhooks as "confirm only".
 *
 * createPayment reproduces the legacy curl Checkout Session call verbatim so the
 * generated session, success/cancel URLs and metadata are unchanged.
 */
class StripeGateway extends AbstractPaymentGateway
{
    public function name(): string
    {
        return 'Stripe';
    }

    public function createPayment(PaymentRequest $request): PaymentResult
    {
        $secret = $this->secretKey();
        if (empty($secret)) {
            throw (new GatewayUnavailableException('Stripe API key is not configured'))->setGateway($this->name());
        }

        $currency = $this->currency();
        $checkAmount = round((float) $this->checkMinimumAmount(strtoupper($currency), $request->amount), 2);

        $fields = [
            'line_items[0][price_data][currency]'              => strtolower($currency),
            'line_items[0][price_data][product_data][name]'    => $request->productName,
            'line_items[0][price_data][unit_amount]'           => (int) ($checkAmount * 100),
            'line_items[0][quantity]'                          => 1,
            'mode'                                             => 'payment',
            'success_url'                                      => $request->successUrl,
            'cancel_url'                                       => $request->cancelUrl,
        ];

        // Echo metadata as Stripe metadata[<key>] fields.
        foreach ($request->metadata as $key => $value) {
            $fields["metadata[{$key}]"] = $value;
        }

        $this->log('Payment Created request', ['amount' => $checkAmount, 'currency' => $currency]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://api.stripe.com/v1/checkout/sessions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $secret,
                'Content-Type: application/x-www-form-urlencoded',
                'Stripe-Version: 2022-11-15',
            ],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($fields),
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            $this->logError('Connection error', ['error' => $error]);
            throw (new PaymentException('Connection error occurred'))->setGateway($this->name());
        }
        curl_close($ch);

        $session = json_decode($response, true);

        if (isset($session['error'])) {
            $this->logError('Stripe API Error', ['message' => $session['error']['message'] ?? null]);
            throw (new PaymentException('Stripe error: ' . ($session['error']['message'] ?? 'unknown')))->setGateway($this->name());
        }

        $this->log('Payment Created', ['session' => $session['id'] ?? null]);

        return new PaymentResult(
            gateway: $this->name(),
            status: PaymentResult::STATUS_PENDING,
            reference: $session['id'] ?? null,
            amount: $checkAmount,
            currency: $currency,
            metadata: $request->metadata,
            raw: $session,
            redirectUrl: $session['url'] ?? null,
        );
    }

    /**
     * Retrieve a Checkout Session and translate it to a result. This is what the
     * success-redirect handler uses to confirm and activate a Stripe payment.
     */
    public function verifyPayment(string $reference): PaymentResult
    {
        $secret = $this->secretKey();
        if (empty($secret)) {
            throw (new GatewayUnavailableException('Stripe API key is not configured'))->setGateway($this->name());
        }

        try {
            Stripe::setApiKey($secret);
            $session = StripeSession::retrieve($reference);
        } catch (\Throwable $e) {
            $this->logError('Verification Failed', ['error' => $e->getMessage()]);
            throw (new PaymentVerificationException('Stripe verification failed: ' . $e->getMessage()))->setGateway($this->name());
        }

        return new PaymentResult(
            gateway: $this->name(),
            status: $session->payment_status === 'paid' ? PaymentResult::STATUS_SUCCEED : PaymentResult::STATUS_PENDING,
            transactionId: $session->id,
            reference: $session->payment_intent,
            amount: ((float) $session->amount_total) / 100,
            currency: $session->currency ? strtoupper($session->currency) : $this->currency(),
            metadata: isset($session->metadata) ? (array) $session->metadata : [],
            raw: $session->toArray(),
        );
    }

    public function handleWebhook(Request $request): PaymentResult
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature') ?? ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? null);
        $endpointSecret = $this->webhookSecret();

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $signature, $endpointSecret);
        } catch (\UnexpectedValueException $e) {
            $this->logError('Webhook payload mismatch');
            throw (new PaymentException('Invalid payload'))->setGateway($this->name());
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            $this->logError('Signature Verification Failed');
            throw (new InvalidWebhookSignatureException('Stripe webhook signature verification failed'))->setGateway($this->name());
        }

        $this->log('Webhook Received', ['event' => $event->type]);

        $object = $event->data->object;

        $status = match ($event->type) {
            'payment_intent.succeeded', 'charge.succeeded' => PaymentResult::STATUS_SUCCEED,
            'payment_intent.payment_failed'                => PaymentResult::STATUS_FAILED,
            default                                        => PaymentResult::STATUS_PENDING,
        };

        return new PaymentResult(
            gateway: $this->name(),
            status: $status,
            reference: $object->id ?? null,
            amount: isset($object->amount) ? ((float) $object->amount) / 100 : 0.0,
            currency: isset($object->currency) ? strtoupper($object->currency) : $this->currency(),
            metadata: [],
            raw: json_decode(json_encode($event), true),
            event: $event->type,
        );
    }
}
