> Ticket: oc:8842

# Piano — Messa in sicurezza PHP2Shell (CVE-2026-109618/109619) sullo shard maphub

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans per eseguire
> questo piano task per task. Gli step usano checkbox (`- [ ]`).

**Goal:** portare maphub su `php:8.4.27-fpm` con il pool FPM irrobustito, su locale, dev.maphub e
prod.maphub, con verifica di compromissione lato host.

**Architettura:** replica di oc:8836 (camminiditalia) secondo la procedura condivisa; modifiche
solo nel repo maphub (`docker/configs/phpfpm/`), applicazione manuale sui server via SSH.

**Spec:** `docs/features/8842-messa-in-sicurezza-php2shell-shard-maphub/overview.md` +
`wm-package/docs/howto/messa-in-sicurezza-php-shard.md` (v1.6.0).

## Vincoli globali

- **Nessuna operazione git senza approvazione esplicita del dev**: i commit indicati sono
  istruzioni testuali, si eseguono solo dopo il review-gate.
- Ordine sui server: **Horizon giù per primo** → check disco → tag `pre-8842` → build `--pull` →
  recreate → verifiche. Mai invertire.
- Rebuild **sempre con `--pull`** (senza, la base in cache produce un no-op silenzioso).
- La verifica è sempre `php -v` **dentro** il container (e `php-fpm -tt` per il pool), mai
  l'esito della build.
- Hardening solo in `www.conf` via `php_admin_*`; `php.ini` non si tocca.
- Rollback per flusso rotto dai limiti: correggere `www.conf` e ribuildare — **mai** tornare a
  `pre-8842` (riporta in vita l'RCE).
- Le evidenze della verifica di compromissione si salvano **fuori dai repo** (sono pubblici).
- Niente snapshot Hetzner (decisione dev, vedi `notes.md`).

## Review Focus

Modi di fallimento che i check del piano non esercitano, dal più probabile:

1. `restore-db` o `import/geojson` con file sopra il nuovo limite → 413/validation error: flusso
   admin raro, nessun test previsto; se capita, vale la regola di rollback dei limiti (Task 2).
2. `scout-init` / `laravel` (one-shot o serve) che dopo il recreate restano su immagine vecchia
   perché non ricreati → coperto dal check per-container di Task 4/5 (`docker ps --filter
   ancestor` come oc:8836).
3. Limite del web server host sotto i nuovi valori FPM → upload legittimi bloccati: lo rivela il
   test under-limit di Task 4/5 (decisione dev: nessuno step dedicato al proxy).
4. Pacchetti apt nuovi portati da `--pull` che cambiano comportamento (es. policy ImageMagick):
   classificato ipotetico in challenge, nessun test media previsto.
5. Job Horizon ripartiti su code piene dopo il terminate → si osserva Horizon running e
   `php artisan queue:failed` vuoto nei check post-recreate.

---

### Task 1 — Misura dei flussi reali su prod (sola lettura)

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-1-misura-su-prod)

**Files:** nessuno (query e letture via SSH su prod.maphub).

- [ ] **Step 1.1:** dimensione massima e distribuzione dei media su prod:
  `ssh prod.maphub "docker exec postgres-maphub psql -U maphub -d maphub -At -c \"select count(*), max(size), round(avg(size)), percentile_cont(0.99) within group (order by size) from media\""`
  Atteso: conteggio e max in byte. Se il container o le credenziali differiscono, leggere `.env`
  del repo sul server (`/var/www/html/maphub/.env`).
- [ ] **Step 1.2:** ricognizione del web server host e dei log:
  `ssh prod.maphub "ps aux | grep -E 'nginx|apache' | head -3; ls /var/log/nginx /var/log/apache2 2>/dev/null"`
- [ ] **Step 1.3:** content-length dei POST più grossi negli access log host (stessa finestra che
  servirà alla verifica di compromissione): estrarre i 10 POST con body più grande.
- [ ] **Step 1.4:** scelta dei valori: se max misurato < 32M → **32M/128M** (default condiviso);
  altrimenti valore maggiore arrotondato per eccesso, e si segnala che va aggiornato il default
  nella procedura di wm-package (fuori scope, nota in `notes.md`).

### Task 2 — Repo: pin dell'immagine e hardening del pool

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-2-bind-delle-porte-dei-servizi-interni-scope-aggiunto)

**Files:**
- Modify: `docker/configs/phpfpm/Dockerfile:1`
- Modify: `docker/configs/phpfpm/www.conf:541-543` (e riga nuova per upload_progress)

- [ ] **Step 2.1:** `Dockerfile:1`: `FROM php:8.4.3-fpm` → `FROM php:8.4.27-fpm`.
- [ ] **Step 2.2:** `www.conf`: sotto gli attuali `php_admin_value` (riga 541-543):
  - `php_admin_value[upload_max_filesize] = 1024M` → valore scelto in Task 1 (rif. `32M`)
  - `php_admin_value[post_max_size] = 1024M` → valore scelto in Task 1 (rif. `128M`)
  - aggiungere `php_admin_flag[session.upload_progress.enabled] = off`
  - `memory_limit` resta `1024M` (invariato).
- [ ] **Step 2.3 (commit, solo dopo review-gate):**
  `fix(oc:8842): pin php:8.4.27-fpm e hardening del pool FPM (PHP2Shell)` — include anche i
  file di `docs/features/8842-*/` e il CLAUDE.md di Task 6.

### Task 3 — Applicazione e verifica in locale

**Files:** nessuno (operazioni Docker locali).

- [ ] **Step 3.1:** `docker tag wm-phpfpm:8.4 wm-phpfpm:8.4-pre-8842`
- [ ] **Step 3.2:** `docker compose -f local.compose.yml build --pull php`
  Atteso: build che scarica `php:8.4.27-fpm` (visibile nel log di pull).
- [ ] **Step 3.3:** recreate dei servizi che usano l'immagine:
  `docker compose -f local.compose.yml up -d --force-recreate php horizon scout-init laravel`
  (solo quelli presenti nel compose locale).
- [ ] **Step 3.4:** verifica versione per-container:
  `for c in php-maphub horizon-maphub laravel-maphub; do docker exec $c php -v | head -1; done`
  Atteso: `PHP 8.4.27` ovunque.
- [ ] **Step 3.5:** verifica pool: `docker exec php-maphub php-fpm -tt 2>&1 | grep -E "upload_max_filesize|post_max_size|upload_progress"`
  Atteso: i valori nuovi e `session.upload_progress.enabled = off`.
- [ ] **Step 3.6:** app viva: home locale risponde, Horizon running
  (`docker exec php-maphub php artisan horizon:status`).

### Task 4 — Applicazione su dev.maphub

**Files:** nessuno (SSH su dev.maphub; il repo sul server riceve le modifiche via git dal branch
mergiato — l'applicazione sui server avviene **dopo** i commit approvati al review-gate).

- [ ] **Step 4.1:** ricognizione: path del repo sul server, compose file in uso, nomi container
  (`docker ps`), web server host.
- [ ] **Step 4.2:** **Horizon giù per primo**: `docker exec horizon-<nome> php artisan horizon:terminate`
  e attesa che il container esca/si fermi (job in corso portati a termine).
- [ ] **Step 4.3:** check disco: `df -h /` — atteso spazio libero sufficiente per la build (>5GB).
- [ ] **Step 4.4:** `docker tag wm-phpfpm:8.4 wm-phpfpm:8.4-pre-8842`
- [ ] **Step 4.5:** `git pull` del repo sul server (porta pin e www.conf nuovi), poi build
  `--pull` e recreate dei servizi PHP col compose in uso.
- [ ] **Step 4.6:** verifiche come Task 3 (php -v per-container, `php-fpm -tt`, Horizon running,
  sito su).
- [ ] **Step 4.7:** test upload: POST oversize (sopra `post_max_size`) → atteso rifiuto pulito
  (413 dal proxy host o errore gestito), worker vivi dopo; POST sotto il limite → passa.
  Qui si legge anche il limite del proxy host e se ne giudica la compatibilità (modello oc:8836).

### Task 5 — Applicazione su prod.maphub

**Files:** nessuno (SSH su prod.maphub).

- [ ] **Step 5.1-5.7:** identici a Task 4 su prod.maphub.
  Downtime atteso: pochi secondi al recreate (modello oc:8838).
- [ ] **Step 5.8:** `php artisan queue:failed` vuoto (o invariato rispetto a prima del recreate).

### Task 6 — Verifica di compromissione (dev + prod, lato host)

**Files:** evidenze salvate fuori dai repo (es. `~/evidenze-8842/` sulla macchina locale).

- [ ] **Step 6.1:** per ogni server, dai log del web server host (finestra dal 20/10, lo storico
  che c'è): cercare i pattern di exploit PHP2Shell (`auto_prepend_file`, `allow_url_include`,
  `%AD`, scanner noti) e verificarne i codici di risposta.
  Atteso (modello oc:8838/8840): nessun pattern con risposta 2xx.
- [ ] **Step 6.2:** file PHP nuovi/modificati dal 20/10 in webroot e storage; `/tmp` dei
  container; crontab; utenti; `authorized_keys` (mtime); processi e connessioni anomale.
- [ ] **Step 6.3:** esito sintetico (senza dati sensibili) in `notes.md` e nelle note del ticket.

### Task 7 — Regola nel CLAUDE.md e chiusura documentale

**Files:**
- Modify: `CLAUDE.md` (repo maphub)
- Modify: `docs/features/8842-*/notes.md`

- [ ] **Step 7.1:** proporre al dev la riga per `## Regole del repo` (come oc:8836): le modifiche
  a `docker/configs/phpfpm/` non arrivano ai server col deploy — richiedono build `--pull` +
  recreate manuali su dev.maphub e prod.maphub.
- [ ] **Step 7.2:** aggiornare `notes.md` (deviazioni, valori scelti, esito verifica); pagina di
  conoscenza secondo la Fase: update-context di wm-plan.
- [ ] **Step 7.3 (commit, solo dopo review-gate):** vedi Step 2.3 — un unico commit con repo,
  docs e CLAUDE.md; poi PR verso `develop`.
