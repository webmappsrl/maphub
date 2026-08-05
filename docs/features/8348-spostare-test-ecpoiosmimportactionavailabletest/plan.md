> Ticket: oc:8348

# Piano implementativo — Spostare test EcPoiOsmImportActionAvailableTest in wm-package

## Ordine di esecuzione (vincolante)

Le due PR sono separate (vedi decisione in `overview.md`) ma devono essere mergiate in quest'ordine specifico, ravvicinate nel tempo, per non lasciare una finestra di stato intermedio:

1. **PR 1 — wm-package**: Task 1 (nuovo test)
2. **PR 2 — Maphub**: solo dopo che PR 1 è mergiata su wm-package, Task 2 (bump submodule + eliminazione file, stesso commit)

## Task 1 — wm-package: aggiungere test esplicito di esposizione azione

**Repo:** wm-package (submodule)
**File:** `tests/Feature/Nova/Actions/ImportEcPoiFromOsmActionTest.php`

1. Nel file esistente, individuare gli import già presenti in cima (`EcPoiResource`, `EcPoi`, `NovaRequest`, `ImportEcPoiFromOsm`) — nessun nuovo import necessario.
2. Aggiungere un nuovo `it()`, posizionato per primo nel file (prima dei 6 test di autorizzazione esistenti, dato che verifica una precondizione più basilare — l'azione esiste nella lista — rispetto a "chi può vederla/eseguirla"):

   ```php
   it('exposes the OSM import action on the EcPoi Nova resource by default', function () {
       $request = NovaRequest::create('/');

       $actions = (new EcPoiResource(new EcPoi))->actions($request);

       expect(collect($actions)->contains(fn ($action) => $action instanceof ImportEcPoiFromOsm))->toBeTrue();
   });
   ```

   Nota: questo test **non** riusa l'helper `resolveImportEcPoiFromOsmAction()` (quello presuppone già l'azione presente e ne verifica l'autorizzazione) — costruisce `actions()` direttamente, senza `Auth::login()`, per restare equivalente in spirito al test originale di Maphub (nessuna assunzione di ruolo/utente).
3. Eseguire `vendor/bin/pest tests/Feature/Nova/Actions/ImportEcPoiFromOsmActionTest.php` e verificare che tutti i test (6 esistenti + il nuovo) passino.
4. Commit: `test(oc:8348): add explicit assertion for default OSM import action exposure`

## Task 2 — Maphub: bump submodule + eliminare test superato

**Repo:** Maphub (repo principale)
**File:** `tests/Feature/Nova/EcPoiOsmImportActionAvailableTest.php` (da eliminare), puntatore submodule `wm-package`

**Precondizione:** Task 1 mergiato sul branch di wm-package consumato da Maphub.

1. Aggiornare il submodule al commit che include il nuovo test (`cd wm-package && git fetch && git checkout <commit> && cd ..`).
2. Eliminare `tests/Feature/Nova/EcPoiOsmImportActionAvailableTest.php`.
3. Eseguire `vendor/bin/phpstan analyse` e verificare `[OK] No errors`.
4. Eseguire `vendor/bin/pest` (suite completa Maphub) per verificare nessuna regressione.
5. Un singolo commit che include sia il bump del submodule sia l'eliminazione del file (mai in due commit separati — vedi Rischi in `overview.md`): `test(oc:8348): remove OSM import action test superseded by wm-package coverage`

## Verifica finale

- [ ] `vendor/bin/phpstan analyse` verde su Maphub
- [ ] Suite Pest verde su wm-package (incluso il nuovo test)
- [ ] Suite Pest verde su Maphub
- [ ] Nessun altro riferimento a `EcPoiOsmImportActionAvailableTest` nel repo (già verificato in Fase: reverse-interaction — `grep` vuoto)
