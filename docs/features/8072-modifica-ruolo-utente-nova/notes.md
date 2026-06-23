> Ticket: oc:8072

# Notes — Modifica ruolo utente Nova

## Deviazioni dal piano

- **Nomi file test diversi dal piano**: il piano prevedeva `UserResourceTest.php` e `AbstractUserResourceTest.php`; i file effettivi sono `UserResourceRoleGuardTest.php` e `AbstractUserResourceRoleGuardTest.php` per rendere esplicito lo scopo del test.
- **Override `fields()` in `app/Nova/User.php`**: non previsto nel piano originale. Emerso dopo aver verificato l'UI: `RoleBooleanGroup` e `PermissionBooleanGroup` apparivano come colonne nell'index. Il fix è stato aggiunto come override locale in maphub (non in wm-package) per lasciare il package agnostico sulla visibilità nell'index.

## Bug trovati

- **`RefreshDatabase` in test maphub cancellava il database di dev**: il `phpunit.xml` non configura un database separato per i test, quindi `RefreshDatabase` eseguiva `migrate:fresh` sul database PostgreSQL di sviluppo. Sostituito con `DatabaseTransactions` che avvolge ogni test in una transazione con rollback finale — nessun dato di dev viene perso.

## Decisioni

- **`Editor` → `Validator` nei test**: il ruolo `Editor` non è creato da `RolesAndPermissionsService::seedDatabase()` (che crea solo Administrator, Validator, Guest). Usare `Validator` evita la necessità di seed aggiuntivi e mantiene i test autocontenuti.
- **`$editor` → `$nonSuperAdmin` nel test wm-package**: la variabile rappresentava un utente Administrator generico (non un Editor), il nome era fuorviante.
- **`PermissionBooleanGroup` incluso nonostante il ticket parli solo di ruoli**: ruolo e permessi sono logicamente inseparabili in Spatie — modificare un ruolo senza poter gestire i permessi associati sarebbe un'esperienza incompleta. Decisione presa con il developer.

## Follow-up

- Audit log delle modifiche ai ruoli: non implementato in questo ciclo. Se necessario, aggiungere un observer su `model_has_roles` o usare il package `spatie/laravel-activitylog`.
- `phpunit.xml` andrebbe configurato con un database di test separato per evitare il problema `RefreshDatabase` in futuro.
