> Ticket: oc:8842

# Notes — Messa in sicurezza PHP2Shell (CVE-2026-109618/109619) sullo shard maphub

## Esito (22/10/2026)

- **Applicato e verificato su locale, dev.maphub e prod.maphub**: `php -v` = 8.4.27 in tutti i
  container PHP, pool con 32M/128M e `session.upload_progress.enabled = off` (`php-fpm -tt`),
  POST 140MB → 413 pulito con worker vivi, POST 20MB passa, Horizon running, siti su (302).
  `queue:failed` invariato (un solo failed del 04/05, preesistente).
- **Porte dei servizi interni** (FPM, Postgres, Kibana) vincolate a `127.0.0.1` su entrambi i
  server, verificato dall'esterno.
- **Verifica di compromissione eseguita su dev e prod**: esito e dettagli nelle note riservate
  del ticket oc:8842 — il repo è pubblico e non li ospita.
- Applicazione sui server fatta sul working tree via scp (come oc:8838), commit a fine giro dopo
  il review-gate.

## Decisioni

- 2026-10-22: ricerca dei tag di ambiente (termini proposti: `maphub`, `backend`) non eseguita su scelta del dev; il ticket resta con i tag `php2shell` e `26Q4`.
- 2026-10-22: **niente snapshot Hetzner** su dev.maphub e prod.maphub, su decisione del dev — scostamento dall'ordine vincolante della strategia del tag php2shell, come già su osm2cai2 (oc:8840) e carg (oc:8838). Si segue il modello di quei due shard: immagine corrente taggata come punto di ritorno prima del rebuild, verifica di compromissione sulle evidenze lato host (log web dal 20/10, webroot, cron, utenti, chiavi SSH).
- 2026-10-22: limiti di upload del pool FPM da fissare con la misura reale sugli storage di prod.maphub (default condiviso 32M/128M come riferimento), su conferma del dev.
- 2026-10-22, esiti della challenge (decisioni del dev):
  - rischio «finestra di esposizione + rotazione rimandata» classificato **ipotetico** dal dev: verifica di compromissione standard (modello carg/osm2cai2), rotazione resta a fine giro shard;
  - proxy host e criterio 413: **allineamento a camminiditalia** — nessuno step dedicato al proxy, il test oversize dopo la patch rivela il limite host e lì si giudica la compatibilità;
  - rollback: per problemi di limiti **mai** `pre-8842` (riporterebbe in vita l'RCE) — si correggono i valori in `www.conf` e si rifà build+recreate; `pre-8842` solo come ultima spiaggia per immagine che non parte;
  - hardening **solo in `www.conf`** come camminiditalia (verificato su GitHub che il suo `develop.compose.yml` espone le stesse porte: situazione identica, stessa scelta); `php.ini` non si tocca;
  - **Horizon giù per primo** (`horizon:terminate` + attesa) prima di qualsiasi operazione sul server; check del disco libero prima della build;
  - misura dei limiti: tabella `media` di prod + controllo dei content-length dei POST grossi nei log host (già aperti per la verifica di compromissione).

- 2026-10-22: **stima saltata** su scelta del dev: `estimated_hours` non scritto su Orchestrator. Per riferimento, `wm-estimate` aveva prodotto 2,8h stimate (+0,9h di pianificazione misurata).

## Divergenze dal piano, task per task

### Task 1: misura su prod

I log Apache di prod (`maphub-access.log`, formato combined) non registrano la dimensione del
body delle richieste, quindi i content-length dei POST non sono misurabili dai log (Step 1.3).
La misura si fonda sulla sola tabella `media`: 12 righe, max ~2,9MB. Shard giovane; valori
scelti: default condiviso **32M/128M**.

### Task 2: bind delle porte dei servizi interni (scope aggiunto)

Durante la ricognizione (Task 1) è emerso che i compose pubblicavano le porte dei servizi
interni (FPM, Postgres, Kibana) su tutte le interfacce. Con l'ok del dev sono state vincolate a
`127.0.0.1` in `compose.yml` e `develop.compose.yml` — Apache le raggiunge via localhost, nulla
si rompe, e il recreate era già previsto dal piano. La motivazione di dettaglio è nelle note
riservate del ticket.

## Follow-up

- Rivedere le porte pubblicate da `develop.compose.yml` sul server di sviluppo (dettagli nelle note riservate del ticket oc:8842).
- Valutare build dell'immagine PHP in CI + push su registry + deploy che fa `pull`, per gli shard wm-package: oggi il pin in git non arriva ai server da solo e la build pesante gira a mano su ogni server (emerso dalla challenge di oc:8842; riguarda i repo degli shard, nessun intervento sui server oltre quelli di ogni singolo ticket). Il dev ha delegato la registrazione («fai come credi»), nessun ticket aperto.
