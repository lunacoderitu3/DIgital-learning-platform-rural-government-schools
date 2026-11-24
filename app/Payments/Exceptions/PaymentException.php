<?php

namespace App\Payments\Exceptions;

use Exception;

/**
 * Base class for all payment-layer exceptions.
 *
 * Every gateway error surfaces as one of these application-level exceptions so
 * callers can handle payment failures uniformly regardless of provider.
 */
class PaymentException extends Exception
{
    /** Optional gateway label for logging/diagnostics. */
    protected ?string $gateway = null;

    public function setGateway(?string $gateway): static
    {
        $this->gateway = $gateway;
        return $this;
    }

    public function getGateway(): ?string
    {
        return $this->gateway;
    }
}
