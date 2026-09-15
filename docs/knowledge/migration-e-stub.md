# Le migration di wm-package e i loro stub

> Fonte di verità del disegno, con i diagrammi e i casi d'uso A–H:
> `docs/features/8218-cicd-migration-wm-package-permission-cache/overview.md`.

## Come funziona oggi

Gli stub di wm-package sono **obbligatori**, e il gate che li verifica guarda lo **schema reale del
database**, non i nomi dei file né una mappatura mantenuta a mano.

Le migration arrivano **solo da git**. La risoluzione è sempre locale → commit → push: gli script di
deploy non generano file.

**In CI** (job `tests` di `run-tests.yml`, sullo stesso DB PostGIS dei test):

```
migrate → publish-missing-migrations --dry-run → php artisan test → deploy (se passa)
```

Il job `deploy` dipende solo da `tests`. In deploy: `migrate --force` e poi
`permission:cache-reset`, sempre, senza condizioni.

**I comandi attivi sono tre:**

| Comando | Ruolo |
|---|---|
| `wm-package:publish-missing-migrations --dry-run` | gate CI e verifica locale; **exit 1** se gli stub non sono allineati |
| `wm-package:publish-missing-migrations` | pubblica i file mancanti in `database/migrations/` |
| `wm-package:publish-migration <stub>` | publish di un singolo stub, per i falsi positivi da suffisso |

**Come decide il gate**, dopo `migrate`:

1. schema completo rispetto allo stub → allineato, anche senza il file di wm-package, se un'altra
   migration ha lo stesso effetto;
2. file committato **identico** allo stub ma assente dalla tabella `migrations` → exit 1, serve
   `migrate`;
3. gap nello schema e nessun file identico → exit 1, va pubblicata la migration del package.

## Perché così

- **Il confronto è sullo schema, non sui nomi** (oc:8218), ed è il caso di `create_users_table` a
  spiegarlo: `0001_..._create_users_table.php` è di Laravel e fa `Schema::create`, mentre lo stub di
  wm-package aggiunge `balance`, `fiscal_code` e `app_id` con `Schema::table`. **Suffisso uguale non
  vuol dire schema allineato.**

## Cosa fare, caso per caso

| Scenario | Azione |
|---|---|
| Push normale, tutto allineato | `--dry-run` esce 0, la CI passa |
| Nuovo stub dopo un update di wm-package | `publish-missing-migrations` → `migrate` → commit |
| `--dry-run` segnala stub e gap di colonne | pubblica la migration del package: il file Laravel omonimo non basta |
| File identico già in git, non migrato | solo `php artisan migrate` |
| Schema già a posto via una migration custom con altro nome | nessuna azione |
| La CI fallisce | si risolve in locale — **mai** un publish sul server |
