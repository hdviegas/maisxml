# ADR-001: Front-end stack (Mais XML remake)

## Status

Accepted — Phase 0 foundation.

## Context

The product needs server-driven CRUD, Portuguese (pt-BR) UI, and alignment with `docs/DEVELOPMENT_PLAN.md` (Livewire *or* Inertia + Vue).

## Decision

Use **Laravel Breeze** with **Livewire 3** (including Volt) for authentication and the application shell.

## Consequences

- **Positive**: Fast forms, fewer bespoke JS assets, good fit for fiscal listings and filters, matches plan option.
- **Negative**: Heavy real-time UX is harder than a dedicated SPA; if the team later standardizes on Vue only, evaluate Inertia migration.
- **Neutral**: Dark mode remains available via Breeze defaults; default experience targets light UI per plan.

## Alternatives considered

- **Inertia + Vue**: Strong for rich client tables; deferred until team preference or complex wizard flows require it.
- **Blade only**: Too much custom wiring for forms and validation compared to Livewire.
