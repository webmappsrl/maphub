# Laravel PostGIS Boilerplate — CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Stack: Laravel 12, PHP 8.4, PostgreSQL + PostGIS, Nova 5, Elasticsearch 8, Redis, Horizon.

## Comandi utili

```bash
# Formattazione codice
composer format

# Avvio ambiente locale completo (serve + horizon + pail + vite)
composer dev

# Entrare nel container PHP
docker exec -it php-${APP_NAME} bash

# Eseguire un comando artisan senza entrare nel container
docker exec -it php-${APP_NAME} php artisan <comando>

# Test (Pest)
vendor/bin/pest
vendor/bin/pest --filter=<nome-test>

# PHPStan
vendor/bin/phpstan analyse

# Migration wm-package (dopo aggiornamento submodule)
php artisan wm-package:publish-missing-migrations --dry-run
php artisan wm-package:publish-missing-migrations
php artisan migrate
# Poi committare i file in database/migrations/
```

## Setup progetto

Usare lo script `scripts/install.sh` per l'installazione guidata completa, oppure manualmente:

```bash
# 1. Configurare .env (copiare da .env-example)
cp .env-example .env
# Modificare: APP_NAME, DOCKER_PHP_PORT, DOCKER_PROJECT_DIR_NAME

# 2. Avviare Docker
bash docker/init-docker.sh

# 3. Installare dipendenze e configurare Laravel
docker exec -it php-${APP_NAME} composer install
docker exec -it php-${APP_NAME} php artisan key:generate
docker exec -it php-${APP_NAME} php artisan optimize
# Bootstrap iniziale migration wm-package (solo prima installazione; vedi install.sh)
docker exec -it php-${APP_NAME} php artisan vendor:publish --tag=wm-package-migrations
docker exec -it php-${APP_NAME} php artisan migrate

# 4. Creare ruoli base
docker exec -it php-${APP_NAME} php artisan tinker --execute="
foreach (['Administrator', 'Editor', 'Validator', 'Guest'] as \$name) {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => \$name, 'guard_name' => 'web']);
}
"

# 5. Creare utente Administrator
docker exec -it php-${APP_NAME} php artisan nova:user
```

## Ambienti Docker

Il progetto ha tre file compose con scopi distinti:

| File | Scopo |
|------|-------|
| `compose.yml` | Base condivisa (prod). Non si usa direttamente. |
| `develop.compose.yml` | Sviluppo locale con nginx/proxy. Aggiunge minio, mailpit. |
| `local.compose.yml` | Sviluppo locale standalone con `php artisan serve`. Aggiunge scout-init, kibana, laravel server. |

```bash
# Produzione
docker compose up -d

# Sviluppo (con nginx)
docker compose -f develop.compose.yml up -d

# Sviluppo standalone (senza nginx)
docker compose -f local.compose.yml up -d
```

### Convenzioni container name

I container usano trattino come separatore: `php-${APP_NAME}`, `postgres-${APP_NAME}`, `horizon-${APP_NAME}`, `minio-${APP_NAME}`.

## Stack Elasticsearch

La sequenza di avvio è: `elasticsearch` → `elasticsearch-init` → `kibana`.

`elasticsearch-init` è un container curl one-shot che imposta la password di `kibana_system` via API (Elasticsearch non supporta variabili d'ambiente per questo utente). Si rimuove automaticamente dopo l'esecuzione.

`scout-init` (solo `local.compose.yml`) esegue `scout:import` sui modelli di wm-package dopo che Elasticsearch e il database sono pronti.

Variabili `.env` rilevanti:
```
ELASTICSEARCH_HOST=elasticsearch:9200
ELASTICSEARCH_USER=elastic
ELASTICSEARCH_PASSWORD=changeme
ELASTICSEARCH_SSL_VERIFICATION=false
DOCKER_KIBANA_PORT=5601
```

## Nova

### Gate

`NovaServiceProvider::gate()` blocca i Guest da Nova:
```php
return !$user->hasRole('Guest');
```

### Menu

Il menu è strutturato per sezioni in `NovaServiceProvider::boot()`. Le sezioni Admin e Media sono visibili solo agli Administrator. Aggiungere nuove sezioni dopo quella Media.

### Traits disponibili (`app/Nova/Traits/`)

- `FiltersUsersByRoleTrait` — filtra gli utenti relatibili per ruolo (Administrator/Validator)
- `HidesAppFromIndexTrait` — nasconde il campo `app` dalla lista index

### Footer

Il footer Nova viene renderizzato da `resources/views/nova/footer.blade.php` e mostra: nome app, versione, environment, versioni di Nova/Laravel/PHP.

### Estensione risorse wm-package

Le risorse Nova nel progetto estendono quelle del wm-package. Pattern:
```php
namespace App\Nova;

use Wm\WmPackage\Nova\App as WmNovaApp;

class App extends WmNovaApp {}
```

Questo permette di personalizzare label, campi, o aggiungere funzionalità mantenendo la logica base nel package.

### Impersonate (oc:8231)

Il trait nativo `Laravel\Nova\Auth\Impersonatable` è abilitato su `Wm\WmPackage\Models\User` (non su `app/Models/User.php`, che eredita tutto). Solo gli Administrator possono impersonare (ruolo hardcoded, nessuna config per consumer). Un Administrator **può** impersonare un altro Administrator; l'unico requisito per essere impersonabili è avere il permesso `access-nova` (esclude Guest, che altrimenti lascerebbe l'admin bloccato senza poter fare "Stop impersonating"). Nessun log/audit trail per start/stop — rifiutato esplicitamente in review dal CTO (se servirà, va introdotto trasversalmente nel package, non ad hoc per questa feature). Dettagli completi in `wm-package/docs/features/8231-aggiungere-impersonate/`.

**Gotcha nota — falso "redirect al login" cliccando Impersonate dalla pagina Detail (non dall'Index): è un bug di Laravel Nova, non del codice di questo progetto.**

Doppia esclusione del codice applicativo:
1. Riprodotto via HTTP reale (login → detail page → impersonate → redirect) sia da Index sia da Detail: il backend risponde sempre 200 e la sessione passa correttamente all'utente impersonato in entrambi i casi — non è un problema di autorizzazione o sessione lato applicazione.
2. La causa vive interamente dentro `vendor/laravel/nova` (pacchetto di terze parti, non modificato in questo progetto): `resources/views/layout.blade.php` stampa il tag `<meta name="csrf-token">` una volta al caricamento pagina, e `resources/js/bootstrap/axios.js` lo legge **una sola volta** all'avvio della SPA senza mai rinfrescarlo durante la navigazione client-side. Se il token in memoria è stantio, la prima chiamata AJAX che riceve 401 attiva il redirect automatico al login di Nova.

Confermato come comportamento noto e **mai risolto upstream** — segnalazioni identiche sul bug tracker ufficiale `laravel/nova-issues` sono state chiuse dal team Laravel/Nova come "not planned"/stale:
- [laravel/nova-issues#5773 — "Users can't be impersonated more than once per session/back-to-back"](https://github.com/laravel/nova-issues/issues/5773)
- [laravel/nova-issues#6082 — "Invalid sessions when impersonate a user for the second time..."](https://github.com/laravel/nova-issues/issues/6082)
- [Spiegazione tecnica del pattern "meta tag CSRF stantio dopo un cambio di sessione SPA" (dev.to)](https://dev.to/vsimke/why-your-laravel-inertiajs-fetch-requests-fail-with-419-after-save-3lg4)

**Risolto** evitando di esporre il punto d'ingresso che lo attiva (non il bug in sé, impossibile da correggere senza patchare Nova): `Wm\WmPackage\Nova\AbstractUserResource::authorizedToImpersonate()` restituisce `false` quando `$request->isResourceDetailRequest()`, nascondendo il bottone "Impersonate" dalla pagina Detail. Dall'Index resta disponibile — è l'unico contesto in cui il bug non si presenta. Verificato via HTTP reale e con test dedicato (`wm-package/tests/Feature/Nova/AbstractUserResourceImpersonateTest.php`). Il reload completo della pagina resta un fallback valido per qualunque altra azione Nova che cambi sessione/utente al di fuori di questo meccanismo, ma non è più necessario per impersonate nell'uso normale.

## Ruoli e Permessi

Il sistema usa spatie/laravel-permission tramite wm-package. Ruoli predefiniti:
- **Administrator** — accesso completo a Nova, gestione utenti e app
- **Editor** — creazione e modifica contenuti
- **Validator** — validazione UGC
- **Guest** — solo lettura, NO accesso a Nova (bloccato dal gate)

Le policy di Role e Permission sono registrate in `AppServiceProvider::boot()`. Per aggiungere policy progetto-specifiche:
```php
Gate::policy(MyModel::class, MyModelPolicy::class);
```

## PHPStan

```bash
vendor/bin/phpstan analyse
```

Configurazione in `phpstan.neon.dist`. La baseline è `phpstan-baseline.neon`. Livello 5.

## MinIO e Storage

MinIO è disponibile negli ambienti di sviluppo per simulare S3. Endpoint: `http://localhost:${FORWARD_MINIO_PORT}` (default 9000). Console: port 8900.

Credenziali default: `laravel` / `laravelminio`. Bucket: `wmfe`.

Il sistema di icone globale è gestito tramite `GlobalFileHelper` (wm-package) che carica e mantiene aggiornato `icons.json` in MinIO.

## Testing

Il progetto usa Pest. Configurazione in `phpunit.xml`:
```bash
# Eseguire tutti i test
vendor/bin/pest

# Eseguire un file specifico
vendor/bin/pest tests/Feature/EsempioTest.php

# Con filtro
vendor/bin/pest --filter=nome_test
```

Variabili d'ambiente di testing definite in `phpunit.xml`.

## wm-package (submodule)

Il progetto dipende da `wm/wm-package` come path repository (submodule Git in `../wm-package` o `vendor/wm/wm-package`).

Il package fornisce:
- Modelli base (User, EcTrack, EcPoi, UgcTrack, UgcPoi, Layer, App)
- Risorse Nova base
- Policy Role/Permission
- Comandi artisan personalizzati
- Migrazioni stub obbligatori — workflow: `wm-package:publish-missing-migrations` (vedi sezione oc:8218). `vendor:publish --tag=wm-package-migrations` solo bootstrap iniziale (`install.sh`), mai in deploy

Quando si modifica il wm-package, ricordare che è condiviso tra progetti.

## Feature disponibili

| Feature | Ticket | Moduli toccati | Note |
|---|---|---|---|
| Aggiungere impersonate su Nova | oc:8231 | `wm-package/src/Models/User.php`, `wm-package/src/Nova/AbstractUserResource.php` | Trait nativo Nova `Impersonatable`; solo Administrator possono impersonare (hardcoded), admin-su-admin ammesso, target richiede `access-nova`. Nessun log/audit trail (rifiutato dal CTO in review). Vedi sezione `## Nova → Impersonate` per dettagli e gotcha CSRF Detail-page |
| Import Layer: associazione EcPoi via taxonomy (theme/where/poi_type) | oc:8043 | `wm-package/src/Services/Import/GeohubImportService.php`, `wm-package/src/Jobs/Import/ImportLayerJob.php`, `wm-package/config/wm-geohub-import.php`, `wm-package/tests/Feature/GeohubImportServiceAssociateLayerPoiTest.php` | `associateLayersWithEcPoi()` traversa tutti e tre i meccanismi taxonomy; taxonomy_theme è il primario per app 63 e app 44 |
| Utenti importati: ruolo Editor in import GeoHub | oc:8042 | `database/migrations/2026_06_26_135156_zz_2026_06_26_000001_add_editor_role.php`, `wm-package/src/Services/Import/GeohubImportService.php`, `wm-package/src/Services/RolesAndPermissionsService.php` | Migration pubblicata da wm-package (`insertOrIgnore`); `assignEditorRole()` condizionale su `roles->isNotEmpty()` |
| Modifica ruolo utente in Nova | oc:8072 | `app/Nova/User.php`, `.env-example`, `tests/Feature/Nova/UserResourceRoleGuardTest.php` | Override `fields()` per `hideFromIndex()` su ruoli/permessi; guard via `WM_SUPER_ADMIN_EMAILS` |
| CI/CD GitHub Actions con deploy automatico e smoke test | oc:8082 | `.github/workflows/develop-deploy.yml`, `.github/workflows/prod-deploy.yml`, `.github/workflows/notify-slack.yml`, `.github/workflows/run-tests.yml`, `scripts/deploy_dev.sh`, `scripts/deploy_prod.sh`, `scripts/horizon_terminate_wait.sh`, `app/Listeners/CheckDatabaseHealth.php`, `app/Listeners/CheckCacheHealth.php`, `app/Providers/AppServiceProvider.php` | Pipeline CI/CD completa: tests → deploy SSH → smoke test (`/up` + `/login`) → notifica Slack `#zabbix-alerts`; listener `DiagnosingHealth` per check DB e cache su `/up` |
| CI/CD: gate migration wm-package + invalidazione cache permessi | oc:8218 | `.github/workflows/run-tests.yml`, `.github/workflows/develop-deploy.yml`, `.github/workflows/prod-deploy.yml`, `scripts/deploy_dev.sh`, `scripts/deploy_prod.sh`, `wm-package/src/Commands/WmPackage{PublishMigration,PublishMissingMigrations}Command.php` | Gate CI: `publish-missing-migrations --dry-run` dopo migrate (stesso DB dei test). Deploy: `migrate` + `permission:cache-reset`, mai `vendor:publish` |

## Decisioni architetturali

### CI/CD: gate migration wm-package + invalidazione cache permessi (oc:8218)

**Fonte di verità:** `docs/features/8218-cicd-migration-wm-package-permission-cache/overview.md` (diagrammi mermaid + casi d'uso A–H).

**Principi:**
- Stub wm-package **obbligatori** — il gate verifica lo **schema DB reale**, non suffissi file né mappature manuali
- Migration solo via **git** — mai `vendor:publish` in deploy; mai `vendor:publish --force` in locale
- Risoluzione sempre **locale → commit → push** — gli script deploy non generano file

**Pipeline CI** (job `tests` in `run-tests.yml`, stesso DB PostGIS dei test):
```
migrate → publish-missing-migrations --dry-run → php artisan test → deploy (se passa)
```
`deploy` dipende solo da `tests`. Deploy: `migrate --force` → `permission:cache-reset` (sempre, incondizionato).

**Comandi attivi (solo 2):**
| Comando | Ruolo |
|---------|--------|
| `wm-package:publish-missing-migrations --dry-run` | Gate CI e verifica locale; **exit 1** se stub non allineati |
| `wm-package:publish-missing-migrations` | Pubblica file mancanti in `database/migrations/` |
| `wm-package:publish-migration <stub>` | Publish singolo stub; ignora falsi positivi da suffisso |

**Logica gate per stub** (dopo `migrate`):
1. Schema DB completo rispetto allo stub → allineato (anche senza file wm-package, se un'altra migration ha lo stesso effetto)
2. File committato **identico** allo stub ma non in tabella `migrations` → exit 1, esegui `migrate`
3. Gap schema e nessun file identico → exit 1, pubblica migration wm-package

**Caso noto maphub:** `create_users_table` — `0001_..._create_users_table.php` è Laravel (`Schema::create`); stub wm-package aggiunge `balance`/`fiscal_code`/`app_id` via `Schema::table`. Suffisso uguale ≠ schema allineato.

**Casi d'uso rapidi per agenti:**
| Scenario | Azione |
|----------|--------|
| Push normale, tutto allineato | `--dry-run` exit 0 → CI passa |
| Nuovo stub dopo update wm-package | `publish-missing-migrations` → `migrate` → commit |
| `--dry-run` segnala stub + gap colonne | Pubblica migration wm-package, non basta il file Laravel omonimo |
| File identico in git, non migrato | Solo `php artisan migrate` |
| Schema già ok via migration custom (nome diverso) | Nessuna azione |
| CI fallisce | Fix in locale, mai publish sul server |


### Import Layer: associazione EcPoi via taxonomy (oc:8043)
- `associateLayersWithEcPoi()` controlla in sequenza `taxonomy_themeables`, `taxonomy_whereables` e `taxonomy_poi_typeables` — **taxonomy_theme è il meccanismo primario** per app 63 e app 44 (poi per layer: 4–48 su 63, 101–109 su 44)
- GeoHub non ha pivot diretta Layer→EcPoi: la relazione è indiretta via taxonomy condivise tra Layer e EcPoi
- I geohub_poi_id vengono deduplicati prima dell'`attach()` per gestire POI trovati da più meccanismi contemporaneamente
- `attach()` con check `alreadyExists` garantisce idempotenza sul re-import

### Utenti importati: ruolo Editor (oc:8042)
- `GeohubImportService::assignEditorRole()` assegna `Editor` solo se `$user->roles->isEmpty()` — non sovrascrive ruoli manuali
- Migration usa `insertOrIgnore` invece di `Role::firstOrCreate` per evitare side-effect cache Spatie in transazione PostgreSQL
- `assignAdministratorRole()` conservato per retrocompatibilità ma non più usato in `checkUserExistence()`

### Modifica ruolo utente in Nova (oc:8072)
- `app/Nova/User.php` estende `AbstractUserResource` (wm-package) — non ridefinisce i campi base, li eredita tutti
- Override `fields()` locale aggiunge `hideFromIndex()` su `RoleBooleanGroup` e `PermissionBooleanGroup` — il package resta agnostico sulla visibilità nell'index
- `DatabaseTransactions` invece di `RefreshDatabase` nei test: `phpunit.xml` non configura un DB separato, `RefreshDatabase` svuoterebbe il DB di dev
- Gli altri shard che aggiornano wm-package devono fare lo stesso override `hideFromIndex()` in `User.php`, altrimenti i campi ruolo/permessi appaiono come colonne nell'index

### CI/CD GitHub Actions (oc:8082)
- Container name hardcoded (`php-maphub`, `php-maphubdev`): pattern Webmapp confermato da camminiditalia — non usare `${APP_NAME}` che dipende dall'env del server
- `horizon:terminate` usa Redis come canale di comunicazione, funziona correttamente tra container separati (`php-maphub` e `horizon-maphub`)
- Smoke test aggiunge `sleep 15` prima dei curl per evitare falsi negativi da connection pool esaurito post-migrate
- Loop attesa Horizon estratto in `scripts/horizon_terminate_wait.sh` e sourcato da entrambi i deploy script
- Job Slack estratto in workflow riusabile `notify-slack.yml` per evitare duplicazione

## Migration wm-package (stub obbligatori)

Gli stub in `wm-package/database/migrations/*.stub` **non sono opzionali**. Dettaglio completo: `docs/features/8218-cicd-migration-wm-package-permission-cache/overview.md`.

### Workflow (dopo aggiornamento wm-package)

```bash
php artisan wm-package:publish-missing-migrations --dry-run   # = gate CI
php artisan wm-package:publish-missing-migrations             # se exit 1
php artisan migrate
git add database/migrations/ && git commit
php artisan wm-package:publish-missing-migrations --dry-run   # verifica
```

### Se `--dry-run` fallisce (exit 1)

Risoluzione **sempre in locale → git**, mai sul server:

1. Leggi `wm-package/database/migrations/<nome>.php.stub`
2. Cerca migration equivalente in `database/migrations/` (per **contenuto/schema**, non solo nome)
3. **Schema già allineato sul DB** → nessuna azione (se fallisce comunque, verifica `migrate`)
4. **Gap sul DB** → `publish-migration <stub>` o `publish-missing-migrations` + `migrate` + commit
5. **File identico committato ma non migrato** → `php artisan migrate`
