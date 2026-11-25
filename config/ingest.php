<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Machine ingest (substitui recepcao.php legado)
    |--------------------------------------------------------------------------
    |
    | Pelo menos um: INGEST_TOKEN (header X-Ingest-Token) ou INGEST_HMAC_SECRET
    | (header X-Ingest-Signature = hash hexadecimal minúsculo de SHA-256 do body).
    | Se ambos vazios, POST /api/v1/ingest/xml retorna 503.
    |
    */

    'token' => env('INGEST_TOKEN'),

    'hmac_secret' => env('INGEST_HMAC_SECRET'),

    'rate_limit_per_minute' => (int) env('INGEST_RATE_LIMIT', 120),

];
