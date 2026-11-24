<?php

namespace App\Payments\DTO;

/**
 * Standardized result returned by every payment gateway.
 *
 * Whether produced by createPayment(), verifyPayment() or handleWebhook(), the
 * rest of the application consumes this single shape and never needs to know
 * which gateway produced it.
 *
 * Status vocabulary deliberately matches the values already stored in
 * payment_transactions.payment_status ("succeed" / "pending" / "failed") so the
 * DTO drops straight into the existing schema with no translation layer.
 */
class PaymentResult
{
    public const STATUS_SUCCEED = 'succeed';
    public const STATUS_PENDING = 'pending';
    public const STATUS_FAILED  = 'failed';

    /**
     * @param string      $gateway       Canonical gateway label (e.g. "Paystack").
     * @param string      $status        One of the STATUS_* constants.
     * @param string|null $transactionId Provider payment/charge id (maps to payment_id).
     * @param string|null $reference     Order/tx reference (maps to order_id).
     * @param float       $amount        Amount in major currency units.
     * @param string|null $currency      ISO currency code.
     * @param array       $metadata      Decoded metadata echoed back by the gateway.
     * @param array       $raw           Raw decoded gateway payload (stored for audit).
     * @param string|null $event         Webhook event type, when applicable.
     * @param string|null $redirectUrl   Where to send the user (createPayment only).
     * @param array       $extra         Gateway-specific extras (e.g. Razorpay order).
     */
    public function __construct(
        public string $gateway,
        public string $status = self::STATUS_PENDING,
        public ?string $transactionId = null,
        public ?string $reference = null,
        public float $amount = 0.0,
        public ?string $currency = null,
        public array $metadata = [],
        public array $raw = [],
        public ?string $event = null,
        public ?string $redirectUrl = null,
        public array $extra = [],
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCEED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Read a single key out of the gateway metadata bag with a fallback.
     */
    public function meta(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    public function toArray(): array
    {
        return [
            'gateway'        => $this->gateway,
            'status'         => $this->status,
            'transaction_id' => $this->transactionId,
            'reference'      => $this->reference,
            'amount'         => $this->amount,
            'currency'       => $this->currency,
            'event'          => $this->event,
            'metadata'       => $this->metadata,
        ];
    }
}
