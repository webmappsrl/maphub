> Ticket: oc:8162

# Fix permessi e visibilità Nova per ruolo Editor (Maphub)

## Cosa cambia

La sezione menu Nova `UGC` (`app/Providers/NovaServiceProvider.php`) oggi non ha alcun `canSee()` — è sempre visibile a chiunque acceda a Nova. Viene aggiunta una condizione di visibilità basata sul ruolo:

- `Administrator` e `Validator` → sezione sempre visibile.
- `Editor` → sezione visibile solo se almeno una delle app possedute dall'utente ha `auth_show_at_startup` **e** `geolocation_record_enable` entrambi `true` (nuovo metodo `User::hasUgcEnabled()`, implementato nel submodule `wm-package` — vedi `wm-package/docs/features/8162-.../overview.md`).
- Altri ruoli (nessuno in pratica, `Guest` è già bloccato dal gate `viewNova`) → sezione non visibile.

## Perché

Test manuale su utente Editor ha rilevato che la sezione UGC del menu Nova è visibile anche quando l'app associata all'Editor non ha UGC abilitati lato mobile/webapp (nessuna registrazione possibile), risultando in una voce di menu fuorviante che porta a liste vuote o irrilevanti.

## Requisiti

- [ ] `MenuSection::make('UGC', ...)` in `NovaServiceProvider::boot()` riceve un `canSee()`:
  - `true` per `Administrator` e `Validator`
  - per `Editor`, `true` solo se `$user->hasUgcEnabled()` (metodo su `wm-package`, vedi overview del package)
  - `false` per ogni altro caso
- [ ] Test Feature Nova (`tests/Feature/Nova/...`) che copre almeno questi casi (ampliato in Challenge):
  - Administrator → sempre visibile
  - Validator → sempre visibile, anche senza alcuna app UGC-abilitata
  - Editor con app UGC-abilitata → visibile
  - Editor con app non-UGC-abilitata → nascosta
  - Editor senza alcuna app associata → nascosta, **nessuna eccezione lanciata** (verifica critica: vedi Rischi — un'eccezione qui romperebbe il menu Nova per tutti gli Editor, non solo la sezione UGC)
  - Editor con più app (una abilitata, una no) → visibile (criterio OR, comportamento accettato esplicitamente, non un bug)

## Rischi

- Il criterio scelto (`auth_show_at_startup && geolocation_record_enable`) riflette la **capacità configurata** di ricevere UGC, non l'uso reale (un'app abilitata ma senza UGC registrati mostra comunque la sezione, risultando in liste vuote per quell'Editor) — decisione esplicita, discussa e confermata: coerente con il criterio già stabilito in oc:7852 per lo stesso scopo (icona verde/rossa in App detail).
- Un Editor che possiede più App (raro ma tecnicamente possibile via `apps.user_id`) vede la sezione se **almeno una** delle sue app è UGC-abilitata, anche se naviga la risorsa e trova contenuti di un'altra app — mitigato solo in parte, perché lo scoping per-app della query UGC è esplicitamente out of scope in questo ciclo (vedi overview wm-package).
- **Ordine di merge vincolante tra i due repo** (stesso pattern già causa di un incidente CI in oc:8348): questo `canSee()` chiama `$user->hasUgcEnabled()`, definito solo nel submodule `wm-package`. Se il bump del pointer submodule in Maphub avviene **dopo** il merge di questo file, o se un rollback tocca solo uno dei due repo, il metodo non esiste ancora/più → fatal error sul rendering del menu Nova per ogni Editor. Merge order: wm-package (con `hasUgcEnabled()`) **prima**, poi bump submodule + questo `canSee()` nello stesso commit Maphub.

## Out of scope

- Scoping della query `UgcPoi`/`UgcTrack` per app dell'Editor (solo visibilità della voce di menu, non filtro dei risultati) — vedi overview `wm-package` per dettaglio.
- Qualsiasi modifica alle altre sezioni del menu Nova (Admin, EC, Taxonomies, Files) — nessun problema di visibilità segnalato su di esse in questo ticket.

## Moduli toccati

- `app/Providers/NovaServiceProvider.php`
- Test in `tests/Feature/Nova/` (nuovo file, nome da definire in plan)
