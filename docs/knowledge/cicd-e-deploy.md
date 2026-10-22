# CI/CD e deploy

## Come funziona oggi

La pipeline è: test → deploy via SSH → smoke test su `/up` e `/login` → notifica Slack su
`#zabbix-alerts`. I workflow sono `run-tests.yml`, `develop-deploy.yml`, `prod-deploy.yml` e
`notify-slack.yml`, quest'ultimo riusabile per non duplicare il job in entrambi i deploy.

Su `/up` rispondono due listener di `DiagnosingHealth`, che controllano database e cache.

Il loop di attesa di Horizon è estratto in `scripts/horizon_terminate_wait.sh` e viene sorgente da
entrambi gli script di deploy.

Il deploy **non ricostruisce mai l'immagine PHP**: una modifica a `docker/configs/phpfpm/` o ai
`ports` dei compose va applicata sui server a mano, con la sequenza della procedura condivisa
(`wm-package/docs/howto/messa-in-sicurezza-php-shard.md`). In `compose.yml` e
`develop.compose.yml` le porte dei servizi interni (FPM, Postgres, Kibana) sono vincolate a
`127.0.0.1`: il web server dell'host le raggiunge via localhost.

## Perché così

- **I nomi dei container sono scritti a mano** — `php-maphub`, `php-maphubdev` — e non derivati da
  `${APP_NAME}` (oc:8082): quella variabile dipende dall'environment del server, e il pattern con i
  nomi espliciti è confermato anche da camminiditalia.
- **`horizon:terminate` funziona fra container separati** (oc:8082) perché usa Redis come canale,
  quindi `php-maphub` e `horizon-maphub` si parlano lo stesso.
- **Lo smoke test aspetta 15 secondi prima delle curl** (oc:8082): subito dopo il `migrate` il
  connection pool è esaurito, e senza attesa si ottengono falsi negativi.
- **Il `FROM` del Dockerfile PHP è pinnato alla versione esatta** (oc:8842): l'aggiornamento del
  motore diventa un commit tracciato invece di un evento invisibile. Il rebuild va fatto sempre
  con `--pull`: con la base in cache, senza `--pull` la build riusa i layer vecchi e dichiara
  successo senza aggiornare nulla.
- **Le porte dei servizi interni sono vincolate a `127.0.0.1`** (oc:8842): non devono essere
  raggiungibili dall'esterno; la motivazione di dettaglio è nelle note del ticket.
