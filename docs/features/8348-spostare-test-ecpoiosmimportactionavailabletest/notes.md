> Ticket: oc:8348

# Notes — Spostare test EcPoiOsmImportActionAvailableTest in wm-package

## Deviazioni dal piano

Nessuna deviazione sostanziale rispetto a `plan.md`.

## Bug trovati

Nessun bug nel codice applicativo. Verifica locale completa bloccata da tre limitazioni ambientali pre-esistenti, non introdotte da questo ticket:

- Suite Pest di wm-package non eseguibile da Maphub (`Wm\WmPackage\Tests\TestCase` non in `autoload-dev` di Maphub — bug noto, vedi `wm-package/CLAUDE.md` oc:8231)
- Standalone `composer install` di wm-package fallito: la licenza Nova configurata in questo ambiente non è abilitata a scaricare `laravel/nova@5.9.0` (versione pinnata nel lock file del package), fallback via git SSH senza chiavi configurate
- Container `postgres-maphub` non collegato alla rete Docker `maphub_default` — suite Feature Pest di Maphub non eseguibile (hostname `db` non risolvibile). Pre-esistente, non causato da questo diff.

`vendor/bin/phpstan analyse` (obiettivo primario del ticket) verificato verde su Maphub sia prima che dopo la rimozione del file.

## Decisioni

- Root cause storica confermata via git archaeology: nel commit `c3d7876` (PR oc:8239 #14), il submodule pointer di Maphub puntava a `edf22b2d6`, che non conteneva ancora `ImportEcPoiFromOsm` — causa dell'errore PHPStan nella run CI #30
- Confermato via `grep` che nessun altro file nel repo referenzia il test eliminato
- Bumpare il submodule pointer include incidentalmente un commit non correlato già mergiato su `wm-package/develop` (`fix(GeometryComputationService): rollback esplicito su errore SQL in geometryModelToBbox`) — accettato come normale comportamento di un bump submodule, non isolabile
- Su richiesta esplicita del dev in fase di review formale (`wm-skills:wm-review-ticket`), non applicato il finding cleanup che suggeriva di riusare l'helper `resolveImportEcPoiFromOsmAction()` invece di duplicare la risoluzione inline — l'obiettivo di questo ciclo è esclusivamente eliminare l'errore PHPStan, non refactoring

## Follow-up

- Nessun audit trasversale sugli altri consumer di wm-package (camminiditalia, osm2cai2) per verificare se hanno lo stesso pattern di test accoppiati a classi interne del package — accettato come out of scope in `overview.md`
- Eventuale refactoring per rimuovere la ridondanza tra il nuovo test e l'helper `resolveImportEcPoiFromOsmAction()`, rimandato a un ciclo futuro se necessario
