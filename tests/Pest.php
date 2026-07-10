<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * NBU test rates: UAH value of one unit of each currency.
 */
const NBU_USD_RATE = 44.4950;
const NBU_EUR_RATE = 50.8600;

/**
 * Fake the NBU exchange-rate API, returning the given UAH-per-unit rates.
 * Any other host reaching Http is left to a stray-request failure.
 */
function fakeNbuRates(float $usd = NBU_USD_RATE, float $eur = NBU_EUR_RATE): void
{
    Http::fake(function (HttpRequest $request) use ($usd, $eur) {
        if (! str_contains($request->url(), 'bank.gov.ua')) {
            return null;
        }

        $currency = str_contains($request->url(), 'valcode=EUR') ? 'EUR' : 'USD';

        return Http::response([[
            'r030' => $currency === 'EUR' ? 978 : 840,
            'txt' => $currency,
            'rate' => $currency === 'EUR' ? $eur : $usd,
            'cc' => $currency,
            'exchangedate' => '01.01.2026',
        ]]);
    });
}

/**
 * Fake the NBU API as unreachable (HTTP 500 on every rate request).
 */
function fakeNbuDown(): void
{
    Http::fake([
        'bank.gov.ua/*' => Http::response('Service Unavailable', 500),
    ]);
}
