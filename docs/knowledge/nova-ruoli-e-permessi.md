# Nova: chi entra, chi vede cosa

## Come funziona oggi

**L'accesso a Nova è deciso da un permesso, non da un ruolo.** Il gate in
`NovaServiceProvider::gate()` definisce `viewNova` come `$user->can('access-nova')`.

I ruoli — Administrator, Editor, Validator, Guest — restano il modo in cui quel permesso viene
assegnato, ma il gate non li guarda.

**La sezione UGC del menu è condizionata per ruolo**: Administrator e Validator la vedono sempre,
l'Editor solo se almeno una delle sue App ha la UGC abilitata (`hasUgcEnabled()`, metodo su
`Wm\WmPackage\Models\User`), gli altri mai.

**`app/Nova/User.php` estende `AbstractUserResource`** di wm-package senza ridefinire i campi base,
e aggiunge solo un override di `fields()` con `hideFromIndex()` su `RoleBooleanGroup` e
`PermissionBooleanGroup`.

## Perché così

- **Il permesso al posto del ruolo** (oc:8161): il gate controllava `!$user->hasRole('Guest')`, che
  legava l'accesso a un ruolo specifico invece che a una capacità. La logica del permesso vive in
  wm-package.
- **Il package resta agnostico sulla visibilità nell'index** (oc:8072): l'`hideFromIndex()` è una
  scelta di questo prodotto, non una regola per tutti i consumer — ed è il motivo per cui va
  ripetuta in ciascuno.
- **La quasi totalità della logica di business sta in wm-package** (oc:8162): scoping per-app di EC
  e UGC, Policy. Qui resta il `canSee()`, che quella logica la consuma.
- **`DatabaseTransactions` invece di `RefreshDatabase`** nei test (oc:8072): `phpunit.xml` non
  configura un DB separato, quindi `RefreshDatabase` svuoterebbe il database di sviluppo.

## Trappole

L'ordine di merge fra wm-package e questo repo, e l'override da ripetere negli altri consumer,
stanno in [.claude/rules/wm-package-ordine-merge.md](../../.claude/rules/wm-package-ordine-merge.md).

## Debito noto

- **Tre bug trovati solo in review formale** (oc:8162), non dalle review per-task: uno lato Maphub
  — `NovaServiceProvider::canSee()` alterato dopo l'approvazione — e due lato wm-package. Il
  dettaglio è nel cantiere.
