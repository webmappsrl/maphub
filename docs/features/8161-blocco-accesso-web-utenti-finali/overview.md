> Ticket: oc:8161

# Blocco accesso web per utenti finali (integrazione Maphub)

Tutta la logica (permesso `access-nova`, meccanismo di blocco login, seed dei ruoli di gestione, comportamento fail-closed, rischi, out of scope) è definita nel wm-package — vedi `wm-package/docs/features/8161-blocco-accesso-web-utenti-finali/overview.md`, documento autoritativo per questo ticket.

Questo documento elenca solo cosa va fatto in Maphub.

## Cosa va fatto qui

- `app/Providers/NovaServiceProvider.php`: il gate `viewNova` diventa `$user->can('access-nova')` (rimuove il check su `hasRole('Guest')`).
- `routes/web.php`: la route `osm-import-reports/{token}` (middleware `web`+`auth`) aggiunge il middleware `can:access-nova`.
- Test Pest di integrazione in Maphub che verificano il comportamento end-to-end con i ruoli reali del progetto (login Nova, route custom, API JWT, password reset), usando il permesso `access-nova` definito nel wm-package.

## Moduli toccati

- `app/Providers/NovaServiceProvider.php`
- `routes/web.php`
- test Pest di integrazione (nuovi)
