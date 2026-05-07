# Suggestions — Mais XML platform (next generation)

This document proposes **functional and product enhancements** beyond a 1:1 port of the legacy system. Combine with `MEMORY_LEGACY_MAISXML.md` for parity checklist.

---

## 1. Fiscal & document core

- **Dual validation pipeline**: schema (XSD) + business rules (CFOP vs CFOP tables, CST/CSOSN consistency, ICMS vs totals).  
- **Event timeline per document**: cancellations, carta de correção, manifestação — store and display as an audit trail (legacy only partially leans on `cStat` / external events).  
- **MDF-e / CT-e / NF-e modelo 55 & 65** unified import with clear model badges and filters.  
- **Duplicate and conflict handling**: explicit UX when chave already exists (replace, skip, keep both with reason).  
- **Reprocess from XML**: rebuild DB row from file without re-upload for admin/support.

---

## 2. Integration & automation

- **REST API v2** with OAuth2 / API keys per tenant, **rate limits**, OpenAPI documentation, and webhook callbacks (“document imported”, “validation failed”).  
- **Replace raw `recepcao.php`** with HTTPS-only endpoint + HMAC or signed payloads for trusted emitters.  
- **Queue-based import**: S3/minio + Laravel queues for large batches; progress UI.  
- **SEFAZ / SIEG pulls** as scheduled jobs per filial with certificate rotation alerts.  
- **FTP/SFTP** as optional **connectors** with health checks and failure notifications (email/Slack).

---

## 3. SPED & reconciliation

- **Full SPED import** beyond C100 where useful (C190 for analytic cross-checks) with **reconciliation dashboard**: XML ↔ SPED matched / missing / value mismatch.  
- **Export** reconciliation reports for accountant (PDF/Excel) with filters by period and filial.  
- **Closing helpers**: “ período fechado ” flags to block edits during audit.

---

## 4. Multi-tenant & access control

- **Organizations → filiais → users** with RBAC (read vs import vs admin vs API-only).  
- **Impersonation** (support) with full audit log.  
- **SSO** optional (Google/Microsoft) for larger clients.

---

## 5. UX & productivity

- **Unified modern UI** (one design system — drop duplicate `template` / `temp_novo` split).  
- **Saved filters** and scheduled email reports.  
- **Global search** by chave, número NF, CNPJ, value range.  
- **Bulk actions**: download ZIP, tag, export CSV for selection.  
- **Mobile-friendly** listing and approvals where relevant.

---

## 6. Observability & compliance

- **Structured logging** (request id, tenant id, job id) + retention policy.  
- **Activity log** (who imported, deleted, exported what).  
- **LGPD**: data export & deletion request workflow for personal data in XML (CPF destinatário, etc.).  
- **Backup strategy** for XML blobs: versioning or off-site replication.

---

## 7. Technical platform choices (align with “recent stack”)

- **Laravel 12**, **PHP 8.4**, **MySQL 8**; **Redis** for queues/cache; **Laravel Horizon** if Redis.  
- **Laravel Sanctum** for SPA/mobile API; **Pest** for tests.  
- **Livewire 3** or **Inertia + Vue** for fast CRUD with fewer separate JS assets.  
- **Object storage** (`league/flysystem` + S3 driver) for XML files; DB only for index/metadata.  
- **Spatie/laravel-backup** or cloud-native backups.

---

## 8. Monetization & product (optional)

- **Usage-based billing** (API calls, storage GB, documents/month).  
- **Plan tiers**: importer-only vs full reconciliation + API.  
- **Client portal** for contadores with separate branding (white-label later).

---

## 9. Quick wins vs investment

| Quick win | Higher investment |
|-----------|---------------------|
| Env-based config, bcrypt, parameterized queries | Full event-store for all fiscal artifacts |
| Sanctum API + rate limits | Real-time SEFAZ sync |
| S3-backed storage | Multi-region DR |
| Reconciliation MVP (XML vs SPED C100) | Full SPED analytics suite |

---

*Prioritize by your user segment (SMB vs accounting firm vs integrator API).*
