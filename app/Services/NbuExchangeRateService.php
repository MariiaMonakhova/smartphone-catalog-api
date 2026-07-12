<?php

namespace App\Services;

use App\Contracts\ExchangeRateProvider;
use App\Exceptions\ExchangeRateUnavailableException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Fetches daily currency rates from the National Bank of Ukraine.
 *
 * The NBU API returns the UAH value of one unit of a given currency
 * (e.g. USD -> 44.4950 means 1 USD = 44.4950 UAH).
 *
 * Caching strategy:
 *  - Rates are memoised until the end of the current day, so at most one
 *    request per currency per day hits the NBU regardless of API traffic.
 *  - On every successful fetch we also persist a non-expiring "last known
 *    good" copy. If the NBU is unreachable when the daily cache is cold, we
 *    serve that stale rate rather than failing outright.
 *  - If neither a fresh nor a stale rate is available, an
 *    ExchangeRateUnavailableException is thrown (surfaced as HTTP 503).
 */
class NbuExchangeRateService implements ExchangeRateProvider
{
    /**
     * Return the UAH value of one unit of the given currency.
     */
    public function rate(string $currency): float
    {
        $currency = strtoupper($currency);

        // 1 UAH is trivially 1 UAH — no upstream call needed.
        if ($currency === 'UAH') {
            return 1.0;
        }

        $cacheKey = $this->dailyKey($currency);

        if (($cached = Cache::get($cacheKey)) !== null) {
            return (float) $cached;
        }

        try {
            $rate = $this->fetchRate($currency);
        } catch (Throwable $e) {
            // NBU unreachable — fall back to the last rate we successfully saw.
            $stale = Cache::get($this->lastGoodKey($currency));

            if ($stale !== null) {
                return (float) $stale;
            }

            throw new ExchangeRateUnavailableException(
                "Unable to obtain the {$currency} exchange rate from NBU and no cached rate is available.",
                previous: $e,
            );
        }

        Cache::put($cacheKey, $rate, now()->endOfDay());
        Cache::forever($this->lastGoodKey($currency), $rate);

        return $rate;
    }

    /**
     * Perform the actual HTTP call to the NBU for a single currency.
     */
    protected function fetchRate(string $currency): float
    {
        $response = Http::timeout((int) config('catalog.nbu_timeout', 10))
            ->get((string) config('catalog.nbu_url'), [
                'format' => 'json',
                'valcode' => $currency,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("NBU responded with HTTP {$response->status()}.");
        }

        $data = $response->json();

        if (! is_array($data) || ! isset($data[0]['rate'])) {
            throw new RuntimeException("Unexpected NBU response for {$currency}.");
        }

        return (float) $data[0]['rate'];
    }

    protected function dailyKey(string $currency): string
    {
        return "nbu:rate:{$currency}";
    }

    protected function lastGoodKey(string $currency): string
    {
        return "nbu:rate:{$currency}:last_good";
    }
}
