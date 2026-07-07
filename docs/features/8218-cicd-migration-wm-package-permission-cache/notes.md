> Ticket: oc:8218

# Notes — CI/CD: gate migration wm-package + invalidazione cache permessi

## Deviazioni dal ticket originale

| Ticket chiedeva | Implementato |
|-----------------|----------------|
| `vendor:publish --tag=wm-package-migrations` in deploy | **No** — file orfani sul server |
| Gate CI | **Sì** — `publish-missing-migrations --dry-run` nel job `tests`, stesso DB |
| `permission:cache-reset` dopo migrate | **Sì** |
| — | **`publish-missing-migrations`** — check semantico DB (evoluzione post-review) |

Usare `overview.md` come riferimento unico.

## Scoperte in review

### `create_users_table` — falso positivo da suffisso

- `0001_..._create_users_table.php` è Laravel (`Schema::create`), non lo stub wm-package
- Stub aggiunge `balance`, `fiscal_code`, `app_id` — assenti su maphub
- `--dry-run` segnala correttamente; va pubblicata la migration wm-package vera

### Stub obbligatori

- Non opzionali — il gate verifica lo schema reale sul DB

## Decisioni

- Gate = schema DB dopo `migrate`, stesso PostGIS dei test
- Due comandi: `publish-missing-migrations` (+ `--dry-run`) e `publish-migration`
- Risoluzione sempre locale → git; mai publish sul server
- `vendor:publish --force` bandito

## Follow-up

- oc:8228 — boilerplate
- maphub: pubblicare e migrare `create_users_table`
- Altri shard: ticket separati
