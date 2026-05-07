# maisxml

Repository for the **Mais XML** project. The legacy PHP application is **not** committed: keep it locally in **`maisxml_old/`** (see `.gitignore`) for side‑by‑side reference while you rebuild on a modern stack. **`docs/MEMORY_LEGACY_MAISXML.md`** remains the written baseline of old behavior.

## Layout

| Path | Description |
|------|-------------|
| **`maisxml_old/`** (local only) | Snapshot of the previous PHP (PHPera) app — present on your machine if you kept it; clone from Git will not include it. |
| **`docs/MEMORY_LEGACY_MAISXML.md`** | Feature and resource inventory of the legacy codebase (migration checklist). |
| **`docs/FEATURE_SUGGESTIONS.md`** | Product and technical suggestions for the new platform. |

## Git remote

This folder tracks [github.com/hdviegas/maisxml](https://github.com/hdviegas/maisxml).

```bash
git remote add origin https://github.com/hdviegas/maisxml.git   # if needed
git push -u origin main
```

## Security

The entire **`maisxml_old/`** directory is gitignored (code, configs, certificates). **Rotate credentials** if they were ever pushed in history or shared outside this repo.

## Next steps

- Scaffold Laravel (or chosen stack) in the **repository root**.
- Export production DB schema for accurate migrations.
- Implement parity using `docs/MEMORY_LEGACY_MAISXML.md`.
