<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dashboard stats cache TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | Document and SPED reconciliation counts are expensive on large tables.
    | Set to 0 to disable caching (fine for local SQLite tests; slow in production).
    |
    */
    'stats_cache_ttl' => (int) env('DASHBOARD_STATS_CACHE_TTL', 180),

    /*
    |--------------------------------------------------------------------------
    | Default & maximum NF issue date range (data_nf)
    |--------------------------------------------------------------------------
    |
    | The dashboard counts only documents with xml.data_nf inside [from, to] (inclusive).
    |
    */
    'default_period_days' => (int) env('DASHBOARD_DEFAULT_PERIOD_DAYS', 7),

    'max_period_days' => (int) env('DASHBOARD_MAX_PERIOD_DAYS', 366),

];
