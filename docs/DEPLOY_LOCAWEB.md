# Publicação na Locaweb (hospedagem compartilhada)

Guia para colocar o Mais XML novo (Laravel 13) no ar na mesma conta da Locaweb onde roda o sistema antigo.

**Layout escolhido:**

```
public_html/
├── admin/            ← o sistema novo inteiro (app, vendor, storage, public, .env, ...)
│   ├── .htaccess     ← bloqueia código e .env; envia tudo para o index.php
│   └── index.php     ← porta de entrada (repassa para public/index.php)
├── xml/              ← XMLs de saída   (coluna xml.url = "xml/...")
├── xml_entrada/      ← XMLs de entrada (coluna xml.url = "xml_entrada/...")
└── ...               ← demais arquivos do site
```

- O sistema fica em `https://seudominio.com.br/admin`.
- Os caminhos de XML gravados no banco são relativos a `public_html`, então o sistema procura os arquivos na **pasta pai** dele (`FILESYSTEM_LOCAL_ROOT=..`). Notas importadas pelo sistema novo também são gravadas lá, em `xml/{cnpj}/{mm-aa}/{chave}.xml`.
- Os dois sistemas usam o **mesmo banco MySQL**. As migrations pendentes só **criam tabelas novas** (SIEG e eventos) e não alteram as tabelas do sistema antigo.

---

## 0. Antes de começar: verifique no painel

| Item | Onde ver | Exigido |
|------|----------|---------|
| Versão do PHP do site | Painel → Hospedagem → *Administrar* → **Versão PHP** | **8.4 ou mais nova**. As bibliotecas (Symfony 8) não rodam em versões anteriores. |
| PHP da linha de comando (SSH) | `php -v` via SSH | 8.4+. O PHP do SSH pode ser diferente do PHP do site; se for, use o caminho completo do binário 8.4 (pergunte ao suporte qual é). |
| Extensões | `php -m` via SSH, ou um `phpinfo()` temporário | `pdo_mysql`, `mbstring`, `openssl`, `dom`, `xml`, `zip`, `gd`, `fileinfo`, `curl` |
| `mod_rewrite` e `.htaccess` | Já usados pelo site antigo | Precisam estar ativos (padrão na Locaweb). |
| Acesso SSH | Painel → *SSH* | Muito recomendado. Sem SSH, veja a seção 7. |

**Se o painel não oferecer PHP 8.4**, não dá para publicar nessa hospedagem: será preciso pedir a versão ao suporte ou migrar para VPS (item D1 em [PENDENCIAS_FINALIZACAO.md](PENDENCIAS_FINALIZACAO.md)).

> A versão do PHP vale para o site inteiro. Se o sistema antigo (PHP 5.6) ainda estiver rodando em `public_html`, mudar para 8.4 **quebra o sistema antigo**. Confirme com o suporte se é possível ter versões diferentes por pasta; se não for, a troca para 8.4 é o momento da virada.

---

## 1. Gerar o pacote na sua máquina

Na pasta do projeto, no branch que vai ser publicado:

```bash
git switch main && git pull
```

```bash
composer install --no-dev --optimize-autoloader
```

```bash
npm ci && npm run build
```

Monte o pacote sem arquivos de desenvolvimento nem segredos:

```bash
zip -r ../maisxml_admin.zip . -x ".git/*" "node_modules/*" "tests/*" "maisxml_old/*" ".env" ".env.backup*" ".env.local*" "storage/logs/*" "storage/framework/sessions/*" "storage/framework/views/*" "storage/framework/cache/data/*" ".claude/*" "*.DS_Store"
```

Depois, volte as dependências de desenvolvimento para continuar trabalhando localmente:

```bash
composer install
```

> O pacote leva o `.env.production.example` e os arquivos `.htaccess` e `index.php` da raiz do projeto. O `.env` local, que aponta para o banco de produção com a sua senha, **não** vai.

---

## 2. Enviar os arquivos

Envie `maisxml_admin.zip` para `public_html/` (SFTP, FTP ou gerenciador de arquivos) e descompacte em `public_html/admin`. Via SSH:

```bash
cd ~/public_html && unzip -q maisxml_admin.zip -d admin && rm maisxml_admin.zip
```

Confira que existem `public_html/admin/.htaccess` e `public_html/admin/index.php` (arquivos começados com ponto às vezes ficam ocultos no FTP).

Permissões de escrita para o PHP:

```bash
cd ~/public_html/admin && chmod -R 775 storage bootstrap/cache
```

A pasta `public_html/xml` também precisa aceitar escrita, para as notas importadas pelo sistema novo.

---

## 3. Configurar o `.env` de produção

```bash
cd ~/public_html/admin && cp .env.production.example .env && chmod 600 .env
```

Preencha no servidor (nunca mande este arquivo por e-mail ou chat):

| Variável | Valor |
|----------|-------|
| `APP_URL` | `https://seudominio.com.br/admin` (sem barra no final) |
| `APP_KEY` | gere com `php artisan key:generate` (seção 4). **Depois de cadastrar contas SIEG, nunca troque**: a chave SIEG é guardada criptografada com ela. |
| `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Os mesmos do sistema antigo (hoje: host `mysql01.maisxml.hospedagemdesites.ws`, banco `maisxml`). |
| `FILESYSTEM_LOCAL_ROOT` | `..` (já vem assim): a pasta pai de `admin`, ou seja, `public_html`, onde estão `xml/` e `xml_entrada/`. Também aceita caminho absoluto. |
| `INGEST_TOKEN` | Um token longo e aleatório (ex.: `php -r 'echo bin2hex(random_bytes(32));'`). Obrigatório: sem ele (ou `INGEST_HMAC_SECRET`) a API de ingestão não sobe. |
| `SESSION_PATH` | `/admin` (já vem assim): o cookie de sessão fica restrito ao sistema novo e não se mistura com os cookies do site em `public_html`. |
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | `file`, `file`, `sync` (já vêm assim). |
| `MAIL_*` | SMTP da Locaweb, para "Esqueceu a senha?" funcionar. |
| `SIEG_SCHEDULE_ENABLED` | `false` até a conta SIEG ser cadastrada (seção 9). |

---

## 4. Comandos no servidor (via SSH)

Antes das migrations, **faça um backup do banco** pelo painel da Locaweb (ou confirme que existe um backup recente).

```bash
cd ~/public_html/admin
```

```bash
php artisan key:generate --force
```

Veja antes o SQL que será executado:

```bash
php artisan migrate --pretend
```

```bash
php artisan migrate --force
```

As migrations pendentes são 6 e só criam tabelas novas (`sieg_accounts`, `sieg_sync_cursors`, `sieg_sync_runs`, `fiscal_events`); a que cria `usuario` é ignorada porque a tabela já existe.

Otimizações de produção (rode de novo a cada publicação e sempre que mudar o `.env`):

```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
```

Se o PHP do SSH não for o 8.4, use o caminho completo do binário em todos os comandos, por exemplo `/usr/bin/php84 artisan ...` (confirme o nome com o suporte).

### 4.1 Ajustes de PHP

O relatório PDF e a impressão de DANFE em lote precisam de memória e tempo. Se a conta usa `suPHP_ConfigPath /home/maisxml/` (como o `.htaccess` antigo indica), ajuste o `php.ini` dessa pasta, ou peça ao suporte:

```ini
memory_limit = 512M
max_execution_time = 120
upload_max_filesize = 20M
post_max_size = 25M
```

---

## 5. Conferência de segurança (obrigatória)

Como o código fica dentro de `public_html`, o `admin/.htaccess` é o que impede o acesso direto ao código e às senhas. Depois de publicar, abra no navegador e confirme que **todos** retornam **403** (ou 404), nunca o conteúdo:

- `https://seudominio.com.br/admin/.env`
- `https://seudominio.com.br/admin/.env.production.example`
- `https://seudominio.com.br/admin/storage/logs/laravel.log`
- `https://seudominio.com.br/admin/vendor/autoload.php`
- `https://seudominio.com.br/admin/config/database.php`
- `https://seudominio.com.br/admin/composer.json`
- `https://seudominio.com.br/admin/artisan`

E que estes funcionam:

- `https://seudominio.com.br/admin` → tela de login.
- `https://seudominio.com.br/admin/build/manifest.json` → conteúdo JSON (estilos e scripts carregam).

Se algum dos bloqueados abrir, **tire o site do ar** (renomeie a pasta `admin`) e verifique se o `.htaccess` foi enviado e se o `mod_rewrite` está ativo.

> Os XMLs em `public_html/xml/` continuam acessíveis publicamente por URL, como já acontece no sistema antigo. Fechar esse acesso depende de as integrações externas (item D3 das pendências) não usarem mais `/xml/...`.

---

## 6. Checklist de homologação

Com usuários reais:

- [ ] Login com a senha antiga e logout (menu do usuário → Sair).
- [ ] Painel abre e mostra os totais do período.
- [ ] `/admin/documentos`: filtro por período, várias filiais, operação e chave.
- [ ] Ver XML, baixar XML, DANFE individual de uma NF-e e de uma NFC-e.
- [ ] Uma nota de **entrada** (`xml_entrada/`) abre e baixa.
- [ ] ZIP e "Imprimir DANFE selecionados" com algumas notas marcadas.
- [ ] Exportar CSV e PDF.
- [ ] "Esqueceu a senha?" envia o e-mail (se o SMTP estiver configurado).
- [ ] Conferência de segurança da seção 5.
- [ ] `storage/logs/laravel-*.log` sem erros novos.

---

## 7. Sem SSH

Sem SSH não há como rodar `artisan` no servidor:

- **Migrations:** podem ser rodadas da sua máquina, porque o `.env` local já aponta para o banco de produção: `php artisan migrate --force` (faça o backup antes).
- **`APP_KEY`:** gere localmente com `php artisan key:generate --show` e cole no `.env` do servidor.
- **Caches (`config:cache` etc.):** não rode localmente, porque gravariam caminhos da sua máquina. Sem eles o sistema funciona, só um pouco mais lento.

---

## 8. Depois da publicação

- Integrações antigas (`/recepcao.php`, `/control/api.php`, `/control/down_direto.php`, `/control/view_chave.php`, `/xml/...`) continuam dependendo dos arquivos do sistema antigo em `public_html`. Só remova esses arquivos quando cada uma tiver substituto ou for confirmada sem uso (itens D3 e P1-2 das pendências).
- **Rollback:** mantenha os arquivos do sistema antigo intactos por 30 dias.
- Depois da virada, troque **todas** as senhas e chaves que estavam no código antigo (banco, FTP, SMTP, chave da API em `control/api.php`, chave SIEG).

---

## 9. Agendamento (SIEG) — depois

Na hospedagem compartilhada, o agendador da Locaweb só roda entre 2h e 3h e não aceita "a cada minuto". Quando a conta SIEG for cadastrada (`php artisan sieg:account`, ver [SIEG_INTEGRACAO.md](SIEG_INTEGRACAO.md)), agende no painel, uma vez por noite:

```bash
cd ~/public_html/admin && php artisan sieg:sync --max-runtime=3300
```

---

## 10. Publicações seguintes

1. Na sua máquina: `git pull`, `composer install --no-dev -o`, `npm run build`, gerar o zip (seção 1).
2. No servidor: `php artisan down`, substituir os arquivos de `public_html/admin` **sem apagar** `.env` e `storage/`.
3. `php artisan migrate --force`, os quatro `*:cache` da seção 4 e `php artisan up`.

---

## Limitações conhecidas desta versão

Itens ainda abertos em [PENDENCIAS_FINALIZACAO.md](PENDENCIAS_FINALIZACAO.md) que afetam o uso em produção:

- **Permissões (P0-3):** todo usuário vinculado ao CNPJ da empresa tem acesso total, inclusive para **excluir notas**. No sistema antigo só o administrador excluía.
- **Senhas (P0-2):** continuam em MD5, como no sistema antigo.
- **Ingestão por API (P0-5):** um único token global para todas as filiais.
- **Paridade (P1):** cadastros de empresa/filial/usuário, relatórios de ICMS e layouts de Excel do sistema antigo ainda não existem no novo.
