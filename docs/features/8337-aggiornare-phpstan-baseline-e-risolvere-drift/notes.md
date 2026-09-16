> Ticket: oc:8337

# Notes — Aggiornare phpstan-baseline.neon e risolvere drift PHPStan preesistente

## Deviazioni dal piano

Il ticket presupponeva un lavoro analogo a oc:8312 (baseline disallineato da correggere). L'indagine ha invece dimostrato l'assenza di drift: il piano si è quindi spostato da "correggere" a "verificare e documentare in modo che la verifica sia riproducibile", incluse le riproduzioni locali dei job CI storici falliti per non lasciare la questione a un'affermazione non verificata.

## Bug trovati

Nessun bug di codice. Trovato solo un problema di ambiente locale (non di repo): il `vendor/` locale di maphub aveva un autoload disallineato per `Wm\WmPackage\Nova\Fields\TranslationsBuilder\FieldServiceProvider` (mancante in `vendor/composer/autoload_psr4.php` nonostante presente in `wm-package/composer.json`), che impediva a Larastan di bootstrappare Laravel. Risolto con `composer install` pulito nel container `php-maphub`. Non è un problema del codice versionato — probabile disallineamento tra l'ultimo `composer install` locale e un aggiornamento più recente del submodule `wm-package`.

## Decisioni

- **Nessuna modifica al codice**: verificato che `phpstan-baseline.neon` è allineato su `main` e su `develop` (0 errori in entrambi, verificato con esecuzione reale in container, non solo lettura statica).
- **Le 20 entry del baseline attuale sono tutte legittime** — nessuna richiede fix: 11 relative a `ForeignKeyDefinition::onDelete()` con stringhe maiuscole (8 `'CASCADE'` + 3 `'SET NULL'`; drift tipizzazione stub vs runtime, stesso identico pattern di camminiditalia oc:8312), 2 su `IndexDefinition::comment()`/`Blueprint::float()` (stub Schema Builder), 5 su `WebAccessBlockedForGuestTest.php` (4 entry) + `Pest.php` (1 entry) (limite noto Larastan con le closure Pest, `$this` non tipizzato staticamente), 2 sui trait `FiltersUsersByRoleTrait`/`HidesAppFromIndexTrait` (boilerplate condiviso mai agganciato in maphub, non codice orfano — vedi sotto).
- **I due trait "dead code" sono scaffold del boilerplate, non un refactor incompleto**: verificato con `git log --follow` (introdotti nell'"Initial commit", mai modificati) e confronto diretto con i repo gemelli — camminiditalia li usa attivamente su 4 risorse Nova (`EcTrack`, `UgcPoi`, `Layer`, `UgcTrack`), forestas ha lo stesso identico scaffold mai agganciato di maphub, osm2cai2/portapporta non hanno questi file. Nessuna azione: applicarli oggi alle risorse Nova di maphub introdurrebbe un comportamento (filtro utenti per ruolo, campo `app` nascosto dall'index) che nessun requisito di questo ticket richiede.
- **Il fallimento CI citato nel ticket (screenshot PR #4, branch `oc_8072`, 24/06/2026) è storia già risolta**, non un problema corrente: causato dall'assenza dei secrets `NOVA_USERNAME`/`NOVA_PASSWORD` sul repo GitHub (introdotti il 25/06/2026, confermato con `gh secret list`). Riprodotto localmente il job esatto (worktree sul commit storico, `composer install` da zero) per provare che oggi, con le stesse credenziali valide, l'installazione completa senza errori.
- **Il workflow "PHPStan" su GitHub Actions mostra oggi una run rossa in cima alla lista** (28/07/2026, branch `0c_8239`) — non è indicativo dello stato corrente: quel fallimento era uno stato transitorio di un refactor in corso (classe temporaneamente spostata tra maphub e wm-package), risolto dal merge della PR #14 subito dopo. Il workflow non ha un trigger `workflow_dispatch` e nessuna PR successiva ha toccato file `.php`, quindi la lista resta "congelata" su quell'ultimo tentativo. La verifica reale (0 errori su `develop`) è stata fatta localmente in container, non tramite una nuova run GitHub-hosted.

## Follow-up

- Valutare (fuori scope qui) l'aggiunta di un trigger `workflow_dispatch` a `.github/workflows/phpstan.yml`, per poter verificare lo stato del check senza dover aprire una PR che tocchi file `.php` — utile per casi come questo ticket, dove serviva una conferma "a freddo" dello stato del check.
- Nessun follow-up su `wm-package` (baseline separato, fuori scope) né sui trait boilerplate (nessun requisito attuale li richiede).
