# Mais XML

Repository for the **Mais XML** remake: **Laravel 13**, **Blade** + controllers (Breeze-style auth, no Livewire), **PHP 8.4**, **Vite**. Legacy PHP app stays local only in **`maisxml_old/`** (ignored by Git).

## Documentation

| File | Purpose |
|------|---------|
| `docs/PENDENCIAS_FINALIZACAO.md` | Pendências para finalizar e publicar na Locaweb (decisões, P0/P1/P2, cutover) |
| `docs/MEMORY_LEGACY_MAISXML.md` | Legacy feature baseline |
| `docs/FEATURE_SUGGESTIONS.md` | Product/tech enhancements |
| `docs/DEVELOPMENT_PLAN.md` | Roadmap, use cases, UX (pt-BR UI) |
| `docs/DOCKER.md` | MySQL + Redis + Nginx via Docker Compose; testes no container |
| `docs/api/openapi.yaml` | Contrato OpenAPI da API v1 (Sanctum + ingest) |

## Docker (MySQL + Redis + Nginx)

```bash
cp docker/env.docker.example .env
docker compose build && docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
# Vite: no host — `npm ci && npm run dev` — ou ver docs/DOCKER.md
```

**Testes no container:** `docker compose exec app php artisan test` — Guia: [docs/DOCKER.md](docs/DOCKER.md).

## Requirements (sem Docker)

- PHP 8.4+, Composer, Node 20+ (ou 22), SQLite *ou* MySQL 8

## Quick start (nativo)

```bash
cp .env.example .env
php artisan key:generate
# Padrão: SQLite; para MySQL, ajuste DB_* e crie o banco

php artisan migrate --seed
npm ci && npm run dev
```

Em outro terminal:

```bash
php artisan serve
```

- **Login seed:** `teste@example.com` — senha padrão da factory (`password`); veja `DatabaseSeeder`.

## Scripts

| Command | Description |
|---------|-------------|
| `php artisan test` | Pest test suite |
| `npm run build` | Production assets |
| `./vendor/bin/pint` | Fix PHP style (optional) |
| `php artisan fiscal:backfill-operations` | Recalcula `operation_type` por CFOP |

## CI

GitHub Actions (`.github/workflows/tests.yml`) runs tests, migrations on MySQL 8, and `npm run build` on push/PR to `main`.

## Phase 1 (current slice)

- **Fiscal documents:** `fiscal_documents` + NF-e XML parser (`App\Services\Nfe\*`), storage under `storage/app/xml/{organization_id}/{Y-m}/{chave}.xml`. Campo **`operation_type`** (1–5, legado `operacao`) a partir do **CFOP** (`config/cfop.php`); `php artisan fiscal:backfill-operations` recalcula.
- **Web:** `/documentos` — filtros, **SPED** + **Conferência**, export **CSV**/**PDF**, **ZIP**, **ver XML**, **DANFE/DANFC-e (PDF)** ([sped-da](https://github.com/nfephp-org/sped-da)), **abrir por chave** (`GET /documentos/por-chave/{44}` → lista filtrada), **exclusão** (`DELETE`) do registro + XML; `/importar`; `/sped`; **/configurações** (organizações e filiais); **/manifestação** (escopo + planejamento SEFAZ).
- **Relatórios:** `/relatorios` — exportações + **log de atividade** (filtros registrados na exportação).
- **API:** `docs/api/openapi.yaml` — Sanctum + ingest; rotas: `POST/GET /api/v1/documents`, `GET /api/v1/documents/{id}`, `GET /api/v1/documents/{id}/danfe`, `POST /api/v1/ingest/xml` (body XML; `X-Branch-Id`, `X-Ingest-Token` ou HMAC, `X-On-Duplicate`).

Create token (tinker exemplo):

```php
$user = App\Models\User::first();
$user->createToken('integração')->plainTextToken;
```

Exemplo curl ingest:

```bash
curl -X POST "$APP_URL/api/v1/ingest/xml" \
  -H "X-Ingest-Token: $INGEST_TOKEN" \
  -H "X-Branch-Id: 1" \
  -H "Content-Type: application/xml" \
  --data-binary @nfe.xml
```

## Security

Never commit **`maisxml_old/`**, real `.env`, or certificates. Rotate anything that leaked.

## Git remote

[github.com/hdviegas/maisxml](https://github.com/hdviegas/maisxml)
