<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Converts USD-denominated prices (how everything is stored locally) into the
 * currencies the API is allowed to serve.
 *
 * Conversion model (NBU gives UAH-per-unit rates):
 *  - USD -> USD : no conversion (stored currency, the default).
 *  - USD -> UAH : usd * rate(USD)                 — direct NBU rate.
 *  - USD -> EUR : usd * rate(USD) / rate(EUR)     — cross-rate through UAH,
 *                 because NBU only quotes against UAH.
 */
class CurrencyConverter
{
    /**
     * Currencies the API accepts for the ?currency= parameter.
     *
     * @var list<string>
     */
    public const array SUPPORTED = ['USD', 'EUR', 'UAH'];

    public function __construct(private readonly NbuExchangeRateService $rates)
    {
    }

    /**
     * Convert an amount stored in USD to the target currency.
     */
    public function convert(float $usd, string $to): float
    {
        $to = strtoupper($to);

        return match ($to) {
            'USD' => round($usd, 2),
            'UAH' => round($usd * $this->rates->rate('USD'), 2),
            'EUR' => round($usd * $this->rates->rate('USD') / $this->rates->rate('EUR'), 2),
            default => throw new InvalidArgumentException("Unsupported currency: {$to}"),
        };
    }

    /**
     * Whether the given currency code is supported.
     */
    public static function supports(string $currency): bool
    {
        return in_array(strtoupper($currency), self::SUPPORTED, true);
    }
}
