<?php

namespace App\Payments\Exceptions;

/**
 * Thrown when an inbound webhook fails signature verification and must be
 * rejected with HTTP 400.
 */
class InvalidWebhookSignatureException extends PaymentException
{
}
