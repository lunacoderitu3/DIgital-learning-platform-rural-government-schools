<?php

namespace App\Payments\DTO;

/**
 * Gateway-agnostic description of a payment to be created.
 *
 * The business layer (SubscriptionService) builds this object and hands it to
 * a {@see \App\Payments\Contracts\PaymentGateway}. The gateway only maps these
 * fields onto its provider API — it never contains subscription/billing rules.
 */
class PaymentRequest
{
    /**
     * @param float       $amount         Amount in major currency units (e.g. 49.99).
     * @param string      $currency       ISO currency code (e.g. "USD", "NGN").
     * @param string      $productName    Human readable line-item name shown on the gateway.
     * @param string|null $customerEmail  Payer email (required by most gateways).
     * @param string|null $customerName   Payer full name.
     * @param string|null $customerMobile Payer mobile number.
     * @param int|null    $schoolId       Owning school id (null for super-admin context).
     * @param int|null    $userId         Initiating user id.
     * @param string|null $reference      Pre-generated client reference / tx_ref (optional).
     * @param array       $metadata       Opaque key/value bag echoed back by the gateway webhook.
     * @param string|null $successUrl     Redirect URL on success.
     * @param string|null $cancelUrl      Redirect URL on cancel.
     * @param string|null $callbackUrl    Server-to-server / post-redirect callback URL.
     */
    public function __construct(
        public float $amount,
        public string $currency,
        public string $productName = 'Subscription',
        public ?string $customerEmail = null,
        public ?string $customerName = null,
        public ?string $customerMobile = null,
        public ?int $schoolId = null,
        public ?int $userId = null,
        public ?string $reference = null,
        public array $metadata = [],
        public ?string $successUrl = null,
        public ?string $cancelUrl = null,
        public ?string $callbackUrl = null,
    ) {
    }

    /**
     * Build from an associative array (keys mirror the constructor parameters).
     */
    public static function make(array $data): self
    {
        return new self(
            amount: (float) ($data['amount'] ?? 0),
            currency: $data['currency'] ?? 'USD',
            productName: $data['productName'] ?? $data['name'] ?? 'Subscription',
            customerEmail: $data['customerEmail'] ?? $data['email'] ?? null,
            customerName: $data['customerName'] ?? null,
            customerMobile: $data['customerMobile'] ?? null,
            schoolId: isset($data['schoolId']) ? (int) $data['schoolId'] : (isset($data['school_id']) ? (int) $data['school_id'] : null),
            userId: isset($data['userId']) ? (int) $data['userId'] : (isset($data['user_id']) ? (int) $data['user_id'] : null),
            reference: $data['reference'] ?? null,
            metadata: $data['metadata'] ?? [],
            successUrl: $data['successUrl'] ?? null,
            cancelUrl: $data['cancelUrl'] ?? null,
            callbackUrl: $data['callbackUrl'] ?? null,
        );
    }
}
