> Ticket: oc:8072

# Aggiungere in Nova la possibilità di modificare il ruolo di un utente

## Cosa cambia

`app/Nova/User.php` estende `AbstractUserResource` (wm-package) invece di `Resource`. I campi `RoleBooleanGroup` e `PermissionBooleanGroup` appaiono nella scheda utente Nova per tutti gli Administrator (sola lettura) e sono editabili esclusivamente dall'utente in `WM_SUPER_ADMIN_EMAILS` (default: `team@webmapp.it`). I campi sono nascosti dall'index tramite override `fields()` in `app/Nova/User.php`.

## Perché

Attualmente non è possibile modificare il ruolo di un utente tramite Nova — richiede accesso diretto al database o CLI. La feature abilita questa gestione in modo sicuro, limitando la modifica a un set configurabile di email super-admin tramite `WM_SUPER_ADMIN_EMAILS`.

## Requisiti

- [x] `app/Nova/User.php` estende `Wm\WmPackage\Nova\AbstractUserResource`
- [x] I campi "Roles" e "Permissions" sono visibili nella scheda utente Nova a tutti gli Administrator (sola lettura)
- [x] Solo l'utente la cui email è in `WM_SUPER_ADMIN_EMAILS` può modificare i campi "Roles" e "Permissions"
- [x] La logica di accesso usa `RolesAndPermissionsService::allowsUser()` — nessuna email hardcodata
- [x] Protezione server-side: `fillUsing()` su `RoleBooleanGroup` e `PermissionBooleanGroup` ignora il payload se `allowsUser()` restituisce `false`
- [x] Protezione anti-self-demotion: `fillUsing` impedisce di rimuovere il ruolo Administrator dall'utente corrente
- [x] I campi "Roles" e "Permissions" sono nascosti dall'index (solo detail/edit) tramite override in `app/Nova/User.php`
- [x] `WM_SUPER_ADMIN_EMAILS` documentata in `.env-example`
- [x] Test in maphub e wm-package verificano il comportamento readonly/editabile

## Rischi

- **Regressione campi password**: rimuovendo il vecchio `fields()` da `User.php`, le regole password passano da `PasswordValidationRules` trait a `Rules\Password::defaults()`. **Mitigazione**: verificato che `AppServiceProvider` non sovrascriva `Password::defaults()`.
- **Campi UGC HasMany**: `AbstractUserResource` include `HasMany` per `UgcPoi` e `UgcTrack`, visibili solo agli Administrator tramite `canSee`. Comportamento desiderabile.
- **Guard solo visivo bypassabile via API**: mitigato dalla `fillUsing()` server-side che blocca la persistenza indipendentemente dal campo readonly.

## Out of scope

- Aggiunta di una colonna testo "Ruolo" nella lista utenti (index view)
- Creazione di nuovi ruoli o permessi tramite Nova
- Modifica della logica di `RolesAndPermissionsService`
- Audit log delle modifiche ai ruoli (follow-up — vedi `notes.md`)

## Moduli toccati

| Repo | File | Tipo modifica |
|------|------|---------------|
| **maphub** | `app/Nova/User.php` | Refactor — estende `AbstractUserResource`, override `fields()` per `hideFromIndex()` |
| **maphub** | `.env-example` | Aggiunta variabile `WM_SUPER_ADMIN_EMAILS` |
| **maphub** | `tests/Feature/Nova/UserResourceRoleGuardTest.php` | Nuovo — test feature Nova sui campi ruolo |
| **wm-package** | `src/Nova/AbstractUserResource.php` | Aggiunta guard `RolesAndPermissionsService::allowsUser()`, `fillUsing()`, anti-self-demotion |
| **wm-package** | `tests/Feature/Nova/AbstractUserResourceRoleGuardTest.php` | Nuovo — test guard readonly AbstractUserResource |
