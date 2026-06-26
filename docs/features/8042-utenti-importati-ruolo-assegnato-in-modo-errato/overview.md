> Ticket: oc:8042

# Utenti importati: ruolo assegnato in modo errato

## Cosa cambia

Il sistema smette di assegnare il ruolo `Administrator` agli utenti creati durante l'import da GeoHub. Viene aggiunto il ruolo `Editor` (mancante) e assegnato automaticamente agli utenti importati **solo se non hanno già un ruolo assegnato**, preservando configurazioni manuali. Una migration garantisce che il ruolo esista su tutti gli ambienti.

## Perché

Il comando `wm:import-from-geohub` crea gli utenti associati alle app importate e assegna loro il ruolo `Administrator`, rendendo visibile l'intera sezione admin di Nova a utenti che dovrebbero avere accesso limitato. Il ruolo `Editor` — già referenziato nelle policy esistenti (MediaPolicy, LayerPolicy, UgcPoiPolicy, ecc.) — non era mai stato creato nel database.

## Requisiti

- [x] Il ruolo `Editor` esiste nel database su tutti gli ambienti (garantito da migration idempotente con `insertOrIgnore`)
- [x] `RolesAndPermissionsService::seedDatabase()` include il ruolo `Editor`
- [x] Il comando `wm:import-from-geohub` assegna il ruolo `Editor` agli utenti importati **solo se non hanno già ruoli assegnati** (nessuna sovrascrittura di configurazioni manuali)
- [x] Nessuna migrazione retroattiva sugli utenti esistenti (confermato: nessun utente importato da GeoHub nel db attuale)

## Rischi

Nessun rischio aperto: le decisioni di design li risolvono tutti a priori (vedi sotto).

- **`assignRole()` aggiunge senza rimuovere** → risolto: assegnazione condizionale (`$user->roles->isEmpty()`) — se l'utente ha già ruoli, il metodo non interviene
- **Ambienti già migrati** → risolto: migration usa `insertOrIgnore`, è idempotente
- **Fallback seed cerca 'Administrator'** → risolto: il nuovo metodo `assignEditorRole()` cerca 'Editor' e il fallback chiama `seedDatabase()` che ora include 'Editor'

## Out of scope

- Gestione ruolo utente tramite Nova (altro ticket)
- Visibilità sezioni Nova per il ruolo Editor (altro ticket)
- Migrazione retroattiva degli utenti con ruolo Administrator errato

## Moduli toccati

**wm-package:**
- `src/Services/Import/GeohubImportService.php` — `checkUserExistence()` assegnazione condizionale + nuovo metodo `assignEditorRole()`
- `src/Services/RolesAndPermissionsService.php` — aggiunta `Editor` a `seedDatabase()`
- `database/migrations/` — nuova migration stub per creare il ruolo Editor con `insertOrIgnore`

**maphub (repo principale):**
- `database/migrations/` — migration pubblicata da wm-package (via `vendor:publish --tag=wm-package-migrations`)
