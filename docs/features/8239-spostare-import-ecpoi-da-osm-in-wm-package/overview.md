> Ticket: oc:8239

# Spostare import EcPoi da OSM in wm-package

## Cosa cambia

Rimozione completa della logica business dell'import EcPoi da OSM da Maphub, ora spostata in wm-package (vedi `wm-package/docs/features/8239-spostare-import-ecpoi-da-osm-in-wm-package/overview.md`). In Maphub resta solo:

- `app/Nova/EcPoi.php` semplificato a stub vuoto (`class EcPoi extends WmNovaEcPoi {}`) — l'action arriva di default dal package, nessun override necessario
- Rimozione di `config/osm-import.php`, delle env var `OSM_IMPORT_*` da `.env`/`.env-example`
- Rimozione delle chiavi OSM da `lang/{it,en,fr,es,de}.json`
- Rimozione della directory vuota `resources/views/osm/`
- Un test feature minimo di smoke che verifica che `ImportEcPoiFromOsm` sia presente tra le action disponibili su `App\Nova\EcPoi` per l'app corrente

## Perché

Con la logica trasferita in wm-package, Maphub non deve più contenere business logic duplicata per questa feature (coerente con l'obiettivo di riuso cross-progetto del ticket oc:8239).

## Requisiti

- [ ] `app/Nova/Actions/ImportEcPoiFromOsm.php` rimosso
- [ ] `app/Services/Osm/*` rimossi (6 file: `OsmPoiImporter`, `ImportReport`, `OsmTaxonomyPoiTypeResolver`, `OsmImportReportPresenter`, `OsmImportReportStore`)
- [ ] `app/Dto/OsmNodePoiData.php`, `app/Dto/OsmEcPoiPropertiesData.php` rimossi
- [ ] `app/Console/Commands/ImportEcPoiFromOsmCommand.php` rimosso
- [ ] `app/Http/Controllers/OsmImportReportController.php` rimosso
- [ ] `resources/views/nova/osm-import-report.blade.php` rimosso
- [ ] `resources/views/osm/` (directory vuota) rimossa
- [ ] `config/osm-import.php` rimosso, env var `OSM_IMPORT_REQUEST_DELAY_MS`/`OSM_IMPORT_MAX_IDS_PER_RUN` rimosse da `.env`/`.env-example`
- [ ] Blocco route `osm.import.report` rimosso da `routes/web.php`
- [ ] Chiavi OSM rimosse da `lang/it.json`, `lang/en.json`, `lang/fr.json`, `lang/es.json`, `lang/de.json`
- [ ] `app/Nova/EcPoi.php` semplificato a stub vuoto senza override di `actions()`
- [ ] `tests/Unit/OsmPoiImportTest.php`, `tests/Feature/OsmImportReportRouteTest.php` rimossi da Maphub
- [ ] Nuovo test feature smoke: `ImportEcPoiFromOsm` presente tra le action Nova disponibili su `EcPoi` dopo l'aggiornamento del submodule wm-package
- [ ] `composer.json` / require su wm-package aggiornato alla versione che include la feature (dopo merge lato package)

## Rischi

Rimando a `wm-package/docs/features/8239-spostare-import-ecpoi-da-osm-in-wm-package/overview.md` per i rischi di logica/architettura (visibilità Administrator, isolamento multi-app, User-Agent OSM, traduzioni, rate limiting). Rischi specifici a questo repo:

- **Sequenza vincolante tra i due repo**: la rimozione del codice in Maphub va eseguita **solo dopo** che wm-package è stato mergiato/taggato e `composer.json` aggiornato. Eseguire i task Maphub prima lascia il progetto senza alcuna azione di import OSM funzionante (finestra di rottura visibile). Mitigazione: ordine esplicito in `plan.md`, verifica del bump versione prima di ogni rimozione file.
- **Rollback non è un semplice `git revert` isolato**: un revert del commit Maphub ripristina i file locali, ma se il submodule wm-package nel frattempo è avanzato con altri commit non correlati alla feature, un rollback del pointer a una versione precedente rischia di retrocedere anche funzionalità non correlate già in produzione. Mitigazione: se serve un rollback, preferire un revert mirato del solo bump di versione/pointer (non un downgrade a un commit arbitrario precedente) e verificare il changelog del package tra le due versioni.
- **Rollback delle traduzioni rimosse è manuale**: una volta eliminate le chiavi OSM da `lang/{it,en,fr,es,de}.json`, ripristinarle (in caso di rollback o ripensamento su fr/es/de) richiede un intervento manuale sui file JSON, non è automatizzabile da git revert se nel frattempo altri commit hanno toccato gli stessi file.
- **Env var rimosse da `.env-example` ma non necessariamente da `.env` di prod**: la rimozione locale non garantisce che il server abbia già aggiornato le variabili — vedi nota tecnica corrispondente nell'overview wm-package.

## Out of scope

- Qualsiasi modifica alla logica di business dell'import OSM (invariata, solo spostata)
- Modifiche ad altre Nova Resource o Action di Maphub

## Moduli toccati

**Rimossi da Maphub:**
- `app/Nova/Actions/ImportEcPoiFromOsm.php`
- `app/Services/Osm/OsmPoiImporter.php`
- `app/Services/Osm/ImportReport.php`
- `app/Services/Osm/OsmTaxonomyPoiTypeResolver.php`
- `app/Services/Osm/OsmImportReportPresenter.php`
- `app/Services/Osm/OsmImportReportStore.php`
- `app/Dto/OsmNodePoiData.php`
- `app/Dto/OsmEcPoiPropertiesData.php`
- `app/Console/Commands/ImportEcPoiFromOsmCommand.php`
- `app/Http/Controllers/OsmImportReportController.php`
- `resources/views/nova/osm-import-report.blade.php`
- `resources/views/osm/` (directory vuota)
- `config/osm-import.php`
- `tests/Unit/OsmPoiImportTest.php`
- `tests/Feature/OsmImportReportRouteTest.php`
- Blocco route in `routes/web.php` (righe 1-14, `osm.import.report`)
- Chiavi OSM in `lang/{it,en,fr,es,de}.json`

**Modificati in Maphub:**
- `app/Nova/EcPoi.php` — semplificato a stub vuoto
- `.env`, `.env-example` — rimosse env var `OSM_IMPORT_*`
- `composer.json` — versione wm-package aggiornata
- `wm-package` (submodule pointer) — aggiornato al commit con la feature

**Nuovi file in Maphub:**
- Test feature smoke sull'integrazione (path da definire in Fase: write-plan, es. `tests/Feature/Nova/EcPoiOsmImportActionAvailableTest.php`)
