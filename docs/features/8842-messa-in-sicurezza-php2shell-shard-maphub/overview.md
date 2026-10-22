> Ticket: oc:8842

# Messa in sicurezza PHP2Shell (CVE-2026-109618/109619) sullo shard maphub

## Cosa cambia

Replica su maphub della messa in sicurezza PHP2Shell già collaudata su forestas (oc:8833) e
camminiditalia (oc:8836), seguendo `wm-package/docs/howto/messa-in-sicurezza-php-shard.md`
(v1.6.0). Lavoro a step: **locale → dev.maphub → prod.maphub** (entrambi Hetzner Cloud,
verificato via whois).

1. **Pin dell'immagine PHP:** `docker/configs/phpfpm/Dockerfile` passa da `php:8.4.3-fpm`
   (vulnerabile) a `php:8.4.27-fpm`. Il tag `wm-phpfpm:8.4` è buildato da quel Dockerfile e
   usato dai servizi `php`, `horizon`, `scout-init` e `laravel` (dichiarati fra `compose.yml`,
   `develop.compose.yml` — dove `php` è un extends — e `local.compose.yml`): vanno ricreati
   tutti quelli attivi su ciascun ambiente.
2. **Pulizia permanente del pool FPM** (`docker/configs/phpfpm/www.conf`):
   `php_admin_flag[session.upload_progress.enabled] = off` (oggi non impostato → default On,
   la condizione che PHP2Shell sfrutta) e riduzione dei limiti di upload dagli attuali
   `upload_max_filesize = 1024M` / `post_max_size = 1024M` a valori commisurati ai dati reali,
   **misurati su prod.maphub prima di scegliere**: tabella `media` + controllo dei
   content-length dei POST grossi nei log del web server host (riferimento: default condiviso
   32M/128M della procedura; il campione locale è inservibile: 11 media, max ~2,8MB).
   Come su camminiditalia, si tocca **solo `www.conf`**: `php.ini` resta com'è.
3. **Applicazione sui server:** su dev e prod l'aggiornamento è manuale — il deploy
   (`prod-deploy.yml` → `scripts/deploy_prod.sh`) non ricostruisce l'immagine. Su ogni server,
   in quest'ordine: **Horizon giù per primo** (`php artisan horizon:terminate` + attesa che i
   job in corso finiscano), check del disco libero, tag dell'immagine corrente come punto di
   ritorno (`pre-8842`), rebuild con `--pull`, ricreazione dei servizi, verifica `php -v`
   dentro ogni container PHP.
4. **Verifica di compromissione** su dev e prod, sulle evidenze lato host (log del web server
   dal 20/10, webroot, cron, utenti, chiavi SSH), come su carg (oc:8838) e osm2cai2 (oc:8840).

## Perché

Il 21/10/2026 sono state pubblicate due vulnerabilità critiche del motore PHP («PHP2Shell»,
CVSS 9.2): RCE non autenticata, exploit pubblico, attacchi già osservati in rete. La falla è
nel motore, non nel codice applicativo: maphub gira su `php:8.4.3-fpm`, vulnerabile; la
versione corretta è la 8.4.27. In più il pool FPM ha `session.upload_progress` attivo (default)
e limiti di upload a 1024M: la combinazione che l'exploit sfrutta. Strategia comune nel tag
Orchestrator `php2shell`; maphub è l'ultimo shard wm-package rimasto.

## Requisiti

- [ ] `docker/configs/phpfpm/Dockerfile` pinnato a `php:8.4.27-fpm`
- [ ] `www.conf`: `php_admin_flag[session.upload_progress.enabled] = off`
- [ ] Misura reale su prod.maphub prima di fissare i limiti: tabella `media` + content-length
      dei POST grossi nei log del web server host
- [ ] Su ogni server, prima di qualsiasi operazione: `horizon:terminate` e attesa; check del
      disco libero prima della build
- [ ] `www.conf`: `upload_max_filesize` / `post_max_size` ridotti ai valori scelti sulla misura
      (riferimento 32M/128M)
- [ ] Su ogni server, prima del rebuild: `docker tag` dell'immagine corrente come `pre-8842`
- [ ] Rebuild sempre con `--pull`; verifica con `php -v` **dentro** ogni container PHP
      (mai l'esito della build)
- [ ] Criterio di done (come oc:8836): `php -v` = 8.4.27 in tutti i container PHP, pool coi
      valori nuovi (`php-fpm -tt`), POST oversize → 413 pulito con worker vivo, upload sotto il
      limite che passano, Horizon running, sito su — su locale, dev e prod
- [ ] Verifica di compromissione lato host su dev.maphub e prod.maphub (log dal 20/10, webroot,
      cron, utenti, chiavi SSH), esito registrato nel ticket

## Rischi

Esiti della Fase: challenge (dettaglio delle decisioni in `notes.md`):

- **Niente snapshot Hetzner** (decisione del dev, come oc:8838/oc:8840): il rebuild distrugge le
  evidenze nei container. Mitigazione: verifica di compromissione sulle evidenze lato host, che
  negli shard fratelli si sono rivelate le più significative (log web, webroot montata), e tag
  `pre-8842` come punto di ritorno operativo.
- **Riduzione dei limiti di due ordini di grandezza (1024M → ~32M):** può bloccare flussi
  legittimi (`restore-db`, `import/geojson` possono portare file grossi). Mitigazione: misura
  reale su prod (tabella `media` + POST nei log host) prima di scegliere i valori;
  `post_max_size` largo rispetto a `upload_max_filesize` come da default condiviso.
- **Il rollback `pre-8842` riporta in vita l'RCE** (8.4.3 + upload_progress on + 1024M):
  mitigazione: per un flusso legittimo rotto dai nuovi limiti **non si fa mai rollback
  dell'immagine** — si correggono i valori in `www.conf` e si rifà build+recreate (minuti, la
  patch del motore resta); `pre-8842` è solo l'ultima spiaggia per un'immagine che non parte.
- **Il test oversize dipende dal limite del web server host**, che non è nel repo: come su
  camminiditalia, nessuno step dedicato — il test oversize dopo la patch rivela il limite host
  e lì si giudica la compatibilità.
- **Recreate con job in corso:** mitigato da `horizon:terminate` come prima operazione su ogni
  server.
- **Il deploy non ricostruisce l'immagine:** una patch committata nel repo non arriva ai server
  da sola. Mitigazione: applicazione manuale documentata passo-passo nel piano, e regola nel
  CLAUDE.md (come su camminiditalia). Il fix strutturale (build in CI + registry) è registrato
  come follow-up in `notes.md`, fuori da questo ticket.

## Out of scope

- Rotazione dei segreti economici (password DB, chiavi MinIO, token esterni): rimandata a fine
  giro shard, come oc:8833/oc:8836. `JWT_SECRET` non si ruota mai.
- Il Dockerfile boilerplate di wm-package (`wm-package/docker/configs/phpfpm/Dockerfile`, base
  mobile `php:8.4-fpm`): non è usato dai container di maphub; si tocca solo ciò che gira sui
  server.
- Test di upload legittimo autenticato end-to-end (via Nova/API con credenziali): lasciato
  fuori come su forestas e camminiditalia, per scelta del dev.
- Snapshot Hetzner dei server: escluso per decisione del dev (vedi Rischi).
- Interventi sulla configurazione del web server host (allineamento a camminiditalia: si
  giudica col test oversize).
- `php.ini`: non si tocca (hardening solo nel pool FPM, come da procedura condivisa).
- Build dell'immagine in CI + registry: follow-up in `notes.md`, non in questo ticket.

## Moduli toccati

| File / operazione | Repo / dove |
|---|---|
| `docker/configs/phpfpm/Dockerfile` (pin 8.4.27) | maphub |
| `docker/configs/phpfpm/www.conf` (upload_progress off, limiti upload) | maphub |
| `docs/features/8842-.../` (overview, plan, notes) | maphub |
| Build `--pull` + recreate servizi PHP, tag `pre-8842`, verifica compromissione | server dev.maphub e prod.maphub (fuori repo) |
| wm-package | **non toccato** |
