> Ticket: oc:8082

# CI/CD Maphub – Workflow GitHub Actions con deploy automatico e smoke test

## Cosa cambia

Il progetto ottiene una pipeline CI/CD completa basata su GitHub Actions:

- Nuovo workflow `develop-deploy.yml`: ad ogni push su `develop` esegue test → deploy SSH su `dev.maphub.it` → smoke test → notifica Slack `#zabbix-alerts`
- Workflow `prod-deploy.yml` aggiornato: aggiunge smoke test e notifica Slack dopo il deploy in produzione; corregge il nome container (`php_maphub` → `php-maphub`, hardcoded come pattern Webmapp)
- Script `scripts/deploy_dev.sh` aggiornato: aggiunge `horizon:terminate` con loop di attesa prima del restart; allineato a `deploy_prod.sh`
- Script `scripts/deploy_prod.sh` aggiornato: aggiunge loop di attesa post `horizon:terminate`
- Nuovo listener `DiagnosingHealth`: estende la rotta `/up` con check DB e cache (risposta 500 se uno dei due fallisce)
- Definizione esplicita dei nomi secret/variabili GitHub per l'ambiente dev
- `run-tests.yml` aggiornato: aggiunge service Redis (fix allineamento con camminiditalia) e `config:clear` pre-test

## Perché

Ridurre il rischio di regressioni silenziose in produzione e garantire visibilità immediata al team sullo stato dei deploy. L'ambiente `develop` diventa un vero staging con validazione automatica prima che il codice raggiunga `main`.

## Requisiti

- [ ] Workflow `develop-deploy.yml` triggerato su push a `develop`: esegue `run-tests.yml` (riuso via `workflow_call`), poi deploy SSH su dev, poi smoke test, poi notifica Slack con esito
- [ ] Workflow `prod-deploy.yml` aggiornato: aggiunge job smoke test post-deploy e notifica Slack; corregge nome container (`php_maphub` → `php-maphub` hardcoded, pattern Webmapp confermato da camminiditalia)
- [ ] `scripts/deploy_dev.sh` aggiornato: aggiunge `horizon:terminate` con loop di attesa (max 60s, polling ogni 5s su `horizon:status`) prima di `composer install` e `migrate`
- [ ] `scripts/deploy_prod.sh` aggiornato: aggiunge lo stesso loop di attesa post `horizon:terminate`
- [ ] Listener per evento `DiagnosingHealth`: tenta connessione DB (`DB::connection()->getPdo()`) e write cache (`Cache::put`) — lancia eccezione se fallisce, così `/up` restituisce 500
- [ ] Listener registrato in `AppServiceProvider`
- [ ] Smoke test su entrambi gli ambienti: due call per ambiente — `curl --fail --max-time 30 --retry 3 --retry-delay 5 https://<env>.maphub.it/up` e `curl --fail --max-time 30 --retry 3 --retry-delay 5 https://<env>.maphub.it/login`
- [ ] Notifica Slack sempre inviata (successo e fallimento) con: ambiente, status emoji, commit SHA, link al run GitHub Actions
- [ ] `run-tests.yml` aggiornato: aggiunge service Redis e step `config:clear` pre-test (allineamento con camminiditalia)
- [ ] Documentazione dei secret/variabili GitHub da configurare manualmente (commento nel workflow)

## Rischi

- **Container name da verificare su prod:** il pattern Webmapp confermato in camminiditalia è `php-{appname}` (trattino, hardcoded). Per maphub si assume `php-maphub` (prod) e `php-maphubdev` (dev). Verificare il nome reale con `docker ps` sul server prima del merge su `main`.
- **Smoke test dipendente da rete pubblica:** `curl` dall'action runner verso `dev.maphub.it` / `maphub.it` dipende da DNS e TLS. Mitigato con `--retry 3 --retry-delay 5 --max-time 30`.
- **Smoke test non copre regressioni funzionali:** `/up` e `/login` validano infrastruttura e bootstrap, non logica applicativa. Una migration distruttiva su una colonna non usata da queste route supera lo smoke test. Limite noto e accettato per questa fase.
- **Secrets non ancora configurati:** il workflow dev non parte finché `HOSTDEV`, `USERNAMEDEV`, `PORTDEV`, `SSHKEYDEV`, `SLACK_WEBHOOK_URL` non sono aggiunti su GitHub. Il piano include una checklist esplicita come prerequisito.

## Out of scope

- Rollback automatico post-smoke test (valutabile in futuro)
- Approval manuale prima del deploy (il merge su `develop`/`main` è la decisione di rilascio)
- Gestione rotation dei secret
- Ambienti oltre dev e prod (es. staging dedicato)
- Test di performance o end-to-end nel CI

## Moduli toccati

| File | Operazione |
|---|---|
| `.github/workflows/develop-deploy.yml` | Nuovo |
| `.github/workflows/prod-deploy.yml` | Aggiornato (smoke test + Slack + fix container name) |
| `.github/workflows/run-tests.yml` | Aggiornato (Redis service + config:clear pre-test) |
| `scripts/deploy_dev.sh` | Aggiornato (`horizon:terminate` + loop di attesa) |
| `scripts/deploy_prod.sh` | Aggiornato (loop di attesa post `horizon:terminate`) |
| `app/Listeners/CheckDatabaseHealth.php` | Nuovo |
| `app/Listeners/CheckCacheHealth.php` | Nuovo |
| `app/Providers/AppServiceProvider.php` | Aggiornato (registra listener `DiagnosingHealth`) |
