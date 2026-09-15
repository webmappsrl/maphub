# Import da GeoHub: layer, POI e utenti

## Come funziona oggi

**L'associazione fra Layer e EcPoi passa dalle taxonomy**, perché GeoHub non ha una pivot diretta
fra i due: `associateLayersWithEcPoi()` controlla in sequenza `taxonomy_themeables`,
`taxonomy_whereables` e `taxonomy_poi_typeables`.

I `geohub_poi_id` vengono deduplicati prima dell'`attach()`, e l'`attach()` stesso ha un check
`alreadyExists`, quindi un re-import è idempotente.

**Agli utenti importati viene assegnato il ruolo Editor**, ma solo se non ne hanno già uno:
`assignEditorRole()` agisce unicamente quando `$user->roles->isEmpty()`.

## Perché così

- **`taxonomy_theme` è il meccanismo primario** (oc:8043) per le app 63 e 44 — rispettivamente 4-48
  e 101-109 layer. Gli altri due esistono per i casi in cui il tema non basta, e vanno percorsi
  tutti e tre perché un POI può essere raggiunto da più di uno.
- **La deduplicazione serve prima dell'`attach()`** (oc:8043) proprio perché i tre meccanismi
  possono restituire lo stesso POI.
- **Il ruolo non si sovrascrive mai** (oc:8042): un ruolo assegnato a mano vince sull'import.
- **La migration usa `insertOrIgnore`, non `Role::firstOrCreate`** (oc:8042): quest'ultimo ha un
  side-effect sulla cache di Spatie che, dentro una transazione PostgreSQL, lascia lo stato
  inconsistente.
- **`assignAdministratorRole()` è conservato per retrocompatibilità** (oc:8042) ma non viene più
  chiamato da `checkUserExistence()`.
