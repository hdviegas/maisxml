# Mais XML — remake development plan

This plan defines scope, use cases, features, UX direction, and a phased roadmap for rebuilding Mais XML on a modern stack. Baseline parity comes from **`MEMORY_LEGACY_MAISXML.md`**; additive product ideas from **`FEATURE_SUGGESTIONS.md`**.

---

## 1. Goals

- **Replace** legacy PHP (PHPera) with a maintainable stack: Laravel 12, PHP 8.4, MySQL 8, Redis (queues/cache), object storage for XML blobs, structured logging, parameterized SQL, modern auth.
- **Preserve** core user journeys (import, list/filter, export, SPED cross-check, filial/empresa model, contador flows) while **improving** security, observability, and scalability.
- **Ship** one coherent product surface: single design system, no parallel `template` / `temp_novo` style divergence.
- **Locale**: End-user **front-end copy, labels, empty states, errors, and default date/number formatting** must be **Portuguese — Brazil (pt-BR)**. Developer docs and code comments may stay in English; API error codes stay machine-stable with localized messages for UI.

---

## 2. UX & UI principles

### 2.0 Language — Portuguese (Brazil)

| Area | Requirement |
|------|-------------|
| **UI strings** | All visible user-facing text in **pt-BR** (menus, buttons, help, validation, toasts, emails to users where applicable). |
| **Tone** | Clear, professional fiscal/accounting context; avoid informal slang. |
| **Formats** | Dates, currencies, decimals per **pt-BR** (e.g. `R$`, `dd/mm/yyyy` or locale-consistent controls). |
| **Implementation** | Single source of truth for copy (e.g. Laravel `lang/pt_BR`, frontend i18n with `pt-BR` default); English **not** a first-class UI locale unless product later requires it. |

Navigation and actions in later sections are described in English for this document only; **product UI implements the same concepts in pt-BR** (e.g. *Documentos*, *Importar*, *Relatórios*, *Configurações*).

### 2.1 Visual language — minimal, readable, calm

| Area | Direction |
|------|-----------|
| **Layout** | Generous whitespace, clear hierarchy (page title → primary action → content). Avoid dense chrome; one primary CTA per view where possible. |
| **Typography** | Comfortable reading: system UI stack or **Inter / Source Sans 3 / IBM Plex Sans** for UI; optional slightly larger base (e.g. 16px) for lists and forms. Tabular numbers for fiscal values (`font-variant-numeric: tabular-nums`). |
| **Color** | Low-contrast neutrals for chrome; **one** accent for links/actions. Semantic colors only for status (success / warning / error / info). Light mode default; dark mode optional later. Avoid pure black on pure white; use soft background (e.g. warm gray `#f8f9fa` or cool `#f4f6f8`) and near-black text. |
| **Density** | Default “comfortable” row height in tables; compact mode optional for power users. |
| **Accessibility** | WCAG AA for text contrast; focus rings; keyboard navigation for menus and tables. |

### 2.2 Interaction — intuitive actions and menus

- **Navigation**: Persistent sidebar or top-level sections with **short pt-BR labels** aligned to user mental model (e.g. *Documentos*, *Importar*, *Relatórios*, *SPED*, *Configurações*, *API*). Avoid deep nesting; group rarely used items under *Mais opções* or *Administração*.
- **Actions**: Primary verbs on buttons in pt-BR (*Importar*, *Baixar*, *Exportar*, *Enviar SPED*). Destructive actions behind confirm + clear warning copy in pt-BR.
- **Feedback**: Toasts for success/error; inline validation on forms; progress for long jobs (import queue, bulk ZIP).
- **Lists**: Saved filters, clear sort, pagination or virtual scroll; bulk select with a single *Ações em lote* menu.

### 2.3 Dashboard — consistent information model

Every logged-in landing experience should expose **the same categories** of information (layout can vary by role):

| Widget / section | Purpose | Consistency rule |
|------------------|---------|------------------|
| **Period & scope** | Selected month range + filial/org | Same control placement on dashboard and list pages |
| **Counts** | Documents in scope; split by operation type / model where relevant | Same definitions as list filters |
| **Exceptions** | Validation failures, SPED mismatches, missing XML | Always link to filtered list |
| **Recent activity** | Last imports, exports, uploads | Last N events with actor + time |
| **Quick actions** | Import, upload SPED, download template | Same shortcuts from dashboard |
| **Health** (admin/API) | Queue depth, failed jobs, certificate expiry | Only for roles that need it |

Avoid one-off metrics that do not match drill-down filters elsewhere in the app.

---

## 3. Personas (actors)

| Persona | Legacy mapping | Primary needs |
|---------|----------------|---------------|
| **Empresa owner** | `tipo` empresa | See all filiais, import/export, reports, certificates |
| **Funcionário** | `tipo` funcionário | Same as empresa with restricted scope (RBAC) |
| **Contador** | `tipo` contador | Multi-client read/export, SPED conference, reports |
| **Platform admin / support** | New / internal | Impersonation (audited), reprocess XML, tenant config |
| **API integrator** | `api.php` successor | REST API keys, webhooks, rate limits, OpenAPI |

---

## 4. Use cases

### 4.1 Authentication & tenancy

| ID | Use case | Actor | Acceptance notes |
|----|----------|--------|------------------|
| UC-A1 | Log in with email/password | All | bcrypt/Argon2; lockout optional |
| UC-A2 | Log out | All | Session / token invalidation |
| UC-A3 | Switch organization or filial scope | Empresa, Contador | Scoped data and dashboard |
| UC-A4 | Manage users and roles | Admin | RBAC: read / import / admin / API-only |
| UC-A5 | Optional SSO (Google/Microsoft) | Enterprise | Optional phase |
| UC-A6 | Support impersonation with audit trail | Admin | Full log of impersonation sessions |

### 4.2 Document ingestion

| ID | Use case | Actor | Acceptance notes |
|----|----------|--------|------------------|
| UC-D1 | Upload XML files (single/batch) | Empresa, Funcionário | Queue + progress; duplicate chave handling (replace/skip/keep) |
| UC-D2 | HTTPS receiver replaces `recepcao.php` | System / integrator | HMAC or signed payloads; no MD5-era secrets |
| UC-D3 | Pull from FTP/SFTP connector | Admin | Health check + failure notification |
| UC-D4 | Scheduled SEFAZ/SIEG sync per filial | Admin | Certificate rotation alerts |
| UC-D5 | Reprocess document from stored XML | Admin/support | Rebuild index without re-upload |

### 4.3 Validation & fiscal intelligence

| ID | Use case | Actor | Acceptance notes |
|----|----------|--------|------------------|
| UC-V1 | XSD + business rules validation | System | CFOP/CST/ICMS vs totals; clear error codes |
| UC-V2 | View event timeline per document | All | Cancelamento, CC-e, manifestação as audit trail |
| UC-V3 | Filter by model (55/65) and badges | All | MDF-e / CT-e if in scope |

### 4.4 Search, list, and details

| ID | Use case | Actor | Acceptance notes |
|----|----------|--------|------------------|
| UC-L1 | Global search: chave, número NF, CNPJ, value range | All | Saved searches optional |
| UC-L2 | Filter by operation type (compras/saída/transferência/entradas/devoluções) | All | Parity with legacy `operacao` semantics |
| UC-L3 | View XML metadata and download file | All | Respect RBAC and filial scope |
| UC-L4 | Bulk download ZIP / export CSV | All | Selection + job progress |

### 4.5 SPED & reconciliation

| ID | Use case | Actor | Acceptance notes |
|----|----------|--------|------------------|
| UC-S1 | Upload SPED; parse C100 (and extend to C190 etc.) | Contador, Empresa | Validate CNPJ vs filiais |
| UC-S2 | Reconciliation view: XML ↔ SPED matched / missing / value mismatch | Contador | Dashboard + drill-down |
| UC-S3 | Export reconciliation report PDF/Excel | Contador | Filters by period + filial |
| UC-S4 | “Período fechado” lock | Admin | Block edits during audit |

### 4.6 Reporting & print

| ID | Use case | Actor | Acceptance notes |
|----|----------|--------|------------------|
| UC-R1 | Listing PDF export | All | Parity with `meus_xml_pdf` family |
| UC-R2 | ICMS / fiscal reports | Contador | Align with `relatorio_icms`, `apuracao_icms` scope |
| UC-R3 | Excel exports including Fortes-oriented layouts | Contador | Feature-flag if layout-specific |
| UC-R4 | DANFE / print paths | Empresa | Integrate with maintained NFe lib; test modelo 55/65 |

### 4.7 Integration (API v2)

| ID | Use case | Actor | Acceptance notes |
|----|----------|--------|------------------|
| UC-I1 | OAuth2 / API keys per tenant | Integrator | Documented in OpenAPI |
| UC-I2 | Rate limits and usage metrics | Admin | Per plan |
| UC-I3 | Webhooks: document imported, validation failed | Integrator | Retry policy documented |

### 4.8 Compliance & operations

| ID | Use case | Actor | Acceptance notes |
|----|----------|--------|------------------|
| UC-C1 | Activity log (imports, deletes, exports) | Admin | Immutable append-only |
| UC-C2 | LGPD export & deletion workflow | Admin | PII in XML (e.g. CPF destinatário) |
| UC-C3 | Structured logs with request/tenant/job IDs | Ops | Retention policy |
| UC-C4 | Backups for XML blobs | Ops | Versioning or off-site replication |

---

## 5. Feature catalog (remake scope)

### 5.1 Parity with legacy (must-have for MVP)

- Auth, sessions, user types (empresa, funcionário, contador, usuário pattern → RBAC).
- **Empresa / filial / usuario / funcionário / contador** CRUD and scoping.
- **`xml` store**: metadata index + files in object storage (`xml/{cnpj}/{m-yy}/` pattern or equivalent key scheme).
- **Import**: `importa_xml*`, entrada pipelines, move from staging, upsert by chave.
- **`recepcao.php`** behavior as secure ingestion endpoint.
- **Listings**: `meus_xml` filters + SPED date cross-check (C100) as currently done.
- **API**: filtered JSON access — rebuilt with **no** inline keys; Sanctum/OAuth.
- **Download/view**: single and multi ZIP; `view_chave`; filial-scoped downloads.
- **SPED**: `upload_sped`, C100 parse into `sped`, conference PDF path.
- **Reports**: listing PDFs, CSV, ICMS-related exports, `gera_relatorios` equivalents.
- **Automation hooks**: lista filiais entrada, `atualiza_entrada`, certificate polling (reimplemented safely).
- **Manifestação** flows where present: `manifestadest.php` semantics.
- **CFOP** maintenance: `cfop.php` style backfills.
- **Logging/audit** baseline: replace file-only logs with structured + DB audit where needed.

### 5.2 Enhancements (post-MVP or phased)

From **FEATURE_SUGGESTIONS.md** (prioritize by segment):

- Dual validation pipeline (XSD + business rules); event timeline per document.
- Unified MDF-e / CT-e / NF-e 55 & 65 import with badges.
- Queue-based bulk import with S3/MinIO; Horizon if Redis.
- Full SPED import beyond C100; rich reconciliation dashboard.
- SSO, usage-based billing, white-label portal (optional product track).

---

## 6. Technical foundations

- **Backend**: Laravel 13, MySQL 8, Redis, Horizon (optional), Flysystem + S3-compatible storage.
- **Frontend**: **Livewire 3** *or* **Inertia + Vue** — pick one for the whole app (document the choice in ADR).
- **Testing**: Pest; contract tests for API; critical fiscal parsers covered by fixtures (sample XMLs).
- **Observability**: request ID middleware, tenant context in logs, error tracking hook (optional Sentry).

---

## 7. Phased roadmap

| Phase | Duration (indicative) | Deliverables |
|-------|------------------------|--------------|
| **0 — Foundation** | 2–4 weeks | Repo scaffold, auth, org/filial/user models, CI, staging env, design tokens + shell UI (sidebar, dashboard skeleton). |
| **1 — MVP parity** | 8–14 weeks | XML upload + ingestion endpoint, `xml` CRUD/list/filter, download/view, basic reports/PDF, SPED C100 upload + list cross-check, API v1 with proper keys, migration scripts from legacy schema export. |
| **2 — Trust & scale** | 4–8 weeks | Queued imports, duplicate/conflict UX, validation pipeline v2, activity log, webhook MVP, certificate rotation alerts, reconciliation MVP dashboard. |
| **3 — Depth** | ongoing | Extended SPED, event timelines, connectors (FTP/SFIEG), LGPD tooling, billing if product requires. |

Adjust timelines after DB schema export and pilot tenant volume.

---

## 8. Success criteria

- **Functional**: All **§5.1** use cases satisfied for at least one pilot tenant.
- **UX**: Dashboard and list pages share **same scope controls and metric definitions**; user testing: tasks completable without legacy training for core import/list/export; **all default UI copy and formats in pt-BR**.
- **Security**: No MD5 passwords; no SQL string concatenation for user input; secrets in env; API keys revocable per tenant.
- **Operability**: Restore drill documented; import job failures visible and retryable.

---

## 9. References

- `docs/MEMORY_LEGACY_MAISXML.md` — legacy feature map.
- `docs/FEATURE_SUGGESTIONS.md` — enhancements and quick wins vs investment table.

---

*Last updated: aligned with repo docs as of plan creation. Update phases when schema migration and pilot scope are fixed.*
