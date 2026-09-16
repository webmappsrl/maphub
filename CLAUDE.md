# maphub — CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Cos'è questo repo

`maphub` è un prodotto Laravel del team Webmapp, costruito sul package condiviso **`wm-package`**,
montato come submodule Git. Il package porta i modelli base, le risorse Nova, le policy di ruoli e
permessi, i comandi artisan e gli stub delle migration; qui vive la customizzazione di questo
prodotto — stub Nova, override, configurazione, pipeline.

Lo stesso package è montato da altri prodotti (camminiditalia, osm2cai2): **una modifica a
wm-package arriva anche a loro**, e ciò che vale solo per maphub resta qui.

Stack: Laravel 12, PHP 8.4, PostgreSQL + PostGIS, Nova 5, Elasticsearch 8, Redis, Horizon.

## Regole del repo

- **Le migration arrivano solo da git. Mai `vendor:publish` in deploy**, e mai
  `vendor:publish --force` in locale: il gate CI verifica lo schema reale del database, e uno
  publish sul server produce file che nessuno ha in git. `vendor:publish --tag=wm-package-migrations`
  serve solo al bootstrap iniziale (`install.sh`).
- **Prima wm-package, poi maphub.** Una modifica che attraversa i due repo si merge nel package per
  prima, poi qui con il bump del submodule e il cambiamento nello stesso commit. L'ordine invertito
  rompe cose che non sembrano collegate — il dettaglio è fra le trappole.
- **Ciò che riguarda il package si documenta nel package**, non qui: questo file dice come lo usiamo,
  come funziona lo dice `wm-package/CLAUDE.md`.

## Convenzioni

- **Gli ID dei ticket hanno la forma `oc:<numero>`** e vengono da Orchestrator. Ogni documento sotto
  `docs/features/` inizia con `> Ticket: oc:<ID>`, lo slug della cartella è
  `<ID>-<titolo-in-kebab-case>`, e lo scope dei commit porta il ticket: `fix(oc:<ID>): …`.
- **`docs/` ha tre destinazioni**: `features/` è il cantiere di un lavoro (immutabile),
  `knowledge/` la conoscenza per argomento, `howto/` le procedure. Le trappole non stanno in
  nessuna delle tre: stanno in `.claude/rules/`.
- **I nomi dei container usano il trattino come separatore**: `php-${APP_NAME}`,
  `postgres-${APP_NAME}`, `horizon-${APP_NAME}`, `minio-${APP_NAME}`. Nei workflow CI sono però
  scritti per esteso, non derivati da `${APP_NAME}` — vedi
  [docs/knowledge/cicd-e-deploy.md](docs/knowledge/cicd-e-deploy.md).
- **Documentazione, commenti e messaggi di commit sono in italiano**, i termini tecnici in inglese.

## Comandi

Come si mette in piedi l'ambiente la prima volta è in
[docs/howto/avviare-il-progetto.md](docs/howto/avviare-il-progetto.md).

| Cosa | Comando |
|---|---|
| Formattare | `composer format` |
| Ambiente locale completo (serve + horizon + pail + vite) | `composer dev` |
| Ambiente in Docker | `docker compose -f develop.compose.yml up -d` (con nginx) o `local.compose.yml` (standalone) |
| Entrare nel container PHP | `docker exec -it php-${APP_NAME} bash` |
| Un comando artisan senza entrare | `docker exec -it php-${APP_NAME} php artisan <comando>` |
| Test | `vendor/bin/pest`, oppure `--filter=<nome-test>` |
| Analisi statica | `vendor/bin/phpstan analyse` |
| Migration di wm-package, dopo un bump del submodule | `php artisan wm-package:publish-missing-migrations --dry-run`, poi senza `--dry-run`, poi `php artisan migrate` — e si committano i file in `database/migrations/` |


## Nova

### Gate

`NovaServiceProvider::gate()` definisce `viewNova` su un **permesso**, non su un ruolo:
```php
return $user->can('access-nova');
```
La logica del permesso vive in wm-package. Il perché in
[docs/knowledge/nova-ruoli-e-permessi.md](docs/knowledge/nova-ruoli-e-permessi.md).

### Menu

Il menu è strutturato per sezioni in `NovaServiceProvider::boot()`. Le sezioni Admin e Media sono visibili solo agli Administrator. Aggiungere nuove sezioni dopo quella Media.

## Ruoli e permessi

Il sistema usa `spatie/laravel-permission` tramite wm-package. **Guest non ha il permesso
`access-nova`**, ed è così che resta fuori dal pannello — non per il suo ruolo.

Le policy di Role e Permission sono registrate in `AppServiceProvider::boot()`; per aggiungerne una
di prodotto: `Gate::policy(MyModel::class, MyModelPolicy::class);`.

## MinIO e storage

MinIO simula S3 negli ambienti di sviluppo; endpoint, porte e credenziali stanno nei file compose e
in `.env-example`.

Il sistema di icone globale passa da `GlobalFileHelper` (wm-package), che tiene aggiornato
`icons.json` su MinIO.

## Testing e analisi statica

Il progetto usa **Pest**, configurato in `phpunit.xml`, che è anche dove vivono le variabili
d'ambiente di testing. **PHPStan** è a livello 5, con la configurazione in `phpstan.neon.dist` e la
baseline in `phpstan-baseline.neon`. I comandi sono sopra.

**I test girano sul database di sviluppo**: in `phpunit.xml` le righe `DB_CONNECTION` e
`DB_DATABASE` sono commentate, quindi vale la connessione del `.env`. È il motivo per cui si usa
`DatabaseTransactions` e mai `RefreshDatabase`, che quel database lo svuoterebbe — sta fra le
trappole.

## Dove sta wm-package

Il submodule vive in **`./wm-package`**, dichiarato in `composer.json` come repository di tipo
`path`; `vendor/wm/wm-package` è un link simbolico che composer crea verso quella cartella — non
una seconda copia. Si modifica sempre `./wm-package`.

Cosa fornisce il package — modelli base, risorse Nova, policy, comandi, stub delle migration — sta
nel suo `CLAUDE.md`.

## Conoscenza

| Argomento | Cosa copre | Ticket | Pagina |
|---|---|---|---|
| CI/CD e deploy | Pipeline, smoke test, nomi dei container, Horizon fra container | oc:8082 | [docs/knowledge/cicd-e-deploy.md](docs/knowledge/cicd-e-deploy.md) |
| Confini con wm-package | Cosa vive nel package, gli stub Nova, i test da non accoppiare | oc:8239, oc:8348 | [docs/knowledge/confini-con-wm-package.md](docs/knowledge/confini-con-wm-package.md) |
| Import da GeoHub | Associazione layer/POI via taxonomy, ruolo degli utenti importati | oc:8043, oc:8042 | [docs/knowledge/import-da-geohub.md](docs/knowledge/import-da-geohub.md) |
| Migration e stub | Il gate che guarda lo schema, i tre comandi, cosa fare caso per caso | oc:8218 | [docs/knowledge/migration-e-stub.md](docs/knowledge/migration-e-stub.md) |
| Elasticsearch | La sequenza di avvio e perché serve `elasticsearch-init` | — | [docs/knowledge/elasticsearch-e-avvio.md](docs/knowledge/elasticsearch-e-avvio.md) |
| Nova: accesso e visibilità | Il gate su `access-nova`, il menu UGC per ruolo, gli override sull'index | oc:8161, oc:8162, oc:8072 | [docs/knowledge/nova-ruoli-e-permessi.md](docs/knowledge/nova-ruoli-e-permessi.md) |

## Trappole

Stanno in `.claude/rules/wm-package-ordine-merge.md`, che si carica toccando `app/`,
`database/migrations/`, i workflow o gli script: l'ordine di merge fra i due repo, i test da non
accoppiare alle classi interne del package, l'override da ripetere negli altri consumer e
`DatabaseTransactions` al posto di `RefreshDatabase`.

## Lavori senza una pagina dedicata

| Lavoro | Ticket | In breve |
|---|---|---|
| Import GeoHub esteso a UGC, media e autori | oc:8158 | Qui è stata pubblicata solo la migration stub `Contributor`; il resto del lavoro vive in wm-package. `docs/features/8158-import-geohub-estensione-ugc-poi-track-media-e-utenti-autori-ugc/` |
