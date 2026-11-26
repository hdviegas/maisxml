-- Mais XML — migrations do SIEG para rodar manualmente (MySQL 5.7, phpMyAdmin ou cliente mysql).
-- Equivale a `php artisan migrate` das 6 migrations pendentes em 2026-11-26.
-- Só cria tabelas novas; não altera nenhuma tabela do sistema antigo.
-- A migration 2026_05_12_000000_create_usuario_table_if_missing não cria nada
-- (a tabela `usuario` já existe); ela só é registrada no fim, como as demais.
-- A coluna `window_end` (migration 2026_11_25_100020) já vem no CREATE de `sieg_sync_cursors`.

CREATE TABLE IF NOT EXISTS `sieg_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `organization_id` bigint unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `api_key` text NOT NULL,
  `mode` varchar(16) NOT NULL DEFAULT 'nsu',
  `last_nsu` bigint unsigned NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `paused_until` timestamp NULL,
  `consecutive_failures` int unsigned NOT NULL DEFAULT '0',
  `last_run_at` timestamp NULL,
  `last_success_at` timestamp NULL,
  `last_error` text NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  UNIQUE KEY `sieg_accounts_organization_id_name_unique` (`organization_id`, `name`),
  KEY `sieg_accounts_organization_id_index` (`organization_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sieg_sync_cursors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `sieg_account_id` bigint unsigned NOT NULL,
  `cnpj` varchar(14) NOT NULL,
  `role` varchar(4) NOT NULL,
  `tipo_xml` tinyint unsigned NOT NULL,
  `window_start` datetime NULL,
  `window_end` datetime NULL,
  `skip` int unsigned NOT NULL DEFAULT '0',
  `synced_until` datetime NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  UNIQUE KEY `sieg_cursor_unique` (`sieg_account_id`, `cnpj`, `role`, `tipo_xml`),
  KEY `sieg_sync_cursors_sieg_account_id_index` (`sieg_account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sieg_sync_runs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `sieg_account_id` bigint unsigned NOT NULL,
  `mode` varchar(16) NOT NULL,
  `status` varchar(16) NOT NULL,
  `started_at` datetime NULL,
  `finished_at` datetime NULL,
  `requests` int unsigned NOT NULL DEFAULT '0',
  `downloaded` int unsigned NOT NULL DEFAULT '0',
  `imported` int unsigned NOT NULL DEFAULT '0',
  `duplicates` int unsigned NOT NULL DEFAULT '0',
  `ignored` int unsigned NOT NULL DEFAULT '0',
  `events` int unsigned NOT NULL DEFAULT '0',
  `errors` int unsigned NOT NULL DEFAULT '0',
  `from_nsu` bigint unsigned NULL,
  `to_nsu` bigint unsigned NULL,
  `message` text NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  KEY `sieg_sync_runs_sieg_account_id_index` (`sieg_account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fiscal_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `organization_id` bigint unsigned NULL,
  `chave` varchar(44) NOT NULL,
  `tp_evento` int unsigned NOT NULL,
  `n_seq_evento` smallint unsigned NOT NULL DEFAULT '1',
  `dh_evento` datetime NULL,
  `c_stat` varchar(4) NULL,
  `protocolo` varchar(20) NULL,
  `descricao` varchar(120) NULL,
  `x_correcao` text NULL,
  `storage_path` varchar(255) NOT NULL,
  `source` varchar(16) NOT NULL DEFAULT 'sieg',
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  UNIQUE KEY `fiscal_events_chave_tp_evento_n_seq_evento_unique` (`chave`, `tp_evento`, `n_seq_evento`),
  KEY `fiscal_events_organization_id_index` (`organization_id`),
  KEY `fiscal_events_chave_index` (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registra as 6 migrations como rodadas (mesmo lote), para o Laravel não tentar de novo.
SET @batch := (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`);
INSERT INTO `migrations` (`migration`, `batch`)
SELECT m.`migration`, @batch FROM (
  SELECT '2026_05_12_000000_create_usuario_table_if_missing' AS `migration`
  UNION ALL SELECT '2026_11_25_100000_create_sieg_accounts_table'
  UNION ALL SELECT '2026_11_25_100001_create_sieg_sync_cursors_table'
  UNION ALL SELECT '2026_11_25_100002_create_sieg_sync_runs_table'
  UNION ALL SELECT '2026_11_25_100010_create_fiscal_events_table'
  UNION ALL SELECT '2026_11_25_100020_add_window_end_to_sieg_sync_cursors'
) AS m
WHERE NOT EXISTS (SELECT 1 FROM `migrations` x WHERE x.`migration` = m.`migration`);

-- Conferência: deve listar as 4 tabelas e as 6 migrations.
SHOW TABLES LIKE 'sieg_%';
SHOW TABLES LIKE 'fiscal_events';
SELECT `migration`, `batch` FROM `migrations` WHERE `migration` LIKE '2026_05_12%' OR `migration` LIKE '2026_11_25%';
