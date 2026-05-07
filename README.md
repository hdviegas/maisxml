# maisxml

Repository for the **Mais XML** project. Legacy source code and assets live in **`maisxml_old/`** for reference while the application is rebuilt on a modern PHP stack (see `docs/`).

## Layout

| Path | Description |
|------|-------------|
| **`maisxml_old/`** | Full snapshot of the previous PHP (PHPera) application — import pipelines, `control/`, `phpera/`, `static/`, etc. |
| **`docs/MEMORY_LEGACY_MAISXML.md`** | Feature and resource inventory of the legacy codebase (migration baseline). |
| **`docs/FEATURE_SUGGESTIONS.md`** | Product and technical suggestions for the new platform. |

## Git remote

This folder is intended to track [github.com/hdviegas/maisxml](https://github.com/hdviegas/maisxml).

```bash
git init
git remote add origin https://github.com/hdviegas/maisxml.git
git add .
git commit -m "chore: archive legacy app under maisxml_old; add docs"
git push -u origin main
```

Use branch name `main` or `master` according to your GitHub default.

## Security

Legacy code may contain **secrets** in `maisxml_old/phpera/config.php` (gitignored). Use `maisxml_old/phpera/config.example.php` as a template. The folder `maisxml_old/certificados/` is also gitignored. **Rotate any credentials** that were ever exposed.

## Next steps

- Scaffold Laravel (or chosen stack) in the **repository root** next to `maisxml_old/`, or move legacy to a separate branch only — team choice.  
- Export production DB schema for accurate Eloquent migrations.  
- Implement parity using `docs/MEMORY_LEGACY_MAISXML.md` as the checklist.
