<?php

return [
    /*
    |--------------------------------------------------------------------------
    | DummyJSON source API
    |--------------------------------------------------------------------------
    |
    | Endpoint the products:seed command pulls smartphones from.
    |
    */
    'dummyjson_url' => env(
        'DUMMYJSON_URL',
        'https://dummyjson.com/products/category/smartphones'
    ),
    'dummyjson_timeout' => (int) env('DUMMYJSON_TIMEOUT', 15),

    /*
    |--------------------------------------------------------------------------
    | NBU exchange rate API
    |--------------------------------------------------------------------------
    |
    | National Bank of Ukraine daily rates. Returns the UAH value of one unit
    | of the requested currency. Rates are cached until the end of the day.
    |
    */
    'nbu_url' => env(
        'NBU_URL',
        'https://bank.gov.ua/NBUStatService/v1/statdirectory/exchange'
    ),
    'nbu_timeout' => (int) env('NBU_TIMEOUT', 10),
];
