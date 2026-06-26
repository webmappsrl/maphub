> Ticket: oc:8042

# Plan — Utenti importati: ruolo assegnato in modo errato

## Repo: wm-package

### Step 1 — Aggiungere il ruolo Editor a `RolesAndPermissionsService::seedDatabase()`

File: `src/Services/RolesAndPermissionsService.php`

Aggiungere `Role::firstOrCreate(['name' => 'Editor']);` dopo la riga di `Validator`:

```php
Role::firstOrCreate(['name' => 'Administrator']);
Role::firstOrCreate(['name' => 'Editor']);       // aggiunto
Role::firstOrCreate(['name' => 'Validator']);
Role::firstOrCreate(['name' => 'Guest']);
```

---

### Step 2 — Aggiungere `assignEditorRole()` e aggiornare `checkUserExistence()` in `GeohubImportService`

File: `src/Services/Import/GeohubImportService.php`

**2a.** Aggiungere il metodo `assignEditorRole()`:

```php
protected function assignEditorRole(User $user): void
{
    if ($user->roles->isNotEmpty()) {
        return;
    }

    $role = Role::firstOrCreate(['name' => 'Editor', 'guard_name' => 'web']);
    $user->assignRole($role);
}
```

**2b.** In `checkUserExistence()`, sostituire la chiamata a `assignAdministratorRole()` con `assignEditorRole()`:

```php
public function checkUserExistence(int $userId): User
{
    $geohubUser = $this->dbConnection->table('users')->where('id', $userId)->first();
    $shardUser = User::where('email', $geohubUser->email)->first();

    if (! $shardUser) {
        $diff = array_diff(array_keys((array) $geohubUser), Schema::getColumnListing('users'));
        $transformedData = array_diff_key((array) $geohubUser, array_flip($diff));
        $shardUser = User::create($transformedData);
    }

    $this->assignEditorRole($shardUser);

    return $shardUser;
}
```

> `assignAdministratorRole()` rimane nel codice per retrocompatibilità, non viene rimosso.

---

### Step 3 — Creare la migration stub

File: `database/migrations/zz_2026_06_26_000001_add_editor_role.php.stub`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->insertOrIgnore([
            'name' => 'Editor',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Intentionally left empty: removing a role assigned to users
        // would cause FK constraint violations on model_has_roles.
    }
};
```

> Nota: si usa `DB::table()->insertOrIgnore()` invece di `Role::firstOrCreate()` per evitare che gli event Eloquent di Spatie tentino di pulire la cache dentro una transazione PostgreSQL (causerebbe SQLSTATE[25P02]).

---

### Step 4 — Commit su wm-package

```
feat(oc:8042): add Editor role and assign it during GeoHub import
```

File committati:
- `src/Services/RolesAndPermissionsService.php`
- `src/Services/Import/GeohubImportService.php`
- `database/migrations/zz_2026_06_26_000001_add_editor_role.php.stub`
- `docs/features/8042-utenti-importati-ruolo-assegnato-in-modo-errato/overview.md`
- `docs/features/8042-utenti-importati-ruolo-assegnato-in-modo-errato/plan.md`
- `docs/features/8042-utenti-importati-ruolo-assegnato-in-modo-errato/notes.md`

---

## Repo: maphub

### Step 5 — Pubblicare la migration e migrare

```bash
php artisan vendor:publish --tag=wm-package-migrations
php artisan migrate
```

Verificare che la migration `2026_06_26_000001_add_editor_role.php` compaia in `database/migrations/` e che `migrate` la esegua senza errori.

---

### Step 6 — Commit su maphub

```
feat(oc:8042): publish Editor role migration from wm-package
```

File committati:
- `database/migrations/2026_06_26_000001_add_editor_role.php`
- `docs/features/8042-utenti-importati-ruolo-assegnato-in-modo-errato/overview.md`
- `docs/features/8042-utenti-importati-ruolo-assegnato-in-modo-errato/plan.md`
- `docs/features/8042-utenti-importati-ruolo-assegnato-in-modo-errato/notes.md`
- `wm-package` (aggiornamento riferimento submodule)

---

### Step 7 — Verifica

```bash
# Verificare che il ruolo Editor esista nel db
php artisan tinker --execute="echo \Spatie\Permission\Models\Role::where('name','Editor')->exists() ? 'OK' : 'MISSING';"

# Verificare il comportamento di checkUserExistence con un utente senza ruoli
# (test manuale oppure Pest se presente)
```

---

## Note deploy

- Dopo la migration, eseguire `php artisan permission:cache-reset` se la Spatie permission cache è abilitata nell'ambiente target.
