# Docker (desenvolvimento e testes)

Stack: **PHP 8.4-FPM** + **Nginx**, **MySQL 8**, **Redis**. O `docker-compose.yml` injeta `DB_*` a partir do seu `.env`, com padrão `DB_HOST=mysql` e `DB_PORT=3306` (rede interna do Compose). Redis continua fixo como `redis` nos serviços PHP. Use um `.env` alinhado a `docker/env.docker.example`. Para apontar para um MySQL remoto, defina `DB_HOST`, `DB_PORT`, etc. no `.env` — valores não são mais sobrescritos por `mysql` fixo.

## Primeira subida

```bash
cp docker/env.docker.example .env
php artisan key:generate --show
# Cole APP_KEY em .env ou rode na pasta (com PHP local):
# php artisan key:generate

docker compose build
docker compose up -d

docker compose exec app composer install
docker compose exec app php artisan migrate --seed

# Permissões de escrita (Linux; no macOS costuma funcionar sem isto):
# docker compose exec -u root app chown -R www-data:www-data storage bootstrap/cache
```

Abra **http://localhost:8080** (porta mapeada por `APP_PORT`, default `8080`). MySQL na máquina host: **127.0.0.1:33060** (usuário/senha como em `.env`).

## Banco legado (`usuario`, `empresa`, `xml`, …)

O app usa as tabelas legadas (auth em `usuario`, sem `users`). Com `DB_*` apontando para o banco certo, rode migrações quando precisar alinhar schema auxiliar:

```bash
docker compose exec app php artisan migrate
```

Para validar conexão e existência de `usuario`: `docker compose exec app php artisan db:verify`.

## Assets (Vite)

O container PHP **não** roda o Vite. Opções:

- No host: `npm ci && npm run dev` (HMR) ou `npm run build` para produção local.
- One-shot Node:  
  `docker run --rm -v "$(pwd)":/app -w /app node:22-bookworm npm ci && npm run build`

## Testes (Pest)

Os testes usam **SQLite em memória** via `phpunit.xml`, independentemente do MySQL do Compose:

```bash
docker compose exec app php artisan test
# ou
docker compose exec app ./vendor/bin/pest
```

Com dependências ainda não instaladas:

```bash
docker compose run --rm app composer install
docker compose run --rm app php artisan test
```

## Fila (opcional)

```bash
docker compose --profile worker up -d
```

## Referência da API

Especificação OpenAPI: `docs/api/openapi.yaml` (v1 documentada; mantenha ao alterar rotas).
