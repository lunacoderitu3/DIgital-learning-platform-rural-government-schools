<?php

namespace App\Payments\Exceptions;

/**
 * Thrown when a payment/webhook has already been processed — the guard that
 * keeps webhook handling idempotent against duplicate deliveries.
 */
class PaymentAlreadyProcessedException extends PaymentException
{
}
