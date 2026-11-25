# Mais XML — Pendências para finalizar e publicar na Locaweb

Levantamento de 2026-11-25. Compara o app novo (Laravel 13) com o legado (`maisxml_old/`, PHP 5.6 na hospedagem compartilhada da Locaweb) e com as restrições de hospedagem. Tamanhos: **P** < 1 dia · **M** 1–5 dias · **G** > 5 dias.

---

## 0. Decisões do cliente (bloqueiam o resto)

| # | Decisão | Por quê | Recomendação |
|---|---------|---------|--------------|
| D1 | **Onde hospedar na Locaweb** | O `composer.lock` trava Symfony 8 → **PHP ≥ 8.4 obrigatório**. A ajuda oficial da Locaweb para *Hospedagem de Sites* só confirma até PHP 8.0 (padrão). O agendador da hospedagem compartilhada não aceita `*` em minuto/hora e roda só entre 2h e 3h. Não há processo contínuo, o SSH tem no máximo 2 conexões e o MySQL fica em outro servidor com limite de 30 conexões. | **VPS Locaweb (Ubuntu, 2–4 GB)** com PHP 8.4, MySQL 8, cron a cada minuto e Supervisor. Manter o domínio e o DNS na Locaweb. Antes, **pedir ao suporte por escrito** as versões de PHP/CLI e as extensões (`gd`, `soap`, `zip`, `bcmath`) do plano atual. Se o 8.4 existir no compartilhado, dá para ficar lá, mas sem sincronização automática com a SEFAZ. |
| D2 | **Banco: reaproveitar o MySQL do legado ou migrar para um MySQL 8 novo** | O app novo lê as tabelas legadas (`xml`, `empresa`, `empresa_filial`, `usuario`, `sped`) e cria tabelas novas. Algumas migrations são destrutivas (ver P0-6). O charset do legado pode ser latin1/utf8 (MyISAM). | Fazer o dump do legado e importar num MySQL 8 novo (utf8mb4). Converter charset no dump e validar com o cliente antes do corte. |
| D3 | **Quais integrações externas precisam continuar funcionando sem mudança** | Os clientes podem chamar `/recepcao.php?x=`, `/control/api.php`, `/control/down_direto.php`, `/control/view_chave.php` e `/xml/...`. Todos são **públicos e sem autenticação** no legado. | Levantar nos logs de acesso da Locaweb quem chama cada URL. Manter apenas o necessário, como shims autenticados por token (ver P1-2). |
| D4 | **Escopo de SEFAZ/SIEG/Manifestação no primeiro corte** | É o maior bloco (G) e depende de certificados A1 e de cron. | Publicar primeiro a paridade de consulta, importação e relatórios. Entregar a automação SEFAZ/SIEG numa fase 2, já na VPS. |
| D5 | **Perfis de usuário** | O legado tem `tipo` empresa/funcionário/contador/usuário. O tipo `usuario` (99) é administrador global e o único que exclui notas. No app novo, todo usuário com o CNPJ da empresa tem acesso total e pode excluir. | Mapear `tipo` → papéis (P0-3). |

---

## 1. Concluído nesta sessão

- **Isolamento entre empresas na importação** (web, API e ingestão), em [NfeImportService.php](../app/Services/Nfe/NfeImportService.php) e [Organization.php](../app/Models/Organization.php):
  - o XML só é aceito se o emitente ou o destinatário for CNPJ de uma filial da empresa da filial escolhida;
  - a chave de acesso precisa conter o CNPJ/CPF do emitente;
  - uma chave já existente em outra empresa nunca é devolvida, substituída nem "pulada";
  - "pode importar" passou a valer o mesmo que "pode ver".
- Testes em [TenantIsolationTest.php](../tests/Feature/TenantIsolationTest.php). A suíte tem **59 testes passando** e o Pint está limpo.

---

## 2. P0 — Segurança e bloqueadores de go-live

| # | Item | Evidência | Tam. |
|---|------|-----------|------|
| P0-1 | **Primeiro commit do app novo.** Todo o código está *untracked* no Git. | `git status` | P |
| P0-2 | **Senhas MD5 → bcrypt/Argon2** com rehash transparente no login. Valer para login, cadastro, redefinição e perfil. | `AuthenticatedSessionController.php`, `ProfileController.php`, `NewPasswordController.php` | P |
| P0-3 | **Papéis por `usuario.tipo`**: admin global (99), empresa, funcionário (restrito), contador (multiempresa). Hoje qualquer conta com o CNPJ da empresa exclui notas. | `FiscalDocumentPolicy.php`, `OrganizationRole` sem uso | M |
| P0-4 | **Fechar `/register` público.** O legado não tinha autocadastro. Pôr throttle em `forgot-password`. "Excluir conta" não pode apagar `usuario` compartilhado. | `routes/auth.php`, `ProfileController::destroy` | P |
| P0-5 | **Ingestão: token por filial** em vez de um token global que escolhe qualquer `X-Branch-Id`. | `EnsureIngestAuthorized.php` | M |
| P0-6 | **Migrations seguras no banco legado.** `drop_users_table` faz `DROP TABLE users` incondicional. `sessions`/`password_reset_tokens` não têm guarda. Há `ALTER` em `usuario`. Ensaiar com `--pretend` numa cópia. | `database/migrations/` | P |
| P0-7 | **Arquivos XML legados.** O `xml.url` aponta para `xml/...` e `xml_entrada/...` sob a raiz web antiga. O app lê de `storage/app/private`. É preciso copiar os arquivos (e suportar `xml_entrada/`), senão ver/baixar/DANFE/ZIP dão 404. | `config/filesystems.php:35`, `FiscalDocument::storage_path` | M |
| P0-8 | **Configuração de produção**: `APP_ENV=production`, `APP_DEBUG=false`, HTTPS forçado e `trustProxies`, `SESSION_SECURE_COOKIE`, SMTP real (sem ele não há redefinição de senha; SMTP compartilhado limitado a 100/h por caixa), `LOG_CHANNEL=daily` e `LOG_LEVEL=warning`, `INGEST_TOKEN` no `.env.example`. | `.env.example`, `bootstrap/app.php` | P |
| P0-9 | **Alinhar requisitos**: `composer.json`/`platform` dizem `^8.3`, mas o lock exige 8.4. Declarar `ext-zip`, `ext-gd`, `ext-soap`, `ext-bcmath`. | `composer.json` | P |
| P0-10 | **Validar a assinatura XML da NF-e** (e o `protNFe`) com `nfephp-org/sped-common`, já em `vendor/`. Sem isso, uma empresa pode importar um XML forjado no próprio CNPJ ou "reservar" uma chave. | `NfeXmlParser.php` | M |

---

## 3. P1 — Paridade necessária para desligar o legado

### 3.1 Cadastros e usuários
| # | Item | Legado | Tam. |
|---|------|--------|------|
| P1-1 | CRUD de empresa, filial (endereço, UF, grupo, certificado), funcionário e contador, com criação do `usuario` correspondente. Hoje "Configurações" só lista. | `control-1.php`, `dados_*.php` | G |
| P1-1b | Log de acessos (`acessos`) e relatório de acessos/usuários/filiais | `login.php`, `relatorios.php` | P |

### 3.2 Ingestão e importação
| # | Item | Legado | Tam. |
|---|------|--------|------|
| P1-2 | **Shim de compatibilidade `/recepcao.php`**: aceita `x` por GET/POST, valida `nProt`, `chNFe` e modelo 55/65, responde em texto (`OK`/`ERROR`). Fica atrás de token, e não aberto como hoje. | `recepcao.php` | M |
| P1-3 | **Importação completa**: preencher `social_emit`/`social_dest`, `total_icms`, `total_icms_st` e `total_frete` (hoje gravados como `null`). Marcar `cancelada` quando `cStat` = 101/151. | `importa_xml*.php` | M |
| P1-4 | Pipeline de **entrada** (`xml_entrada/{cnpj_dest}/`, sobrescrita de canceladas) | `importa_xml_entrada.php` | M |
| P1-5 | Importação em lote em fila (com timeout baixo na hospedagem, 30 arquivos síncronos é arriscado) | — | M |

### 3.3 Listagem, API e eventos
| # | Item | Legado | Tam. |
|---|------|--------|------|
| P1-6 | **Filtros que faltam**: tipo de nota pela raiz do CNPJ (entrada/saída/transferência/saída NF-e/NFC-e), situação (canceladas/SPED), razão social, CFOP, faixa de nº NF, linha de totais (Σ vNF e Σ ICMS), coluna de eventos | `meus_xml.php:485-840` | M |
| P1-7 | **Regra de "operação"**: o legado classifica pela raiz do CNPJ e exclui CFOP 5117/6117. O app novo usa listas de CFOP e filtra `xml.operation_type`, coluna **que nenhuma migration cria**, então o filtro não faz nada. Decidir a regra e criar a coluna, ou calcular na hora. | `api.php`, `OperationClassifier.php` | M |
| P1-8 | **Compatibilidade da API legada** `/control/api.php` (array JSON cru), se houver consumidores (D3). Criar uma tela para **emitir tokens Sanctum**, que hoje não existe. | `api.php` | M |
| P1-9 | **Eventos**: ler a tabela `eventos` (cancelamento, CC-e), mostrar na listagem e no Excel, atualizar `cancelada` | `downxmlentrada_teste.php` | M |
| P1-10 | **Corrigir `fiscal:reindex-documents` e `fiscal:backfill-operations`**: usam atributos fora do `$fillable` (não gravam nada) e uma coluna `organization_id` inexistente | `app/Console/Commands/` | P |

### 3.4 Relatórios e downloads
| # | Item | Legado | Tam. |
|---|------|--------|------|
| P1-11 | Apuração/relatório de ICMS | `apuracao_icms.php`, `relatorio_icms.php` | M |
| P1-12 | Excel/CSV no layout legado, incluindo o **Fortes** (com eventos), e CSV por item (`det`) | `gerarexcel*.php`, `relatorio_csv.php` | M |
| P1-13 | Conferência SPED em PDF. **Não portar** o SQL em base64 via POST do legado. | `conferencia_sped_pdf.php` | M |
| P1-14 | Painel: emitidas × recebidas, série de 12 meses, NF-e × NFC-e | `painel*.php` | M |
| P1-15 | ZIP do **conjunto filtrado inteiro** (por pastas `m-y`, opção de incluir DANFE em PDF) e DANFE múltiplo em um PDF. Hoje são no máximo 100 IDs, só XML. | `multi-down_novo.php`, `multi-view.php` | M |
| P1-16 | Contador `downs` e "XML inválido/ausente" | `down.php`, `xml_invalido.php` | P |
| P1-17 | Upload do SPED: gravar o CNPJ do registro 0000 e validar contra as filiais. Hoje grava o CNPJ do usuário. | `upload_sped.php`, `SpedC100ImportService.php` | P |

---

## 4. P2 — Automação fiscal (fase 2, exige VPS e cron)

| # | Item | Legado | Tam. |
|---|------|--------|------|
| P2-1 | **Certificados A1 por filial** com armazenamento seguro (criptografado, fora do webroot) e alerta de vencimento | `certificados/` (9 clientes) | M |
| P2-2 | **DistDFe/SEFAZ** por filial (NSU, ciência automática 210210, eventos), agendado | `downxmlentrada_teste.php`, `atualiza_auto*.php` | G |
| P2-3 | **SIEG**: entradas e saídas paginadas, com a chave em `.env`. Hoje ela está fixa no código legado e deve ser rotacionada. | `downxml*_sieg.php` | M |
| P2-4 | **Manifestação do destinatário** (210200/210220/210240) com a tabela `manifesta_dest`. Hoje é uma página estática. | `manifestadest.php` | G |
| P2-5 | Scheduler (`routes/console.php`) e fila com worker (Supervisor). Na hospedagem compartilhada só existe a janela de cron das 2h às 3h. | — | P |

---

## 5. Desempenho e qualidade (antes de carregar a base real)

- `REPLACE()` sobre as colunas de CNPJ em `xml`, `empresa` e `empresa_filial` impede o uso de índice: cada listagem, painel e exportação faz full scan. Criar colunas normalizadas e indexadas (`cnpj_emit_digits`, `cnpj_dest_digits`) num backfill. **M**
- N+1 em `FiscalDocument::organization_id`/`branch_id` (policy por item no ZIP) e `Schema::hasColumn` a cada request. **P**
- PDF de listagem com até 2.000 linhas em memória (dompdf). Limitar ou paginar. **P**
- `route:cache` quebra com a closure da rota `/` em `routes/web.php`. **P**
- Documentação divergente: o [ADR-001](ADR-001-frontend-stack.md) diz Livewire, mas o app é Blade. O DEVELOPMENT_PLAN diz Laravel 12. O README descreve o caminho de armazenamento antigo. `@tailwindcss/vite` v4 está instalado sem uso. **P**

---

## 6. Plano de publicação (cutover)

1. Confirmar D1–D5 com o cliente e contratar ou validar o ambiente.
2. Subir um **staging** (subdomínio) com cópia do banco e dos arquivos XML. Rodar as migrations com `--pretend` e depois de verdade.
3. Preparar o deploy:
   - `composer install --no-dev -o` e `npm run build` no CI;
   - na VPS: `git pull`, `php artisan migrate --force`, `config:cache`, `route:cache`, `view:cache`;
   - na hospedagem compartilhada: enviar `vendor/` e `public/build/` por FTP e ajustar `public_html/index.php` para apontar ao app fora do webroot.
4. Fazer o checklist de homologação com usuários reais de cada `tipo`: login com a senha MD5 antiga (rehash), listagem, download, DANFE, SPED e ingestão.
5. **Corte**:
   - congelar o legado (somente leitura);
   - sincronização final do banco e dos arquivos;
   - trocar o DNS ou o docroot;
   - manter os shims das URLs legadas (D3);
   - monitorar logs por 1 a 2 semanas.
6. **Rollback**: manter o legado intacto e reversível pelo DNS por 30 dias.
7. Depois do corte: rotacionar **todos** os segredos que estavam no código legado (credenciais de banco, FTP e SMTP em `phpera/config.php`, chave da API em `api.php`, chave SIEG, certificados).

---

## 7. Estimativa

| Bloco | Esforço |
|-------|---------|
| P0 (segurança + bloqueadores) | ~2 semanas |
| P1 (paridade para desligar o legado) | ~5–7 semanas |
| §5 desempenho | ~1 semana |
| Publicação/staging/corte | ~1 semana |
| **Total até substituir o legado (sem automação SEFAZ)** | **~9–11 semanas** |
| P2 automação fiscal (fase 2) | +3–5 semanas |

## Fontes (Locaweb)

- [Como alterar a versão do PHP — Hospedagem de Sites](https://www.locaweb.com.br/ajuda/wiki/como-alterar-a-versao-do-php-hospedagem-de-sites-ajuda-locaweb/)
- [Como usar o agendador de tarefas — Hospedagem de Sites](https://www.locaweb.com.br/ajuda/wiki/como-usar-o-agendador-de-tarefas-hospedagem-de-sites)
- [Como utilizar o SSH — Hospedagem de Sites](https://www.locaweb.com.br/ajuda/wiki/como-utilizar-o-ssh-hospedagem-de-sites)
- [Composer — Hospedagem Dedicada](https://www.locaweb.com.br/ajuda/wiki/composer-cloud-hosting/)
- [VPS Locaweb](https://www.locaweb.com.br/vps/)
