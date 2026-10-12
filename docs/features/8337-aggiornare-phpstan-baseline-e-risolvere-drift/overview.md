> Ticket: oc:8337

# Aggiornare phpstan-baseline.neon e risolvere drift PHPStan preesistente

## Cosa cambia

Nulla nel codice. Il ticket richiedeva di replicare in maphub il lavoro fatto per camminiditalia (oc:8312): verificare se `phpstan-baseline.neon` è disallineato dal codice/dipendenze reali e, se sì, rigenerarlo correggendo i problemi reali trovati durante la revisione.

La verifica ha accertato che in maphub **non esiste drift** — il baseline è già allineato sia su `main` che su `develop`. Questo documento registra l'indagine e la prova, così il ticket si chiude con evidenza verificabile invece che a mani vuote.

## Perché

Il ticket è stato aperto il 2026-08-03 con richiesta esplicita di "replicare il lavoro svolto per cammini in maphub" (link a oc:8312), dopo che in camminiditalia il check CI PHPStan era rimasto silenziosamente rotto per ~5 mesi (baseline non aggiornato da febbraio 2025) con ~51 errori non catturati.

## Requisiti

- [x] Eseguire `vendor/bin/phpstan analyse` reale (non solo leggere il codice) su `main` e su `develop`, per verificare l'esistenza di drift
- [x] Ricostruire la cronologia dei fallimenti del workflow GitHub Actions "PHPStan" (distinto da "Run Tests") per capire se i fallimenti storici sono ancora attivi
- [x] Rivedere ogni singola entry del baseline attuale, una per una, per distinguere falsi positivi legittimi da bug reali (stesso metodo usato in oc:8312: 21 falsi positivi confermati su migration + verifica separata di eventuali fix reali)
- [x] Verificare comparativamente con progetti gemelli (camminiditalia, forestas, osm2cai2, portapporta) per interpretare correttamente entry ambigue (i due trait "used zero times")

## Rischi

- **Falso senso di sicurezza dal solo "check verde" in CI**: il workflow "PHPStan" non ha un trigger manuale (`workflow_dispatch`) — si attiva solo su `pull_request` con path `**.php`/`phpstan.neon.dist`. Se non arrivano PR che tocchino questi file, la pagina Actions resta "congelata" sull'ultimo risultato (oggi: rosso, dell'ultimo commit WIP di oc:8239 del 28/07, già superato dal merge). Mitigato documentando qui la verifica diretta (esecuzione locale reale in container, non lettura statica del codice) su entrambi i branch.
- **I due trait "dead code" potrebbero sembrare un bug da correggere**: `FiltersUsersByRoleTrait`/`HidesAppFromIndexTrait` risultano "used zero times" — a prima vista sembra un refactor incompleto. Verificato con git log (`Initial commit`, mai stati agganciati) e confronto con progetti gemelli che sono scaffold del boilerplate condiviso, non codice orfano da un refactor: camminiditalia li usa attivamente su EcTrack/UgcPoi/Layer/UgcTrack (extend + `use Trait`), forestas ha lo stesso boilerplate mai agganciato (identico a maphub), osm2cai2/portapporta non li hanno nemmeno. Nessuna azione necessaria.

## Out of scope

- Aggiungere un trigger `workflow_dispatch` al workflow "PHPStan" per poterlo rilanciare senza una PR — utile ma non richiesto da questo ticket, valutabile come follow-up separato
- Applicare `FiltersUsersByRoleTrait`/`HidesAppFromIndexTrait` alle risorse Nova di maphub (EcTrack/UgcPoi/Layer/UgcTrack) per allinearle al comportamento di camminiditalia — nessun requisito funzionale lo richiede oggi
- Baseline/drift di `wm-package` (ha una sua config PHPStan separata, `wm-package/phpstan.neon.dist`) — fuori scope, questo ticket riguarda solo il repo principale maphub

## Moduli toccati

Nessuno lato codice. Solo documentazione: `docs/features/8337-aggiornare-phpstan-baseline-e-risolvere-drift/`.
