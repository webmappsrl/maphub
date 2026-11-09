> Ticket: oc:8337

# Plan — Aggiornare phpstan-baseline.neon e risolvere drift PHPStan preesistente

Nessuna implementazione di codice. Il piano è la sequenza di verifica già eseguita, riportata qui come traccia.

## Task

1. **Verificare stato reale del baseline su `main`**
   - `composer install` dentro container `php-maphub` (necessario un fix una tantum: vendor locale disallineato sull'autoload di `TranslationsBuilder`, risolto con `composer install` pulito)
   - `vendor/bin/phpstan analyse --no-progress` → risultato: `[OK] No errors`

2. **Verificare stato reale del baseline su `develop`**
   - Worktree isolato su `origin/develop` (`git worktree add --detach`), submodule `wm-package` aggiornato al commit corretto (`0adbfbe`)
   - Container throwaway (`wm-phpfpm:8.4`) con `composer install` da zero + `vendor/bin/phpstan analyse` → risultato: `[OK] No errors`

3. **Ricostruire la cronologia del workflow "PHPStan"**
   - `gh run list --workflow=PHPStan --limit 50` per l'intero storico (maggio → luglio 2026)
   - Classificazione dei fallimenti in 3 cause distinte (Nova/composer 403, baseline stale su `ExampleTest.php`, stato transitorio branch WIP `0c_8239`) con verifica del commit/secret che ha risolto ciascuna
   - `gh secret list` per confermare la data di creazione di `NOVA_USERNAME`/`NOVA_PASSWORD` (2026-06-25)

4. **Riprodurre localmente il job CI fallito citato nel ticket (screenshot PR #4, branch `oc_8072`, 24/06)**
   - Worktree dedicato sul commit esatto di quel branch, `composer install` da zero con le credenziali oggi valide → successo (prova che il fix dei secrets risolve quel fallimento specifico)
   - `phpstan analyse` sullo stesso commit storico → 32 errori reali dell'epoca (drift già sanato dai fix successivi, non presente oggi)

5. **Revisione entry-per-entry del baseline attuale (`develop`)**
   - 20 entry raggruppate in 5 categorie, ciascuna verificata: 11 migrazioni `CASCADE`/`SET NULL`, 2 stub Schema Builder, 5 limiti Pest/Larastan (4 in `WebAccessBlockedForGuestTest.php` + 1 in `Pest.php`), 2 trait boilerplate
   - Confronto con `camminiditalia`/`forestas`/`osm2cai2`/`portapporta` per i 2 trait ambigui (`FiltersUsersByRoleTrait`, `HidesAppFromIndexTrait`) — confermato boilerplate condiviso non agganciato, non dead code da un refactor

6. **Documentazione**
   - `overview.md`, `plan.md`, `notes.md` in questa cartella
   - Aggiornamento ticket oc:8337 su Orchestrator (nota dev con timeline + link cartella, stato `done`)

## Nessun task di implementazione

Non ci sono file di codice da creare o modificare. La documentazione viene comunque isolata sul branch dedicato `feature/oc-8337-aggiornare-phpstan-baseline-e-risolvere-drift` (da `develop`), su richiesta esplicita, prima del commit.
