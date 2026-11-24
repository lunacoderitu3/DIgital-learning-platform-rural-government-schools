<?php

namespace App\Payments\Gateways;

use App\Models\PaymentConfiguration;
use App\Payments\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Log;

/**
 * Shared plumbing for concrete gateways: holds the resolved
 * {@see PaymentConfiguration}, exposes currency/credential helpers, the
 * Stripe-style minimum-amount table, and a namespaced logger.
 *
 * Concrete gateways implement only the provider-specific request/response
 * mapping declared by {@see PaymentGateway}.
 */
abstract class AbstractPaymentGateway implements PaymentGateway
{
    public function __construct(protected PaymentConfiguration $config)
    {
    }

    public function config(): PaymentConfiguration
    {
        return $this->config;
    }

    protected function currency(): string
    {
        return $this->config->currency_code;
    }

    protected function secretKey(): ?string
    {
        return $this->config->secret_key ?? null;
    }

    protected function apiKey(): ?string
    {
        return $this->config->api_key ?? null;
    }

    protected function webhookSecret(): ?string
    {
        return $this->config->webhook_secret_key ?? null;
    }

    /**
     * Emit a structured, gateway-tagged log line. Centralizing this gives the
     * uniform payment logging the refactor calls for (Payment Created, Webhook
     * Received, Verification Failed, ...).
     */
    protected function log(string $message, array $context = []): void
    {
        Log::info('[Payment][' . $this->name() . '] ' . $message, $context);
    }

    protected function logError(string $message, array $context = []): void
    {
        Log::error('[Payment][' . $this->name() . '] ' . $message, $context);
    }

    /**
     * Clamp an amount up to the gateway's minimum chargeable value for the
     * given currency. Mirrors the historical SubscriptionService::checkMinimumAmount
     * table so charged amounts are unchanged by the refactor.
     *
     * @param string     $currency Upper-cased ISO currency code.
     * @param string|int|float $amount
     */
    protected function checkMinimumAmount(string $currency, $amount): float
    {
        $currencies = [
            'USD' => 0.50, 'AED' => 2.00, 'AUD' => 0.50, 'BGN' => 1.00, 'BRL' => 0.50,
            'CAD' => 0.50, 'CHF' => 0.50, 'CZK' => 15.00, 'DKK' => 2.50, 'EUR' => 0.50,
            'GBP' => 0.30, 'HKD' => 4.00, 'HUF' => 175.00, 'INR' => 0.50, 'JPY' => 50,
            'MXN' => 10, 'MYR' => 2.00, 'NOK' => 3.00, 'NZD' => 0.50, 'PLN' => 2.00,
            'RON' => 2.00, 'SEK' => 3.00, 'SGD' => 0.50, 'THB' => 10, 'ZAR' => 10,
        ];

        if ($amount != 0) {
            if (array_key_exists($currency, $currencies)) {
                return $currencies[$currency] >= $amount ? (float) $currencies[$currency] : (float) $amount;
            }
            return (float) $amount;
        }

        return 0.0;
    }
}
