> Ticket: oc:8239

# Rimozione import EcPoi da OSM da Maphub — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rimuovere da Maphub tutta la logica di import EcPoi da OSM ora disponibile in wm-package, lasciando solo lo stub Nova Resource e un test di smoke sull'integrazione.

**Architecture:** Rimozione file + bump submodule/composer + verifica che l'azione arrivi automaticamente dal package (nessun override applicativo necessario, vedi `wm-package/src/Nova/EcPoi.php::actions()`).

**Tech Stack:** Laravel 12, PHP 8.4, Nova 5, Pest.

## Global Constraints

<HARD-GATE>
Nessun task di questo piano può essere eseguito prima che il piano `wm-package/docs/features/8239-spostare-import-ecpoi-da-osm-in-wm-package/plan.md` sia completo, il branch wm-package sia mergiato, e il pointer del submodule punti a un commit che contiene la feature. Eseguire i task di rimozione prima lascia Maphub senza alcuna azione di import OSM funzionante (rischio individuato in Fase: challenge).
</HARD-GATE>

- Namespace nel resto del codice Maphub che referenziava `App\Services\Osm\*`, `App\Dto\Osm*`: nessuno (verificato — questi namespace sono usati solo dai file rimossi in questo piano, vedi Task 3 Step 1).
- Comandi test: `docker exec -it php-maphub php artisan test <path>` oppure `docker exec -it php-maphub vendor/bin/pest <path>`.
- Nessun commit va eseguito da chi esegue questo piano in modalità autonoma senza revisione umana.

---

### Task 1: Bump submodule wm-package + composer.json

**Files:**
- Modify: `composer.json` (constraint versione wm-package, se presente un vincolo di versione esplicito — verificare)
- Modify: `wm-package` (submodule pointer)

- [ ] **Step 1: Verificare che il branch wm-package con la feature sia mergiato**

Run: `cd wm-package && git log --oneline -5 origin/main` (o il branch di integrazione del package)
Expected: l'ultimo commit rilevante è quello di Task 11 Step 4 del piano wm-package (`docs(oc:8239): document OSM import migration in wm-package CLAUDE.md`) o successivo, e il branch feature è stato mergiato.

Se non è così: **fermarsi qui**, non procedere con i task successivi.

- [ ] **Step 2: Verificare il vincolo di versione in `composer.json`**

Run: `grep -A2 '"wm/wm-package"' composer.json`
Expected: mostra il require attuale (path repository o versione). Se è un path repository locale (`"type": "path"`), nessun bump di versione è necessario: il submodule pointer aggiornato è sufficiente. Se è una versione semver, annotare il valore attuale per lo Step 4.

- [ ] **Step 3: Aggiornare il submodule pointer**

```bash
cd wm-package
git checkout main   # o il branch di integrazione dopo il merge
git pull
cd ..
git add wm-package
```

- [ ] **Step 4: Se `composer.json` fissa una versione semver (non path repository), aggiornarla e lanciare composer update**

```bash
docker exec -it php-maphub composer update wm/wm-package --with-all-dependencies
```

Expected: `composer.lock` aggiornato, nessun conflitto di dipendenze.

- [ ] **Step 5: Verificare che le classi del package siano caricabili**

Run: `docker exec -it php-maphub php artisan tinker --execute="var_dump(class_exists(\Wm\WmPackage\Nova\Actions\ImportEcPoiFromOsm::class));"`
Expected: `bool(true)`

- [ ] **Step 6: Commit**

```bash
git add wm-package composer.json composer.lock
git commit -m "feat(oc:8239): bump wm-package submodule to include OSM import migration"
```

---

### Task 2: Semplificare `app/Nova/EcPoi.php` a stub vuoto + smoke test

**Files:**
- Modify: `app/Nova/EcPoi.php`
- Test: `tests/Feature/Nova/EcPoiOsmImportActionAvailableTest.php`

**Interfaces:**
- Consumes: `Wm\WmPackage\Nova\Actions\ImportEcPoiFromOsm` (arriva di default da `Wm\WmPackage\Nova\EcPoi::actions()`, vedi Task 7 del piano wm-package).

- [ ] **Step 1: Scrivere il test che fallisce**

```php
<?php

declare(strict_types=1);

use App\Nova\EcPoi;
use Laravel\Nova\Http\Requests\NovaRequest;
use Wm\WmPackage\Nova\Actions\ImportEcPoiFromOsm;

it('exposes the OSM import action on the EcPoi Nova resource by default', function () {
    $request = NovaRequest::create('/');

    $resource = new EcPoi(new \Wm\WmPackage\Models\EcPoi);
    $actions = $resource->actions($request);

    expect(collect($actions)->contains(fn ($action) => $action instanceof ImportEcPoiFromOsm))->toBeTrue();
});
```

Salva in `tests/Feature/Nova/EcPoiOsmImportActionAvailableTest.php`.

- [ ] **Step 2: Eseguire il test e verificare che fallisca**

Run: `docker exec -it php-maphub php artisan test tests/Feature/Nova/EcPoiOsmImportActionAvailableTest.php`
Expected: FAIL — `App\Nova\EcPoi::actions()` restituisce ancora `App\Nova\Actions\ImportEcPoiFromOsm` (la vecchia classe locale, rimossa nel Task 3), non l'istanza del package.

- [ ] **Step 3: Semplificare `app/Nova/EcPoi.php` a stub vuoto**

Sostituire l'intero contenuto di `app/Nova/EcPoi.php`:

```php
<?php

namespace App\Nova;

use Wm\WmPackage\Nova\EcPoi as WmNovaEcPoi;

class EcPoi extends WmNovaEcPoi
{
}
```

- [ ] **Step 4: Eseguire il test e verificare che passi**

Run: `docker exec -it php-maphub php artisan test tests/Feature/Nova/EcPoiOsmImportActionAvailableTest.php`
Expected: PASS — `actions()` ora eredita da `Wm\WmPackage\Nova\EcPoi`, che include `ImportEcPoiFromOsm` di default (Task 7 del piano wm-package).

- [ ] **Step 5: Commit**

```bash
git add app/Nova/EcPoi.php tests/Feature/Nova/EcPoiOsmImportActionAvailableTest.php
git commit -m "refactor(oc:8239): simplify App\\Nova\\EcPoi to a plain stub, action now comes from wm-package"
```

---

### Task 3: Rimuovere il codice business spostato

**Files:**
- Delete: `app/Nova/Actions/ImportEcPoiFromOsm.php`
- Delete: `app/Services/Osm/OsmPoiImporter.php`
- Delete: `app/Services/Osm/ImportReport.php`
- Delete: `app/Services/Osm/OsmTaxonomyPoiTypeResolver.php`
- Delete: `app/Services/Osm/OsmImportReportPresenter.php`
- Delete: `app/Services/Osm/OsmImportReportStore.php`
- Delete: `app/Dto/OsmNodePoiData.php`
- Delete: `app/Dto/OsmEcPoiPropertiesData.php`
- Delete: `app/Console/Commands/ImportEcPoiFromOsmCommand.php`
- Delete: `app/Http/Controllers/OsmImportReportController.php`
- Delete: `resources/views/nova/osm-import-report.blade.php`
- Delete: `resources/views/osm/` (directory vuota)
- Delete: `config/osm-import.php`
- Delete: `tests/Unit/OsmPoiImportTest.php`
- Delete: `tests/Feature/OsmImportReportRouteTest.php`
- Modify: `routes/web.php` (rimuovere il blocco route `osm.import.report`)

- [ ] **Step 1: Verificare che nessun altro file in Maphub referenzi questi namespace**

Run: `grep -rln "App\\\\Services\\\\Osm\|App\\\\Dto\\\\Osm\|OsmPoiImporter\|OsmImportReport\|ImportEcPoiFromOsmCommand" app/ tests/ routes/ resources/ config/ --include="*.php" --include="*.blade.php" | sort`
Expected: solo i file elencati sopra (nessun consumatore esterno dimenticato). Se emerge un file non nella lista, fermarsi e valutare prima di procedere.

- [ ] **Step 2: Rimuovere i file PHP e la view**

```bash
git rm app/Nova/Actions/ImportEcPoiFromOsm.php
git rm app/Services/Osm/OsmPoiImporter.php
git rm app/Services/Osm/ImportReport.php
git rm app/Services/Osm/OsmTaxonomyPoiTypeResolver.php
git rm app/Services/Osm/OsmImportReportPresenter.php
git rm app/Services/Osm/OsmImportReportStore.php
git rm app/Dto/OsmNodePoiData.php
git rm app/Dto/OsmEcPoiPropertiesData.php
git rm app/Console/Commands/ImportEcPoiFromOsmCommand.php
git rm app/Http/Controllers/OsmImportReportController.php
git rm resources/views/nova/osm-import-report.blade.php
rmdir resources/views/osm 2>/dev/null || true
git rm config/osm-import.php
git rm tests/Unit/OsmPoiImportTest.php
git rm tests/Feature/OsmImportReportRouteTest.php
```

Se `app/Services/Osm/` o `app/Dto/` restano vuote dopo le rimozioni, verificare con `ls app/Services/Osm app/Dto` e rimuovere le directory vuote (`rmdir`) — attenzione: `app/Dto/` potrebbe contenere altri DTO non-OSM, verificare prima di rimuovere la directory stessa (solo i due file OSM vanno rimossi, la directory resta se contiene altro).

- [ ] **Step 3: Rimuovere il blocco route in `routes/web.php`**

Il file attuale è:

```php
<?php

use App\Http\Controllers\OsmImportReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // return view('welcome');
    return redirect('/nova');
});

Route::middleware(['web', 'auth', 'can:access-nova'])
    ->get('/nova-vendor/maphub/osm-import-reports/{token}', [OsmImportReportController::class, 'show'])
    ->where('token', '[A-Za-z0-9\-]{16,64}')
    ->name('osm.import.report');
```

Sostituirlo con:

```php
<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // return view('welcome');
    return redirect('/nova');
});
```

- [ ] **Step 4: Eseguire la suite completa e verificare che non ci siano regressioni**

Run: `docker exec -it php-maphub php artisan test`
Expected: PASS (nessun test referenzia più le classi rimosse; il test di smoke del Task 2 copre l'integrazione).

- [ ] **Step 5: Verificare PHPStan**

Run: `docker exec -it php-maphub vendor/bin/phpstan analyse`
Expected: PASS, nessun nuovo errore da riferimenti a classi rimosse.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "refactor(oc:8239): remove OSM import business logic from Maphub, moved to wm-package"
```

---

### Task 4: Rimuovere le chiavi di traduzione OSM

**Files:**
- Modify: `lang/it.json`
- Modify: `lang/en.json`
- Modify: `lang/fr.json`
- Modify: `lang/es.json`
- Modify: `lang/de.json`

- [ ] **Step 1: Elencare le chiavi da rimuovere (verificate presenti in tutti e 5 i file)**

```
"Data will be downloaded from openstreetmap.org for each OSM ID. Continue?"
"Dry run (no writes)"
"Example: 12345, 67890, 11223. OSM nodes only (points)."
"If an OSM ID was already imported, its POI will be updated."
"Import POIs from OSM"
"Invalid OSM geometry"
"New taxonomies created: :tax."
"Node not found or invalid OSM response"
"No app selected or available."
"No valid OSM IDs found. Enter numeric IDs separated by commas."
"OSM IDs not imported (first): "
"OSM node has no tags (nothing useful to import)"
"OSM node IDs (comma-separated)"
"Other error"
"POI owner (required)."
"Requested :req OSM IDs. Created :created, updated :updated, skipped :fail."
"S3/MinIO storage (wmfe disk) not configured or error after save"
"The POI owner is automatically set to the user_id of the selected app."
"Include in app pois.geojson (EcPoi.global = true)"
"All requested nodes failed. See the table below for details."
"Go back"
"Completed with some errors. Review skipped rows below."
"Dry run — no database changes will be saved."
"Errors by category"
"Import completed successfully."
"More failures (:count) are not shown in this table."
"New taxonomies"
"OSM import report"
"Requested OSM IDs"
"Sample failures"
"This page expires after about :minutes minutes."
":count OSM IDs were skipped because they exceeded the per-run limit (OSM_IMPORT_MAX_IDS_PER_RUN). Import the remaining IDs in another run."
```

Nota: `"Category"`, `"Created"`, `"Message"`, `"Reference"`, `"Skipped"`, `"Summary"`, `"Updated"` **non vanno rimosse** — sono parole generiche riusate altrove nell'app (verificare con `grep -c '"Created":' lang/it.json` prima di ogni rimozione: se conta 1 sola occorrenza della chiave e non è più referenziata da nessun `__()` residuo in `app/`/`resources/`, rimuovere; altrimenti lasciare).

- [ ] **Step 2: Rimuovere le chiavi da ciascun file lang**

Per ciascuno dei 5 file (`lang/it.json`, `lang/en.json`, `lang/fr.json`, `lang/es.json`, `lang/de.json`), rimuovere le righe corrispondenti alle chiavi elencate allo Step 1. Esempio di diff atteso su `lang/it.json` (righe 4-47 del file originale, vedi Task 3 Step 1 dell'overview per il contenuto completo prima della rimozione):

```diff
 {
-    "Data will be downloaded from openstreetmap.org for each OSM ID. Continue?": "Verranno scaricati i dati da openstreetmap.org per ogni OSMID. Continuare?",
-    "Dry run (no writes)": "Dry run (nessuna scrittura)",
-    "Example: 12345, 67890, 11223. OSM nodes only (points).": "Esempio: 12345, 67890, 11223. Solo node OSM (punti).",
-    "If an OSM ID was already imported, its POI will be updated.": "Se un OSMID è già stato importato in precedenza, il relativo POI verrà aggiornato.",
     ... (altre chiavi non-OSM del file restano invariate) ...
-    "Import POIs from OSM": "Importa POI da OSM",
-    "Invalid OSM geometry": "Geometria OSM non valida",
-    "New taxonomies created: :tax.": "Nuove taxonomy create: :tax.",
-    "Node not found or invalid OSM response": "Node non trovato o risposta OSM non valida",
-    "No app selected or available.": "Nessuna app selezionata o disponibile.",
-    "No valid OSM IDs found. Enter numeric IDs separated by commas.": "Nessun OSMID valido trovato. Inserire ID numerici separati da virgola.",
-    "OSM IDs not imported (first): ": "OSMID non importati (primi): ",
-    "OSM node has no tags (nothing useful to import)": "Node OSM senza tag (nessun dato utile)",
-    "OSM node IDs (comma-separated)": "OSMID dei node (separati da virgola)",
-    "Other error": "Altro errore",
-    "POI owner (required).": "Proprietario del POI (obbligatorio).",
-    "Requested :req OSM IDs. Created :created, updated :updated, skipped :fail.": "Richiesti :req OSMID. Importati :created, aggiornati :updated, skippati :fail.",
-    "S3/MinIO storage (wmfe disk) not configured or error after save": "Storage S3/MinIO (disk wmfe) non configurato o errore dopo il salvataggio",
-    "The POI owner is automatically set to the user_id of the selected app.": "Il proprietario dei POI viene impostato automaticamente sull'user_id dell'app selezionata.",
-    "Include in app pois.geojson (EcPoi.global = true)": "Visibili nel pois.geojson (EcPoi.global = true)",
-    "All requested nodes failed. See the table below for details.": "Tutti i node richiesti sono falliti. Vedi la tabella sotto per i dettagli.",
-    "Go back": "Torna indietro",
-    "Completed with some errors. Review skipped rows below.": "Completato con alcuni errori. Controlla le righe skippate qui sotto.",
-    "Dry run — no database changes will be saved.": "Dry run — nessuna modifica al database verrà salvata.",
-    "Errors by category": "Errori per categoria",
-    "Import completed successfully.": "Import completato con successo.",
-    "More failures (:count) are not shown in this table.": "Altri :count errori non sono mostrati in questa tabella.",
-    "New taxonomies": "Nuove taxonomy",
-    "OSM import report": "Report import OSM",
-    "Requested OSM IDs": "OSMID richiesti",
-    "Sample failures": "Esempi di errori",
-    "This page expires after about :minutes minutes.": "Questa pagina scade dopo circa :minutes minuti.",
-    ":count OSM IDs were skipped because they exceeded the per-run limit (OSM_IMPORT_MAX_IDS_PER_RUN). Import the remaining IDs in another run.": ":count OSMID non sono stati elaborati perché superano il limite per esecuzione (OSM_IMPORT_MAX_IDS_PER_RUN). Importa i rimanenti in un'altra esecuzione.",
     ... (resto del file invariato) ...
 }
```

Applicare la stessa logica di rimozione (solo le chiavi elencate allo Step 1, valori nella lingua corrispondente) su `en.json`, `fr.json`, `es.json`, `de.json`.

- [ ] **Step 3: Validare che ogni file JSON resti sintatticamente valido**

Run: `for f in lang/it.json lang/en.json lang/fr.json lang/es.json lang/de.json; do php -r "json_decode(file_get_contents('$f'), true) !== null || exit(1);" && echo "$f OK" || echo "$f INVALID"; done`
Expected: `OK` per tutti e 5 i file.

- [ ] **Step 4: Eseguire la suite e verificare che non ci siano regressioni**

Run: `docker exec -it php-maphub php artisan test`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add lang/it.json lang/en.json lang/fr.json lang/es.json lang/de.json
git commit -m "refactor(oc:8239): remove OSM import translation keys from Maphub (moved to wm-package en/it only)"
```

---

### Task 5: Rimuovere config e env var OSM_IMPORT_*

**Files:**
- Modify: `.env` (locale, non versionato — solo verifica/rimozione manuale)
- Modify: `.env-example`

**Interfaces:**
- Nessuna (il config `config/osm-import.php` è già stato rimosso nel Task 3; qui si puliscono solo le variabili d'ambiente).

- [ ] **Step 1: Verificare i valori attuali in `.env` locale (e annotare se diversi dai default)**

Run: `grep "OSM_IMPORT" .env`
Expected: mostra `OSM_IMPORT_REQUEST_DELAY_MS` e/o `OSM_IMPORT_MAX_IDS_PER_RUN` se presenti. Se i valori differiscono dai default del package (`350`, `500`), annotarli: andranno impostati come `WM_OSM_IMPORT_REQUEST_DELAY_MS`/`WM_OSM_IMPORT_MAX_IDS_PER_RUN` nel `.env` locale (Step 3) e comunicati per l'aggiornamento del `.env` di produzione (Step 4).

- [ ] **Step 2: Rimuovere le righe da `.env-example`**

Cercare ed eliminare le righe:
```
OSM_IMPORT_REQUEST_DELAY_MS=350
OSM_IMPORT_MAX_IDS_PER_RUN=500
```
(o valori equivalenti presenti nel file) da `.env-example`.

- [ ] **Step 3: Aggiornare il `.env` locale (non committato)**

Se allo Step 1 sono stati trovati valori diversi dai default, sostituire nel `.env` locale:
```
OSM_IMPORT_REQUEST_DELAY_MS=<valore>
OSM_IMPORT_MAX_IDS_PER_RUN=<valore>
```
con:
```
WM_OSM_IMPORT_REQUEST_DELAY_MS=<valore>
WM_OSM_IMPORT_MAX_IDS_PER_RUN=<valore>
```
Se i valori erano i default, è sufficiente rimuovere le vecchie righe (il package applica già gli stessi default).

- [ ] **Step 4: Annotare il task di deploy per l'ambiente di produzione**

Questo step non è automatizzabile da questo piano (richiede accesso al server). Aggiungere a `docs/features/8239-spostare-import-ecpoi-da-osm-in-wm-package/notes.md` (Fase: notes, dopo l'esecuzione) una voce esplicita: "Verificare `OSM_IMPORT_REQUEST_DELAY_MS`/`OSM_IMPORT_MAX_IDS_PER_RUN` nel `.env` di produzione prima del deploy — se diversi dai default (350ms/500), rinominarli in `WM_OSM_IMPORT_*` con lo stesso valore, altrimenti il comportamento cambia silenziosamente ai default del package."

- [ ] **Step 5: Commit**

```bash
git add .env-example
git commit -m "refactor(oc:8239): remove OSM_IMPORT_* env vars from .env-example (renamed to WM_OSM_IMPORT_* in wm-package config)"
```

---

### Task 6: Aggiornare `CLAUDE.md` di Maphub

**Files:**
- Modify: `CLAUDE.md`

- [ ] **Step 1: Aggiungere una riga alla tabella `## Feature disponibili`**

```markdown
| Import EcPoi da OSM spostato in wm-package | oc:8239 | `app/Nova/EcPoi.php` (semplificato a stub), `wm-package/src/Nova/Actions/ImportEcPoiFromOsm.php` | Logica business rimossa da Maphub; l'azione arriva di default da `Wm\WmPackage\Nova\EcPoi::actions()`. Vedi `wm-package/CLAUDE.md` per i dettagli architetturali |
```

- [ ] **Step 2: Aggiungere un blocco a `## Decisioni architetturali`**

```markdown
### Import EcPoi da OSM spostato in wm-package (oc:8239)
- Tutta la logica (Nova Action, servizi, DTO, comando CLI, controller/route/view, config, test) vive ora in wm-package — vedi `wm-package/CLAUDE.md` per le decisioni di dettaglio (fix permessi Administrator, fix isolamento multi-app)
- `app/Nova/EcPoi.php` è uno stub vuoto che estende `Wm\WmPackage\Nova\EcPoi` — non serve più override di `actions()` per questa feature
- Env var rinominate: `OSM_IMPORT_REQUEST_DELAY_MS`/`OSM_IMPORT_MAX_IDS_PER_RUN` → `WM_OSM_IMPORT_REQUEST_DELAY_MS`/`WM_OSM_IMPORT_MAX_IDS_PER_RUN` — verificare `.env` di produzione al deploy se i valori erano stati tarati diversamente dai default
- Traduzioni fr/es/de per questa feature non sono state portate nel package (solo en/it) — regressione accettata esplicitamente, non un bug
```

Mostra il diff completo prima di scrivere (regola generale del workflow).

- [ ] **Step 3: Commit**

```bash
git add CLAUDE.md
git commit -m "docs(oc:8239): document OSM import migration to wm-package in Maphub CLAUDE.md"
```
