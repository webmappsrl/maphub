> Ticket: oc:8158

# Piano implementativo — lato Maphub

Vedi `wm-package/docs/features/8158-import-geohub-estensione-ugc-poi-track-media-e-utenti-autori-ugc/plan.md` per tutto il lavoro sostanziale.

## 1. Publish migration Contributor (dopo il merge della PR wm-package)

- Bump del submodule pointer `wm-package` alla PR mergiata
- `php artisan wm-package:publish-missing-migrations --dry-run` per verificare che venga rilevata la nuova migration
- `php artisan wm-package:publish-missing-migrations` per pubblicarla in `database/migrations/`
- `php artisan migrate` in locale per verificarne l'applicazione
- Commit del file pubblicato

**Commit:** `feat(oc:8158): publish Contributor role migration from wm-package`

Nessun altro file applicativo Maphub previsto da questa feature.
