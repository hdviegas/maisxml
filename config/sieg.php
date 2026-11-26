<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SIEG API (Cofre SIEG) — download automatizado de XMLs
    |--------------------------------------------------------------------------
    |
    | Documentação: https://integracoes.sieg.com/clientes-sieg/docs
    | Swagger: https://api.sieg.com/swagger/docs/docs-fiscais
    | Desenho da integração: docs/SIEG_INTEGRACAO.md
    |
    | A API Key de cada cliente fica criptografada em `sieg_accounts.api_key`
    | (comando `php artisan sieg:account`), nunca no .env nem no código.
    |
    */

    'base_url' => env('SIEG_BASE_URL', 'https://api.sieg.com'),

    // Timeout máximo por requisição. Com prazo de execução (cron), é reduzido para o tempo
    // restante + 3 s; sem prazo (diagnóstico) vale este valor.
    'timeout' => (int) env('SIEG_TIMEOUT', 60),

    'connect_timeout' => (int) env('SIEG_CONNECT_TIMEOUT', 10),

    /*
    | JWT de cliente direto (`X-Access-Mode: direct`) vale 24h; renovamos antes.
    */
    'jwt_ttl_minutes' => (int) env('SIEG_JWT_TTL_MINUTES', 23 * 60),

    /*
    | Limites por API Key e por endpoint (requisições por minuto).
    | Documentados: baixar-xmls 2/min, contar-xmls 5/min, baixar-xml/nsu 3000/min.
    | Os demais não são documentados: valores conservadores.
    */
    'rate_limits' => [
        'create-jwt' => (int) env('SIEG_RL_CREATE_JWT', 10),
        'consultar-nsu' => (int) env('SIEG_RL_CONSULTAR_NSU', 30),
        'baixar-xml-nsu-lote' => (int) env('SIEG_RL_NSU_LOTE', 60),
        'baixar-xmls' => (int) env('SIEG_RL_BAIXAR_XMLS', 2),
        'contar-xmls' => (int) env('SIEG_RL_CONTAR_XMLS', 5),
        'baixar-eventos' => (int) env('SIEG_RL_BAIXAR_EVENTOS', 2),
    ],

    /*
    | Usamos só esta fração do limite (0.9 = 90%) para ter folga.
    */
    'rate_limit_safety' => (float) env('SIEG_RL_SAFETY', 0.9),

    /*
    | 429 / 5xx / timeout: backoff exponencial com jitter (segundos).
    | O header Retry-After, quando presente, tem prioridade.
    */
    'backoff' => [
        'base_seconds' => (int) env('SIEG_BACKOFF_BASE', 60),
        'max_seconds' => (int) env('SIEG_BACKOFF_MAX', 900),
        'max_retries' => (int) env('SIEG_MAX_RETRIES', 3),
    ],

    'sync' => [
        // Tempo máximo de uma execução de `sieg:sync` (cabe no intervalo do cron).
        'max_runtime_seconds' => (int) env('SIEG_SYNC_MAX_RUNTIME', 240),

        // Itens por página em baixar-xmls (máx. 50) e NSUs por lote (máx. 50).
        'page_size' => 50,
        'nsu_batch' => 50,

        // Modo NSU: por quantos segundos reaproveitar a faixa (primeiro/último) de consultar-nsu.
        // Enquanto o cursor não alcança o último NSU em cache, a consulta é pulada; ao alcançar
        // (ou expirar), consulta de novo. 0 desativa o cache.
        'nsu_range_cache_seconds' => (int) env('SIEG_NSU_RANGE_CACHE_SECONDS', 300),

        // Tipos baixados: 1 = NF-e, 4 = NFC-e (o parser atual só entende estes).
        'tipos_xml' => [1, 4],

        // Modo "periodo": 'account' consulta a conta SIEG inteira por tipo (2 cursores,
        // ~1 requisição por tipo e rodada); 'branch' filtra por CNPJ de filial × papel
        // (dest/emit) × tipo — use só se a API exigir filtro de CNPJ (muito mais lento a 2/min).
        'periodo_scope' => env('SIEG_PERIODO_SCOPE', 'account'),

        // Modo "periodo": janela por data de upload no Cofre e sobreposição
        // para pegar uploads atrasados (duplicatas são ignoradas na importação).
        'window_hours' => (int) env('SIEG_SYNC_WINDOW_HOURS', 24),
        // Mantenha pequeno: a 2 req/min (50 XMLs cada), revarrer 48h de NFC-e de varejo
        // a cada rodada não cabe no limite. O modo NSU não usa sobreposição.
        'overlap_hours' => (int) env('SIEG_SYNC_OVERLAP_HOURS', 2),
        'initial_lookback_days' => (int) env('SIEG_SYNC_INITIAL_LOOKBACK_DAYS', 5),

        // Após N execuções seguidas com falha, a conta é pausada (minutos).
        'pause_after_failures' => 5,
        'pause_minutes' => 60,
    ],

    /*
    | Endpoint HTTP de sincronização (/api/cron/sieg-sync) para hospedagem sem PHP CLI,
    | chamado por um serviço externo de cron (ex.: cron-job.org, timeout de 30 s no plano
    | gratuito). Token vazio desativa o endpoint (responde 404). Cada chamada roda no máximo
    | `max_runtime_seconds`; a próxima retoma de onde parou.
    */
    'cron' => [
        'token' => env('SIEG_CRON_TOKEN'),
        'max_runtime_seconds' => (int) env('SIEG_CRON_MAX_RUNTIME', 25),
    ],

    'schedule' => [
        'enabled' => (bool) env('SIEG_SCHEDULE_ENABLED', false),
        // Expressão cron do agendamento de `sieg:sync`.
        'cron' => env('SIEG_SCHEDULE_CRON', '*/10 * * * *'),
    ],

];
