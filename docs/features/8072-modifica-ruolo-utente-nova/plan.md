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

---

## Task 6 — wm-package: fix `json_decode` null guard in `fillUsing` (Bug bloccante #1)

**Repo:** `wm-package`
**File:** `src/Nova/AbstractUserResource.php`

Sia in `RoleBooleanGroup.fillUsing` che in `PermissionBooleanGroup.fillUsing`, dopo la chiamata a `json_decode`, aggiungere un check di tipo: se il risultato non è un array, return early (preserva lo stato esistente).

```php
$decoded = json_decode($request[$requestAttribute], true);
if (! is_array($decoded)) {
    return;
}
$roles = collect($decoded)->filter(fn ($value) => $value)->keys();
```

**Commit:** `fix(oc:8072): return early on invalid JSON in fillUsing to prevent silent role wipe`

---

## Task 7 — wm-package: fix anti-self-demotion condition (Bug bloccante #2)

**Repo:** `wm-package`
**File:** `src/Nova/AbstractUserResource.php`

Nella logica anti-self-demotion di `RoleBooleanGroup.fillUsing`, aggiungere la condizione `$model->hasRole('Administrator')`:

```php
if ($request->user()->id === $model->id && $model->hasRole('Administrator')) {
    $roles = $roles->merge(['Administrator'])->unique();
}
```

Senza questo check, un super-admin che non ha mai avuto il ruolo Administrator nel DB se lo ritrova assegnato automaticamente al primo salvataggio del proprio profilo.

**Commit:** `fix(oc:8072): only preserve Administrator role if model already has it`

---

## Task 8 — wm-package: allineare sorgente auth in `readonly()` (Cleanup)

**Repo:** `wm-package`
**File:** `src/Nova/AbstractUserResource.php`

Cambiare `auth()->user()` con `$request->user()` in entrambi i campi `readonly()`, per coerenza con `fillUsing()` e corretto funzionamento nei test:

```php
->readonly(fn (NovaRequest $request) => ! RolesAndPermissionsService::allowsUser($request->user()))
```

**Commit:** `fix(oc:8072): use request->user() in readonly() for consistency with fillUsing()`

---

## Task 9 — wm-package: fix helpers nel namespace globale (Cleanup)

**Repo:** `wm-package`
**File:** `tests/Feature/Nova/AbstractUserResourceRoleGuardTest.php`

Convertire le funzioni `makeUserResource()` e `makeRoleField()` in closure assegnate a variabili a livello di file, catturate con `use` nei singoli `it()`:

```php
$makeUserResource = fn (User $user): AbstractUserResource => new class($user) extends AbstractUserResource {
    public static string $model = User::class;
};

$makeRoleField = function (NovaRequest $request, User $contextUser) use ($makeUserResource): RoleBooleanGroup {
    Auth::login($contextUser);
    $resource = $makeUserResource($contextUser);
    $fields = $resource->fields($request);
    return collect($fields)->first(fn ($f) => $f instanceof RoleBooleanGroup);
};
```

**Commit:** `refactor(oc:8072): convert global test helpers to file-scoped closures`

---

## Task 10 — wm-package: aggiungere test `PermissionBooleanGroup.fillUsing` + fix commento (Bug bloccante #3 + Cleanup)

**Repo:** `wm-package`
**File:** `tests/Feature/Nova/AbstractUserResourceRoleGuardTest.php`

Aggiungere i test mancanti per il guard di `PermissionBooleanGroup`:
- Non-super-admin non può modificare permessi via `fillUsing`
- Super-admin può assegnare permessi

Correggere il commento errato (riga 51): `readonly()` non blocca `fillUsing()` — la protezione è il `return` dentro `fillUsing()` quando `allowsUser()` è `false`.

**Commit:** `test(oc:8072): add PermissionBooleanGroup fillUsing tests and fix misleading comment`

---

## Task 11 — wm-package: spostare test di logica package da maphub (Cleanup)

**Repo:** `wm-package`
**File:** `tests/Feature/Nova/AbstractUserResourceRoleGuardTest.php`

Aggiungere i test che testano logica del package (attualmente in maphub):
- `super-admin can change another user's role`
- `super-admin cannot remove their own Administrator role`

**Repo:** maphub
**File:** `tests/Feature/Nova/UserResourceRoleGuardTest.php`

Rimuovere questi due test da maphub — testano `AbstractUserResource`, non `App\Nova\User`. Maphub mantiene solo i test specifici della propria configurazione (estende, `hideFromIndex`, readonly/editable per il campo).

**Commit wm-package:** `test(oc:8072): move package-level role guard tests from maphub`
**Commit maphub:** `refactor(oc:8072): remove duplicate role guard tests that belong to wm-package`

---

## Task 12 — wm-package: aggiornare CHANGELOG (Cleanup)

**Repo:** `wm-package`
**File:** `CHANGELOG.md` (o equivalente)

Documentare il breaking change: `RoleBooleanGroup` e `PermissionBooleanGroup` ora richiedono `WM_SUPER_ADMIN_EMAILS` configurata — senza di essa nessuno può modificare ruoli/permessi in Nova.

**Commit:** `docs(oc:8072): document breaking change for role/permission guard in CHANGELOG`

---

## Task 13 — wm-package: fix `PermissionBooleanGroup` anti-self-demotion (Bug da review)

**Repo:** `wm-package`
**File:** `src/Nova/AbstractUserResource.php`

Aggiunta protezione simmetrica a quella dei ruoli: se `$request->user()->id === $model->id`, i permessi diretti esistenti vengono preservati tramite merge, impedendo la rimozione accidentale di permessi critici (es. `manage roles and permissions`).

```php
if ($request->user()->id === $model->id) {
    $existing = $model->getDirectPermissions()->pluck('name')->toArray();
    $values = array_unique(array_merge($values, $existing));
}
```

**Commit:** `fix(oc:8072): preserve super-admin own permissions on self-edit`

---

## Task 14 — wm-package + maphub: allineare `canSee()` a `$request->user()` (Bug da review)

**Repo:** `wm-package`
**File:** `src/Nova/AbstractUserResource.php`

Le closure `canSee()` sulle `HasMany` (UGC POIs e UGC Tracks) usavano `auth()->user()` invece di `$request->user()`. Allineate per coerenza con il resto della logica auth.

**Repo:** maphub
**File:** `.env-example`

Aggiunta `WM_SUPER_ADMIN_EMAILS` — requisito originariamente marcato ✅ ma non eseguito.

**Commit wm-package:** `fix(oc:8072): use request->user() in canSee() for consistency`
**Commit maphub:** `docs(oc:8072): add WM_SUPER_ADMIN_EMAILS to .env-example`
