> Ticket: oc:8042

# Notes — Utenti importati: ruolo assegnato in modo errato (maphub)

## Deviazioni dal piano

- **Migration: `Role::firstOrCreate` → `DB::table()->insertOrIgnore()`**: il piano prevedeva `Role::firstOrCreate()` nella migration. Ha causato `SQLSTATE[25P02]: In failed sql transaction` — Spatie tenta di pulire la cache dalla tabella `cache` dentro la transazione PostgreSQL, che abortisce. Sostituito con `DB::table('roles')->insertOrIgnore([...])` che bypassa i model event Spatie.
- **`assignEditorRole`**: implementato con pattern `Role::where()` + `seedDatabase()` fallback (come `assignAdministratorRole`), non con `Role::firstOrCreate()` diretto come nel plan originale — coerente con il pattern interno al service.

## Bug trovati

**Policy wm-package e visibilità Nova per Editor** (→ oc:8162)
Il test manuale con un utente Editor ha rivelato che le policy esistenti erano dead code finché il ruolo Editor non esisteva. Con il ruolo attivo si sono manifestati:
- `LayerPolicy`: Editor non può creare layer ma può eliminarli (deve invece poter modificare ma non eliminare)
- `MediaPolicy::before()`: bypassa tutte le verifiche per qualsiasi utente autenticato
- `UserPolicy::before()`: controlla ruoli inesistenti (`Admin`, `Author`, `Contributor`)
- Nova: Editor può creare/modificare taxonomy (deve solo visualizzarle); UGC visibile anche senza app con UGC attivi

Tracciati in **oc:8162**.

## Decisioni

- **Nessuna migrazione retroattiva**: confermato in fase di analisi che nel db non esistevano utenti importati da GeoHub.
- **`assignAdministratorRole()` conservato**: non rimosso per retrocompatibilità con eventuali chiamanti esterni.

## Follow-up

- **oc:8162** — Fix permessi e visibilità Nova per ruolo Editor (policy + menu Nova)
- I test di oc:8072 che usavano `Validator` al posto di `Editor` possono essere aggiornati ora che `Editor` è nel seed (out of scope per questo ticket).
