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

# Pubblicare migrazioni dal wm-package
php artisan vendor:publish --tag=wm-package-migrations
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
- Migrazioni (da pubblicare con `--tag=wm-package-migrations`)

Quando si modifica il wm-package, ricordare che è condiviso tra progetti.

## Feature disponibili

| Feature | Ticket | Moduli toccati | Note |
|---|---|---|---|
| Import Layer: associazione EcPoi via taxonomy (theme/where/poi_type) | oc:8043 | `wm-package/src/Services/Import/GeohubImportService.php`, `wm-package/src/Jobs/Import/ImportLayerJob.php`, `wm-package/config/wm-geohub-import.php`, `wm-package/tests/Feature/GeohubImportServiceAssociateLayerPoiTest.php` | `associateLayersWithEcPoi()` traversa tutti e tre i meccanismi taxonomy; taxonomy_theme è il primario per app 63 e app 44 |
| Utenti importati: ruolo Editor in import GeoHub | oc:8042 | `database/migrations/2026_06_26_135156_zz_2026_06_26_000001_add_editor_role.php`, `wm-package/src/Services/Import/GeohubImportService.php`, `wm-package/src/Services/RolesAndPermissionsService.php` | Migration pubblicata da wm-package (`insertOrIgnore`); `assignEditorRole()` condizionale su `roles->isNotEmpty()` |
| Modifica ruolo utente in Nova | oc:8072 | `app/Nova/User.php`, `.env-example`, `tests/Feature/Nova/UserResourceRoleGuardTest.php` | Override `fields()` per `hideFromIndex()` su ruoli/permessi; guard via `WM_SUPER_ADMIN_EMAILS` |
| CI/CD GitHub Actions con deploy automatico e smoke test | oc:8082 | `.github/workflows/develop-deploy.yml`, `.github/workflows/prod-deploy.yml`, `.github/workflows/notify-slack.yml`, `.github/workflows/run-tests.yml`, `scripts/deploy_dev.sh`, `scripts/deploy_prod.sh`, `scripts/horizon_terminate_wait.sh`, `app/Listeners/CheckDatabaseHealth.php`, `app/Listeners/CheckCacheHealth.php`, `app/Providers/AppServiceProvider.php` | Pipeline CI/CD completa: tests → deploy SSH → smoke test (`/up` + `/login`) → notifica Slack `#zabbix-alerts`; listener `DiagnosingHealth` per check DB e cache su `/up` |

## Decisioni architetturali

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
