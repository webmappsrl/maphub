> Ticket: oc:8348

# Spostare test EcPoiOsmImportActionAvailableTest in wm-package

## Cosa cambia

Il test `tests/Feature/Nova/EcPoiOsmImportActionAvailableTest.php` viene eliminato da Maphub. La stessa asserzione esplicita che verifica ("l'azione `ImportEcPoiFromOsm` è esposta di default sulla EcPoi Nova resource") viene ricreata come nuovo test in `wm-package/tests/Feature/Nova/Actions/ImportEcPoiFromOsmActionTest.php`, puntando alla resource del package (`Wm\WmPackage\Nova\EcPoi`) invece che allo stub `App\Nova\EcPoi` di Maphub.

## Perché

Dopo oc:8239 (import EcPoi da OSM spostato interamente in wm-package), il test rimasto in Maphub referenziava direttamente una classe interna del submodule (`Wm\WmPackage\Nova\Actions\ImportEcPoiFromOsm`), accoppiando la correttezza del test in Maphub allo stato del puntatore submodule `wm-package`.

**Root cause verificata** (non solo ipotizzata): nel commit `c3d7876` (PR oc:8239, #14), il submodule `wm-package` era ancora puntato al commit `edf22b2d6`, che **non conteneva ancora** la classe `ImportEcPoiFromOsm` (verificato con `git show edf22b2d6:src/Nova/Actions/ImportEcPoiFromOsm.php` → file assente a quel commit). Questo ha causato l'errore PHPStan "Class Wm\WmPackage\Nova\Actions\ImportEcPoiFromOsm not found" nella run #30 del job `phpstan` (`.github/workflows/phpstan.yml`). Il bump del puntatore submodule alla versione con la classe è arrivato solo in un commit successivo su `develop`.

Ad oggi (submodule a `60f88f45`, che include la classe), l'errore **non è più riproducibile localmente** (`vendor/bin/phpstan analyse tests/Feature/Nova/EcPoiOsmImportActionAvailableTest.php` → `[OK] No errors`). Il problema non è quindi un bug attivo, ma un **rischio strutturale ricorrente**: qualsiasi test in Maphub che referenzia direttamente classi interne di wm-package può rompersi ogni volta che l'ordine dei commit (bump submodule vs commit che referenzia la nuova classe) non è allineato all'interno dello stesso PR. Spostare il test elimina questa classe di errore alla radice.

## Requisiti

- [ ] Eliminare `tests/Feature/Nova/EcPoiOsmImportActionAvailableTest.php` da Maphub, senza sostituirlo con un test equivalente in Maphub (coerente con la convenzione esistente: nessuno degli altri 11 stub Nova di Maphub — `App`, `EcTrack`, `Layer`, ecc. — ha un test dedicato che verifica l'estensione della resource del package)
- [ ] Aggiungere in `wm-package/tests/Feature/Nova/Actions/ImportEcPoiFromOsmActionTest.php` un nuovo test che asserisce esplicitamente `collect($actions)->contains(fn ($action) => $action instanceof ImportEcPoiFromOsm)`, risolvendo `actions()` da `Wm\WmPackage\Nova\EcPoi` (non dallo stub Maphub)
- [ ] Verificare che `vendor/bin/phpstan analyse` (Maphub) resti verde dopo la rimozione
- [ ] Verificare che la suite Pest di wm-package (incluso il nuovo test) passi

## Rischi

- **Coordinamento cross-repo**: le due modifiche vivono in PR separate (Maphub + wm-package) su CI indipendenti. Se mergiate fuori sequenza si crea una finestra di stato intermedio (o copertura persa, o solo duplicazione senza eliminare l'accoppiamento). Mitigazione: ordine di merge vincolante — prima wm-package, poi Maphub con bump submodule + rimozione file nello stesso commit (vedi `plan.md`).
- **Ridondanza accettata consapevolmente**: il nuovo test esplicito in wm-package non copre uno scenario nuovo — i 6 test di autorizzazione esistenti già fallirebbero indirettamente se l'azione sparisse da `EcPoi::actions()` (via `resolveImportEcPoiFromOsmAction()` che ritornerebbe `null`). Si accetta la duplicazione in cambio di un messaggio d'errore esplicito e leggibile. Eventuale refactoring per rimuovere la ridondanza è rimandato fuori da questo ciclo.
- **Root cause non risolta a livello strutturale**: questo fix rimuove solo l'istanza del problema in Maphub. Altri consumer di wm-package (camminiditalia, osm2cai2) potrebbero avere lo stesso pattern (test che referenziano direttamente classi interne del package) e restare esposti allo stesso rischio di errore PHPStan da disallineamento del puntatore submodule. Nessun audit trasversale è previsto in questo ciclo — accettato esplicitamente come out of scope.

## Out of scope

- Nessuna modifica a `EcPoi::actions()`, `RolesAndPermissionsService`, o altra logica applicativa dell'azione OSM import
- Nessun nuovo test "stub extends" in Maphub per `app/Nova/EcPoi.php` — fuori convenzione rispetto agli altri stub Nova
- Nessuna modifica ai 6 test di autorizzazione già esistenti in `ImportEcPoiFromOsmActionTest.php` — solo aggiunta di un nuovo `it()`
- Nessuna modifica al workflow CI (`.github/workflows/phpstan.yml`, `run-tests.yml`) — il fix è solo a livello di test, non di pipeline

## Moduli toccati

- **Maphub** (repo principale): `tests/Feature/Nova/EcPoiOsmImportActionAvailableTest.php` — eliminato
- **wm-package** (submodule): `tests/Feature/Nova/Actions/ImportEcPoiFromOsmActionTest.php` — nuovo test aggiunto
