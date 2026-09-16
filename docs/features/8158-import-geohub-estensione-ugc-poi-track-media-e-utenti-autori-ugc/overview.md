> Ticket: oc:8158

# Import GeoHub: estensione UGC (POI, Track, Media) e utenti autori UGC — lato Maphub

## Cosa cambia

Tutta la logica di import (job, config, ruolo Contributor, modelli) vive in `wm-package` — vedi `wm-package/docs/features/8158-import-geohub-estensione-ugc-poi-track-media-e-utenti-autori-ugc/overview.md` per i dettagli. Lato Maphub l'unico effetto è la pubblicazione della migration stub del nuovo ruolo `Contributor` (`wm-package:publish-missing-migrations`).

Nessuna nuova risorsa Nova, nessun nuovo file applicativo: la proposta iniziale del ticket prevedeva una risorsa Nova `UgcMedia` in Maphub (stub che estende `Wm\WmPackage\Nova\UgcMedia`), ma è stata eliminata insieme al modello `UgcMedia` stesso (vedi overview wm-package, sezione "Cosa cambia").

## Perché

Vedi overview wm-package.

## Requisiti

- [ ] Dopo il merge della PR wm-package e il bump del submodule pointer, eseguire `php artisan wm-package:publish-missing-migrations` e committare la migration stub `Contributor` generata in `database/migrations/`
- [ ] Nessuna modifica a `app/Nova/*` o `NovaServiceProvider` è prevista da questo ticket

## Rischi

Nessuno specifico lato Maphub — i rischi rilevanti sono tutti descritti nell'overview wm-package.

## Out of scope

Vedi overview wm-package.

## Moduli toccati

- `database/migrations/` — pubblicazione dello stub `Contributor` da wm-package (nessun file scritto a mano)
