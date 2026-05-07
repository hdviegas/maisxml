# Memory: Mais XML — legacy application (`maisxml_old`)

This document inventories **features, resources, data touchpoints, and integrations** present in the legacy PHP stack (PHPera framework under `phpera/`, scripts under `control/`, UI under `static/`, entry points at project root). It exists to support a full rewrite on a modern stack while preserving functional coverage.

**Stack summary (legacy)**  
- Custom mini-framework: `phpera/app.php` (session, `App::core()` factory, `need_login`), `phpera/core.php`, `phpera/config.php`, `phpera/functions.php`.  
- Database: MySQL via `phpera/classes/db/mysql.php` (mysqli, string-built SQL).  
- XML: `SimpleXML` via `phpera/classes/xml.php`; optional XSD validation / NFePHP hooks in `phpera/ext`.  
- PDF: dompdf under `phpera/ext/dompdf`, FPDF + Danfe in `control/danfe/`.  
- Excel: legacy PHPExcel folder, `control/excel/`, `static/excel/`.  
- Frontend: jQuery, jQuery UI, Select2, tablesorter; duplicate view trees: `static/template/`, `static/temp_novo/`, `static/forms/`.

---

## Domain tables (observed in code)

| Table / area | Usage |
|--------------|--------|
| **`xml`** | Central store of NF-e/NFC-e metadata (chave, urls, CNPJ emit/dest, totals, CFOP, cancelada, modelo, série, número, datas, IP, download counters `downs`, etc.). Files on disk under paths like `xml/{cnpj}/{m-y}/arquivo.xml`. |
| **`empresa`** | Company master; linked to logged user by CNPJ. |
| **`empresa_filial`** | Branches (CNPJ, grupo, certificado, flags like `entrada`, `ult_consulta_sefaz` referenced in automation). |
| **`usuario`** | Login accounts; join to `empresa` on CNPJ; `tipo` maps to profile (empresa / funcionário / contador / usuário). |
| **`funcionario`** | Staff (CRM-style “cliente” in some templates). |
| **`contador`** | Accountant profiles. |
| **`acessos`** | Login audit (tipo, IP, codigo user) on successful login with `ok=1`. |
| **`sped`** | Lines derived from SPED file (C100): `cnpj`, `data_nf`, `chave_nf`, `total_nf`, `total_icms` — used to cross-check XML vs SPED in listings. |

> Exact column lists should be confirmed with a DB schema export (`SHOW CREATE TABLE`) when migrating.

---

## User types (`phpera/config.php`)

- **Numeric `tipo` → label**: `1` empresa, `2` funcionário, `3` contador, `99` usuário (`$cfg->tipos`).  
- **`$cfg->tabelas_login`**: `empresa`, `funcionario`, `contador` — drive auto user creation on insert (`control/control-1.php`).  
- Session keys after login: `user-login`, `user-nome`, `user-email`, `user-doc` (CNPJ), `user-id`, `user-empresa`, `user-tipo` (string label).

---

## Entry points (root)

| File | Role |
|------|------|
| **`index.php`** | Main shell; if logged in loads `static/forms/xml.php` (Meus XML) else `static/forms/info.php`. |
| **`index_2.php`**, **`index_teste.php`** | Alternate/test entry points. |
| **`recepcao.php`** | HTTP receiver: accepts raw XML in request, validates emit/protocol/chave, saves to `tmp/{chave}-NFe/NFCe.xml` for batch import. |
| **`info.php`**, **`hecktor.php`**, **`test_*.php`** | Diag / test utilities. |
| **`PHPExcel/`** | Legacy spreadsheet library. |

---

## Control scripts (`control/*.php`) — functional map

| Script | Purpose |
|--------|---------|
| **`login.php`**, **`login1.php`** | Auth: MD5 password, JSON or redirect; logs `acessos`. |
| **`sair.php`** | Logout. |
| **`checa_session.php`** | Session check. |
| **`control-1.php`** | Generic CRUD: insert/update by `tabela`/`acao`; MD5 on password fields; creates `usuario` rows for `tabelas_login`. |
| **`editar_cadastros.php`** | Load record by `tipo` (1 empresa, 2 funcionário, 3 contador, 4 usuário, 5 filial) → includes template form. |
| **`apagar.php`** | Deletes `xml` row and file; logs. |
| **`importa_xml.php`**, **`importa_xml2.php`** | Batch import from `tmp/`: parse NFe, move to `xml/...`, upsert `xml`. |
| **`importa_xml_entrada.php`**, **copy** | Import pipeline for **entrada** from `tmp_entrada`/FTP paths. |
| **`api.php`** | JSON API: filters on `xml` by empresa/CNPJ, **operacao** (1 compras, 2 saída, 3 transferência, 4 entradas gerais, 5 devoluções), dates, chaves, pagination; reloads XML files to enrich JSON (`total_icms_st`, `natOp`, operation type). |
| **`cfop.php`** | Backfill or fix `cfop_nf` on `xml` records. |
| **`meus_xml.php`** | Main listing UI server-side + filters + SPED date cross-check SQL. |
| **`meus_xml_pdf.php`**, **`meus_xml_pdf_default.php`** | PDF export paths for listings. |
| **`gera_relatorios.php`** | HTML/PDF report builder (table layout, empresa header). |
| **`relatorios.php`**, **`relatorio_csv.php`**, **`relatorio_icms.php`** | Report / CSV / ICMS outputs. |
| **`gerarexcel.php`**, **`gerarexcel_1.php`**, **`gerarexcel_fortes.php`** | Excel exports (Fortes = specific layout). |
| **`download.php`**, **`down.php`**, **`down_direto.php`**, **`multi-down*.php`**, **`multi-view.php`**, **`view.php`**, **`view_chave.php`** | Download and view XML/ZIP bundles; filter by filial CNPJs. |
| **`downxmlentrada.php`**, **`downxmlentrada_sieg.php`**, **`downxmlentrada_teste.php`**, **`downxmlsaidas_sieg.php`** | SIEG / SEFAZ-oriented download flows. |
| **`envio.php`** | Validates/submits XML shipment metadata (`xml` by chave/url). |
| **`sped.php`**, **`upload_sped.php`** | Upload SPED flat file, parse `|C100|`, insert `sped`, validate CNPJ vs `empresa_filial`. |
| **`conferencia_sped_pdf.php`** | SPED conference PDF. |
| **`imprimir.php`**, **`pdf.php`**, **`painel_detalhe_pdf.php`** | Print/PDF utilities. |
| **`lista_empresas.php`** | Lists filiais with `entrada = 1` (pull automation). |
| **`atualiza_entrada.php`** | Batch update for entrada / certificate polling (group_concat codigos filiais with cert). |
| **`manifestadest.php`** | Manifestação do destinatário–related processing for selected `chave`s. |
| **`xml_invalido.php`** | Invalid XML handling. |
| **`confignfe.php`** | NFe tooling config from `empresa_filial`. |
| **`autocomplete.php`** | Generic autocomplete: `tabela` + field + empresa scope. |
| **`ajax.php`** | Minimal/empty stub (verify if used). |
| **`contato.php`** | Contact-related query over `xml` (legacy). |
| **`buraco.php`** | Placeholder / dev. |
| **`head.php`** | Shared control header if any. |
| **`envio.php`** | Related to XML send/consistency. |
| **`novo/control/envio_contato.php`** | Contact form backend for `novo/` subsite. |

### `control/upload/`

Upload helpers (uploadify-era) for chunks / existence checks.

### `control/excel/`, `control/JAVA/`, `control/danfe/`

Legacy Excel generation, Java integration folder, FPDF + **DanfeNFePHP** for printed DANFE.

---

## PHPera classes (`phpera/classes/`)

| Class file | Role |
|------------|------|
| **`db/mysql.php`** | CRUD: `gravar`, `editar`, `excluir`, `consultar`, `consultarAssoc`, `executar` — **SQL concatenation** throughout. |
| **`xml.php`** | `carregar` / `carregar_String`; NFe tree access; `validaXML` with NFePHP + XSD. |
| **`util.php`** | Form/query cleaning, JSON helpers, arrays. |
| **`html.php`** | HTML helpers (combos from SQL). |
| **`logs.php`** | File-based logging (erro, relatorio, api, upload channels). |
| **`ftp.php`** | FTP connect for remote paths (import pipeline). |
| **`email.php`** | SMTP config from `$cfg->email`. |
| **`excel.php`** | Thin wrapper for spreadsheet export. |
| **`pdf.php`**, **`pdf_novo.php`**, **`dpdf.php`** | PDF generation wrappers. |
| **`gerarelatorios.php`** | Report orchestration. |
| **`download.php`**, **`downnfe.php`**, **`upload.php`**, **`zip.php`** | File delivery and archives. |
| **`sqli.php`** | Secondary DB helper (if used). |

---

## Static / UI

- **`static/forms/`** — Page fragments included by `index` and flows: login, empresa, filial, cliente, contador, usuario, relatórios, meus_xml, gerencia_xml, painel, excel, apuracao_icms, conferencia_sped, upload_sped, fornecedor, etc.  
- **`static/temp_novo/`** — Newer skin: `head`, `menu`, `footer`, dashboards (`painel`, `painel_ajax`, `painel_detalhe`), CRUD templates with `dados_*` partials, `meus_xml`, reports, `style.css`.  
- **`static/template/`** — Older parallel templates (gerencia_xml variants, rels, etc.).  
- **`static/include/`** — `header`, `footer`, `listar-xml`, `slides.html`.  
- **`static/css/`**, **`static/js/`** — jQuery, Fancybox, tablesorter, validate, `index.js`, `exibe_tela.js`.  
- **`static/imgs/`**, **`static/excel/`** — Assets and client-side excel helpers.

---

## Subapp `novo/`

Alternate login/marketing UI (`novo/index.php` with jQuery UI dialogs), `gerencia_xml_2.php`, test and `envio_contato.php`.

---

## Configuration & business rules (`config.php`)

- **FTP** credentials and remote base path for imports.  
- **MySQL** connection parameters.  
- **Email** SMTP placeholders.  
- **Upload** allowlist (`xml`).  
- **CFOP lists**: `cfop_dev`, `cfop_trasnf`, `cfop_saidas`, `cfop_compras` (large comma-separated lists for fiscal classification).  
- **Event types** (`tpeventos`, `descEventos`) for NFe events (cancellation, CC-e, manifestação, etc.).

---

## External / notable dependencies

- **NFePHP** (under `phpera/ext/nfephp`) for validation/protocol in `xml::validaXML`.  
- **dompdf** (vendor tree under `phpera/ext/dompdf`).  
- **PHPExcel** (root).  
- **Certificates** stored under `certificados/` (per-deploy sensitive).  

---

## Security notes (for rewrite, not endorsement of legacy)

- Passwords: **MD5**.  
- SQL: largely **interpolated strings** → injection risk.  
- `api.php` exposes an **inline API key** in source (must be replaced with proper auth).  
- **Secrets in `config.php`** — must not ship to new repo; use environment variables.

---

## File storage layout (observed)

- **`tmp/`**, **`tmp_entrada/`** — Staging for inbound XML before import.  
- **`xml/{cnpj}/{mm-yy}/`** — Organized storage after successful import.  
- **`tmp_sped/`** — SPED upload temp.  
- **`logs/`** — Application logs (referenced in config).

---

## Test / sample assets

- Sample NFe/NFC-e XMLs at root (`*-nfe.xml`, `*-NFCe.xml`).  
- HTML test harnesses (`teste_entradas.html`, `teste_saidas.html`, `teste_cfop.html`).

---

*Generated to baseline parity for a Laravel (or similar) recreation. Update this file when new legacy behaviors are discovered during migration.*
