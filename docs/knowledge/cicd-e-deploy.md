# CI/CD e deploy

## Come funziona oggi

La pipeline è: test → deploy via SSH → smoke test su `/up` e `/login` → notifica Slack su
`#zabbix-alerts`. I workflow sono `run-tests.yml`, `develop-deploy.yml`, `prod-deploy.yml` e
`notify-slack.yml`, quest'ultimo riusabile per non duplicare il job in entrambi i deploy.

Su `/up` rispondono due listener di `DiagnosingHealth`, che controllano database e cache.

Il loop di attesa di Horizon è estratto in `scripts/horizon_terminate_wait.sh` e viene sorgente da
entrambi gli script di deploy.

## Perché così

- **I nomi dei container sono scritti a mano** — `php-maphub`, `php-maphubdev` — e non derivati da
  `${APP_NAME}` (oc:8082): quella variabile dipende dall'environment del server, e il pattern con i
  nomi espliciti è confermato anche da camminiditalia.
- **`horizon:terminate` funziona fra container separati** (oc:8082) perché usa Redis come canale,
  quindi `php-maphub` e `horizon-maphub` si parlano lo stesso.
- **Lo smoke test aspetta 15 secondi prima delle curl** (oc:8082): subito dopo il `migrate` il
  connection pool è esaurito, e senza attesa si ottengono falsi negativi.
