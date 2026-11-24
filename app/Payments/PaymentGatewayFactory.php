<?php

namespace App\Payments;

use App\Models\PaymentConfiguration;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Exceptions\GatewayNotFoundException;
use App\Payments\Exceptions\GatewayUnavailableException;

/**
 * Resolves a payment method name to a concrete {@see PaymentGateway} instance.
 *
 * This is the single place that knows the map of provider name -> class. Adding
 * a new provider means one line in {@see self::$gateways} (or a runtime
 * {@see self::register()} call) plus the gateway class — nothing else in the
 * codebase changes.
 *
 * Note: payment_method casing is inconsistent across the legacy data/code
 * ("stripe" vs "Stripe" vs "Paystack"), so all lookups are case-insensitive.
 */
class PaymentGatewayFactory
{
    /**
     * Map of lowercase method name => gateway class.
     *
     * Adding a provider (e.g. PayPal) is a single entry here — no controller or
     * subscription-logic change required.
     *
     * @var array<string, class-string<PaymentGateway>>
     */
    protected array $gateways = [
        'stripe'      => Gateways\StripeGateway::class,
        'razorpay'    => Gateways\RazorpayGateway::class,
        'flutterwave' => Gateways\FlutterwaveGateway::class,
        'paystack'    => Gateways\PaystackGateway::class,
    ];

    /**
     * Register or override a gateway at runtime (used by tests and future
     * providers such as PayPal).
     *
     * @param class-string<PaymentGateway> $class
     */
    public function register(string $method, string $class): void
    {
        $this->gateways[strtolower(trim($method))] = $class;
    }

    /**
     * Build the gateway for a given method name.
     *
     * @param  string                    $method  e.g. "stripe", "Paystack".
     * @param  PaymentConfiguration|null $config  Pre-resolved config; looked up when null.
     * @throws GatewayNotFoundException    When the method is not registered.
     * @throws GatewayUnavailableException When the gateway is disabled / unconfigured.
     */
    public function make(string $method, ?PaymentConfiguration $config = null): PaymentGateway
    {
        $key = strtolower(trim($method));

        if (!isset($this->gateways[$key])) {
            throw (new GatewayNotFoundException("No payment gateway registered for '{$method}'"))->setGateway($method);
        }

        $config ??= $this->configFor($method);

        if (!$config) {
            throw (new GatewayUnavailableException("Payment configuration not found for '{$method}'"))->setGateway($method);
        }

        if (!$config->status) {
            throw (new GatewayUnavailableException("Payment gateway '{$method}' is currently disabled"))->setGateway($method);
        }

        $class = $this->gateways[$key];

        return new $class($config);
    }

    /**
     * Resolve the gateway for the single active super-admin payment
     * configuration (school_id = null, status = 1) — the common entry point
     * when the caller does not yet know which provider is enabled.
     *
     * @throws GatewayUnavailableException
     * @throws GatewayNotFoundException
     */
    public function makeActive(): PaymentGateway
    {
        $config = PaymentConfiguration::on('mysql')
            ->whereNull('school_id')
            ->where('status', 1)
            ->first();

        if (!$config) {
            throw new GatewayUnavailableException('No active payment gateway is configured');
        }

        return $this->make($config->payment_method, $config);
    }

    /**
     * Whether a method name maps to a registered gateway.
     */
    public function supports(string $method): bool
    {
        return isset($this->gateways[strtolower(trim($method))]);
    }

    /**
     * Look up the super-admin payment configuration for a method (case-insensitive).
     */
    protected function configFor(string $method): ?PaymentConfiguration
    {
        return PaymentConfiguration::on('mysql')
            ->whereNull('school_id')
            ->whereRaw('LOWER(payment_method) = ?', [strtolower(trim($method))])
            ->where('status', 1)
            ->first();
    }
}
