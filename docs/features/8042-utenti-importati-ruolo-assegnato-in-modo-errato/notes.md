> Ticket: oc:8042

# Notes — Utenti importati: ruolo assegnato in modo errato (maphub)

## Deviazioni dal piano

- **Ordine di lavoro**: la migration è stata pubblicata in maphub prima del completamento del codice wm-package.
- **`assignEditorRole`**: implementato con pattern `Role::where()` + `seedDatabase()` fallback (come `assignAdministratorRole`), non con `Role::firstOrCreate()` diretto come nel plan originale.

## Decisioni

- **Migration con `insertOrIgnore`**: evita side-effect della cache Spatie in transazione PostgreSQL (SQLSTATE[25P02]).
- **Nessuna migrazione retroattiva**: gli utenti già importati con ruolo Administrator non vengono modificati.

## Follow-up

- I test di oc:8072 che usavano `Validator` al posto di `Editor` possono essere aggiornati ora che `Editor` è nel seed (out of scope per questo ticket).
