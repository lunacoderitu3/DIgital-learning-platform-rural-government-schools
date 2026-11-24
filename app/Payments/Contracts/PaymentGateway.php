<?php

namespace App\Payments\Contracts;

use App\Payments\DTO\PaymentRequest;
use App\Payments\DTO\PaymentResult;
use Illuminate\Http\Request;

/**
 * Contract every payment gateway must implement.
 *
 * A gateway is responsible for exactly four things and nothing more:
 *   1. Create a payment session/order/reference for a {@see PaymentRequest}.
 *   2. Verify a payment by its reference.
 *   3. Parse + signature-verify an inbound webhook.
 *   4. Always answer with a standardized {@see PaymentResult}.
 *
 * Gateways MUST NOT touch subscriptions, bills, features or any business rule.
 * To add a new provider (e.g. PayPal): implement this interface and register
 * the class in {@see \App\Payments\PaymentGatewayFactory}. No controller or
 * subscription-logic change is required.
 */
interface PaymentGateway
{
    /**
     * Canonical, human-facing gateway label (e.g. "Paystack"). Stored on the
     * payment transaction as payment_gateway.
     */
    public function name(): string;

    /**
     * Create a payment and return a result carrying the redirect URL (or, for
     * client-side gateways, the order payload in {@see PaymentResult::$extra}).
     *
     * @throws \App\Payments\Exceptions\PaymentException
     */
    public function createPayment(PaymentRequest $request): PaymentResult;

    /**
     * Verify a payment by its gateway reference (order id / tx_ref / session).
     *
     * @throws \App\Payments\Exceptions\PaymentVerificationException
     */
    public function verifyPayment(string $reference): PaymentResult;

    /**
     * Parse and signature-verify an inbound webhook request, returning a
     * standardized result. Implementations throw on signature failure.
     *
     * @throws \App\Payments\Exceptions\InvalidWebhookSignatureException
     * @throws \App\Payments\Exceptions\PaymentException
     */
    public function handleWebhook(Request $request): PaymentResult;
}
