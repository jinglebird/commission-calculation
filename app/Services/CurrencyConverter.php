<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CurrencyConverter
{
    public const API_URL = 'https://developers.paysera.com/tasks/api/currency-exchange-rates';
    public const CACHE_TIME = 3600; // Cache for 1 hour

    /**
     * @throws Exception
     */
    public function getConversionRate($fromCurrency, $toCurrency = 'EUR')
    {
        $cacheKey = "currency_rate_{$fromCurrency}_to_{$toCurrency}";

        // If the rate is cached, return it
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Otherwise, fetch the rates
        $response = Http::get(self::API_URL);
        if ($response->failed()) {
            throw new Exception('Failed to fetch exchange rates.');
        }

        $rates = $response->json();

        $conversionRate = 1;
        if (isset($rates['rates'])) {
            $conversionRate = $toCurrency === 'EUR'
                ? $rates['rates'][$fromCurrency]
                : $rates['rates'][$fromCurrency] / $rates['rates'][$toCurrency];
        }

        // Cache the conversion rate
        Cache::put($cacheKey, $conversionRate, self::CACHE_TIME);

        return $conversionRate;
    }
}
