<?php

use App\Services\CurrencyConverter;
use App\Services\NbuExchangeRateService;

/**
 * A stub rate service so the converter can be tested in isolation — no HTTP,
 * no cache, no Laravel app. Returns preset UAH-per-unit rates, and fails loudly
 * if a rate is requested that the test didn't expect.
 *
 * @param  array<string, float>  $rates
 */
function stubRates(array $rates): NbuExchangeRateService
{
    return new class($rates) extends NbuExchangeRateService
    {
        /** @param array<string, float> $rates */
        public function __construct(private array $rates) {}

        public function rate(string $currency): float
        {
            $currency = strtoupper($currency);

            return $this->rates[$currency]
                ?? throw new RuntimeException("Unexpected rate lookup for {$currency}");
        }
    };
}

it('returns the amount unchanged for USD without looking up any rate', function () {
    // No rates provided: if convert() touches the rate service, stubRates throws.
    $converter = new CurrencyConverter(stubRates([]));

    expect($converter->convert(199.99, 'USD'))->toBe(199.99);
});

it('converts USD to UAH using the direct rate', function () {
    $converter = new CurrencyConverter(stubRates(['USD' => 44.4950]));

    expect($converter->convert(100.0, 'UAH'))->toBe(round(100 * 44.4950, 2));
});

it('converts USD to EUR using the UAH cross-rate', function () {
    $converter = new CurrencyConverter(stubRates(['USD' => 44.4950, 'EUR' => 50.8600]));

    expect($converter->convert(100.0, 'EUR'))->toBe(round(100 * 44.4950 / 50.8600, 2));
});

it('is case-insensitive about the target currency', function () {
    $converter = new CurrencyConverter(stubRates(['USD' => 44.4950]));

    expect($converter->convert(10.0, 'uah'))->toBe(round(10 * 44.4950, 2));
});

it('throws on an unsupported currency', function () {
    (new CurrencyConverter(stubRates([])))->convert(100.0, 'GBP');
})->throws(InvalidArgumentException::class);

it('reports which currencies are supported', function () {
    expect(CurrencyConverter::supports('usd'))->toBeTrue()
        ->and(CurrencyConverter::supports('EUR'))->toBeTrue()
        ->and(CurrencyConverter::supports('uah'))->toBeTrue()
        ->and(CurrencyConverter::supports('gbp'))->toBeFalse();
});
