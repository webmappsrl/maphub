> Ticket: oc:8072

# Plan — Modifica ruolo utente Nova

## Task 1 — wm-package: aggiornare `AbstractUserResource` ✅

**Repo:** `wm-package`
**File:** `src/Nova/AbstractUserResource.php`

- Aggiunto guard `->readonly(fn () => ! RolesAndPermissionsService::allowsUser(auth()->user()))` su `RoleBooleanGroup` e `PermissionBooleanGroup`
- Aggiunta `fillUsing()` server-side su entrambi i campi: blocca la persistenza se `allowsUser()` restituisce `false`
- Logica anti-self-demotion in `fillUsing()` di `RoleBooleanGroup`: impedisce di rimuovere il ruolo Administrator dall'utente corrente

**Commit:** `feat(oc:8072): use RolesAndPermissionsService as guard for role/permission fields in AbstractUserResource`

---

## Task 2 — wm-package: aggiungere test ✅

**Repo:** `wm-package`
**File:** `tests/Feature/Nova/AbstractUserResourceRoleGuardTest.php` (nuovo)

Test Pest che copre:
- Non-super-admin non può modificare ruoli (fillUsing è no-op)
- Super-admin può assegnare un nuovo ruolo
- Super-admin non può rimuovere il proprio ruolo Administrator (anti-self-demotion)
- Super-admin può modificare il ruolo Administrator di un altro utente

**Commit:** `test(oc:8072): add feature tests for AbstractUserResource role guard`

---

## Task 3 — maphub: refactor `app/Nova/User.php` ✅

**Repo:** maphub
**File:** `app/Nova/User.php`

- Cambiata classe parent da `Resource` a `AbstractUserResource`
- Rimosso vecchio `fields()` con tutti i campi base e i relativi import
- Aggiunto override `fields()` che chiama `parent::fields()` e aggiunge `hideFromIndex()` su `RoleBooleanGroup` e `PermissionBooleanGroup`

**Commit:** `feat(oc:8072): extend AbstractUserResource in Nova User resource`

---

## Task 4 — maphub: aggiornare `.env-example` ✅

**Repo:** maphub
**File:** `.env-example`

Aggiunta variabile documentata:
```env
# Email degli utenti con accesso super-admin a Nova (possono modificare ruoli e permessi degli utenti).
# Lista separata da virgole. Default (wm-package): team@webmapp.it
WM_SUPER_ADMIN_EMAILS=team@webmapp.it
```

**Commit:** `docs(oc:8072): document WM_SUPER_ADMIN_EMAILS in .env-example`

---

## Task 5 — maphub: aggiungere test ✅

**Repo:** maphub
**File:** `tests/Feature/Nova/UserResourceRoleGuardTest.php` (nuovo)

Test Pest con `DatabaseTransactions` che copre:
- `App\Nova\User` estende `AbstractUserResource`
- I campi Roles includono `RoleBooleanGroup`
- Il campo ruolo è readonly per un non-super-admin
- Il campo ruolo è editabile per il super-admin
- Super-admin può cambiare il ruolo di un altro utente
- Super-admin non può rimuovere il proprio ruolo Administrator

**Commit:** `test(oc:8072): add feature tests for Nova User resource role access`
