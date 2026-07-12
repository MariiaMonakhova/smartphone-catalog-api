<?php

namespace App\Contracts;

use App\Exceptions\ExchangeRateUnavailableException;

/**
 * Supplies the UAH value of one unit of a given currency.
 *
 * CurrencyConverter depends on this abstraction rather than a concrete
 * provider (e.g. NbuExchangeRateService), so it can be swapped for a
 * different source — or a test fake — without any changes to the converter.
 */
interface ExchangeRateProvider
{
    /**
     * Return the UAH value of one unit of the given currency.
     *
     * @throws ExchangeRateUnavailableException if no rate can be obtained.
     */
    public function rate(string $currency): float;
}
