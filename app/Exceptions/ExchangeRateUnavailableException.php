<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a currency conversion is requested but the NBU rate cannot be
 * obtained — neither freshly nor from the stale "last known good" cache.
 *
 * The API layer translates this into a 503 Service Unavailable response so the
 * client knows the failure is transient and upstream, not a bad request.
 */
class ExchangeRateUnavailableException extends RuntimeException
{
}
