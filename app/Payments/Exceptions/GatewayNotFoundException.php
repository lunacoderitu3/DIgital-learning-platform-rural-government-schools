<?php

namespace App\Payments\Exceptions;

/**
 * Thrown when no gateway is registered for the requested payment method.
 */
class GatewayNotFoundException extends PaymentException
{
}
