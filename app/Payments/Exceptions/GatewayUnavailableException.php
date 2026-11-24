<?php

namespace App\Payments\Exceptions;

/**
 * Thrown when a gateway exists but cannot be used right now — disabled in
 * configuration, missing API keys, or the provider is unreachable.
 */
class GatewayUnavailableException extends PaymentException
{
}
