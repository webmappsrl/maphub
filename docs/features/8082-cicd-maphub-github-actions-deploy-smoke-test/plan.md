> Ticket: oc:8082

# Plan — CI/CD Maphub – Workflow GitHub Actions con deploy automatico e smoke test

---

## Prerequisiti (da completare manualmente PRIMA di qualsiasi deploy)

- [ ] **Verificare il nome container sul server prod:** eseguire `docker ps --filter name=php` e confermare che sia `php-maphub` (prod) e `php-maphubdev` (dev). Se diverso, aggiornare i workflow di conseguenza prima del merge.
- [ ] **Configurare i secret/variabili GitHub** nel repository `maphub`:
  | Tipo | Nome | Descrizione |
  |------|------|-------------|
  | Variable | `HOSTDEV` | IP/hostname del server dev |
  | Variable | `USERNAMEDEV` | Utente SSH del server dev |
  | Variable | `PORTDEV` | Porta SSH del server dev |
  | Secret | `SSHKEYDEV` | Chiave privata SSH per il server dev |
  | Secret | `SLACK_WEBHOOK_URL` | Webhook Slack per il canale #zabbix-alerts |

---

## Step 1 — Branch

```bash
git checkout -b feature/oc-8082-cicd-maphub-github-actions-deploy-smoke-test
```

---

## Step 2 — Listener `DiagnosingHealth`: check DB e cache su `/up`

### 2a. Creare `app/Listeners/CheckDatabaseHealth.php`

```php
<?php

namespace App\Listeners;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;

class CheckDatabaseHealth
{
    public function handle(DiagnosingHealth $event): void
    {
        DB::connection()->getPdo();
    }
}
```

### 2b. Creare `app/Listeners/CheckCacheHealth.php`

```php
<?php

namespace App\Listeners;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Cache;

class CheckCacheHealth
{
    public function handle(DiagnosingHealth $event): void
    {
        Cache::put('health_check', true, 10);
    }
}
```

### 2c. Registrare i listener in `app/Providers/AppServiceProvider.php`

Aggiungere nel metodo `boot()`:

```php
use App\Listeners\CheckCacheHealth;
use App\Listeners\CheckDatabaseHealth;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Event;

// Nel boot():
Event::listen(DiagnosingHealth::class, CheckDatabaseHealth::class);
Event::listen(DiagnosingHealth::class, CheckCacheHealth::class);
```

**Verifica:** avviare il container e chiamare `curl http://localhost/up` — deve rispondere 200. Stoppare il DB e richiamare — deve rispondere 500.

**Commit:**
```
feat(oc:8082): add DiagnosingHealth listeners for DB and cache checks on /up
```

---

## Step 3 — Aggiornare `scripts/deploy_dev.sh`

Aggiungere `horizon:terminate` con loop di attesa (max 60s) e allineare la struttura a `deploy_prod.sh`:

```bash
#!/bin/bash
set -e

echo "Dev deployment started ..."

# Enter maintenance mode or return true if already in maintenance mode
(php artisan down) || true

git submodule update --init --recursive

# Install composer dependencies
composer install --no-interaction --prefer-dist --optimize-autoloader

php artisan optimize

php artisan migrate --force

# Gracefully terminate Horizon and wait for workers to stop (max 60s)
php artisan horizon:terminate
echo "Waiting for Horizon to stop..."
for i in $(seq 1 12); do
  sleep 5
  if php artisan horizon:status 2>/dev/null | grep -qiE "inactive|stopped"; then
    echo "Horizon stopped after $((i * 5))s."
    break
  fi
  echo "Horizon still running... ($((i * 5))s elapsed)"
done

php artisan up

echo "Dev deployment finished!"
```

**Commit:**
```
fix(oc:8082): add horizon:terminate wait loop and --force migrate to deploy_dev.sh
```

---

## Step 4 — Aggiornare `scripts/deploy_prod.sh`

Aggiungere il loop di attesa post `horizon:terminate`:

```bash
#!/bin/bash
set -e

echo "Prod deployment started ..."

php artisan down

git submodule update --init --recursive

composer install --no-interaction --prefer-dist --optimize-autoloader

php artisan optimize

php artisan migrate --force

# Gracefully terminate Horizon and wait for workers to stop (max 60s)
php artisan horizon:terminate
echo "Waiting for Horizon to stop..."
for i in $(seq 1 12); do
  sleep 5
  if php artisan horizon:status 2>/dev/null | grep -qiE "inactive|stopped"; then
    echo "Horizon stopped after $((i * 5))s."
    break
  fi
  echo "Horizon still running... ($((i * 5))s elapsed)"
done

php artisan up

echo "Prod deployment finished!"
```

**Commit:**
```
fix(oc:8082): add horizon:terminate wait loop to deploy_prod.sh
```

---

## Step 5 — Aggiornare `.github/workflows/run-tests.yml`

Aggiungere il service Redis e il `config:clear` pre-test (allineamento con camminiditalia):

```yaml
services:
  db:
    image: postgis/postgis:17-3.5-alpine
    # ... invariato
  redis:
    image: redis:7-alpine
    ports:
      - 6379:6379
    options: --health-cmd "redis-cli ping" --health-interval 10s --health-timeout 5s --health-retries 5
```

E aggiungere lo step pre-test:
```yaml
- name: Clear config cache before tests
  run: php artisan config:clear
```
(dopo `Optimize` e prima di `Laravel Tests`)

**Commit:**
```
fix(oc:8082): add Redis service and config:clear pre-test to run-tests.yml
```

---

## Step 6 — Creare `.github/workflows/develop-deploy.yml`

```yaml
# Secrets/variables richiesti:
#   vars.HOSTDEV, vars.USERNAMEDEV, vars.PORTDEV
#   secrets.SSHKEYDEV, secrets.SLACK_WEBHOOK_URL
#   secrets.NOVA_USERNAME, secrets.NOVA_PASSWORD (ereditati da run-tests.yml)

name: Laravel deploy DEV

on:
  push:
    branches:
      - develop

jobs:
  tests:
    uses: ./.github/workflows/run-tests.yml
    secrets: inherit

  deploy:
    name: Deploy DEV
    needs: [tests]
    runs-on: ubuntu-latest
    steps:
      - name: SSH connection and run scripts/deploy_dev.sh
        uses: appleboy/ssh-action@master
        with:
          host: ${{ vars.HOSTDEV }}
          username: ${{ vars.USERNAMEDEV }}
          port: ${{ vars.PORTDEV }}
          key: ${{ secrets.SSHKEYDEV }}
          script: >
            cd /var/www/html/maphub &&
            git pull &&
            git submodule update --init --recursive &&
            docker exec php-maphubdev bash scripts/deploy_dev.sh

  smoke-test:
    name: Smoke Test DEV
    needs: [deploy]
    runs-on: ubuntu-latest
    steps:
      - name: Health check /up
        run: curl --fail --max-time 30 --retry 3 --retry-delay 5 https://dev.maphub.it/up

      - name: Check /login
        run: curl --fail --max-time 30 --retry 3 --retry-delay 5 https://dev.maphub.it/login

  notify:
    name: Notify Slack
    needs: [tests, deploy, smoke-test]
    runs-on: ubuntu-latest
    if: always()
    steps:
      - name: Send Slack notification
        run: |
          if [[ "${{ needs.tests.result }}" == "success" && \
                "${{ needs.deploy.result }}" == "success" && \
                "${{ needs.smoke-test.result }}" == "success" ]]; then
            EMOJI="✅"
            COLOR="good"
            TEXT="Deploy DEV completato con successo"
          else
            EMOJI="❌"
            COLOR="danger"
            TEXT="Deploy DEV fallito (tests=${{ needs.tests.result }}, deploy=${{ needs.deploy.result }}, smoke=${{ needs.smoke-test.result }})"
          fi
          curl -s -X POST "${{ secrets.SLACK_WEBHOOK_URL }}" \
            -H "Content-Type: application/json" \
            -d "{
              \"text\": \"$EMOJI *Maphub DEV* — $TEXT\",
              \"attachments\": [{
                \"color\": \"$COLOR\",
                \"fields\": [
                  {\"title\": \"Commit\", \"value\": \"${{ github.sha }}\", \"short\": true},
                  {\"title\": \"Autore\", \"value\": \"${{ github.actor }}\", \"short\": true},
                  {\"title\": \"Dettagli\", \"value\": \"<${{ github.server_url }}/${{ github.repository }}/actions/runs/${{ github.run_id }}|Vedi run>\", \"short\": false}
                ]
              }]
            }"
```

**Commit:**
```
feat(oc:8082): add develop-deploy.yml with tests, SSH deploy, smoke test and Slack notify
```

---

## Step 7 — Aggiornare `.github/workflows/prod-deploy.yml`

Aggiungere job `smoke-test` e `notify`; correggere il nome container da `php_${APP_NAME}` a `php-maphub` (hardcoded):

```yaml
name: Laravel deploy PROD

on:
  push:
    branches:
      - main

jobs:
  tests:
    uses: ./.github/workflows/run-tests.yml
    secrets: inherit

  deploy:
    name: Deploy PROD
    needs: [tests]
    runs-on: ubuntu-latest
    steps:
      - name: SSH connection and run scripts/deploy_prod.sh
        uses: appleboy/ssh-action@master
        with:
          host: ${{ vars.HOSTPROD }}
          username: ${{ vars.USERNAMEPROD }}
          port: ${{ vars.PORTPROD }}
          key: ${{ secrets.SSHKEYPROD }}
          script: >
            cd /var/www/html/maphub &&
            git pull &&
            git submodule update --init --recursive &&
            docker exec php-maphub bash scripts/deploy_prod.sh

  smoke-test:
    name: Smoke Test PROD
    needs: [deploy]
    runs-on: ubuntu-latest
    steps:
      - name: Health check /up
        run: curl --fail --max-time 30 --retry 3 --retry-delay 5 https://maphub.it/up

      - name: Check /login
        run: curl --fail --max-time 30 --retry 3 --retry-delay 5 https://maphub.it/login

  notify:
    name: Notify Slack
    needs: [tests, deploy, smoke-test]
    runs-on: ubuntu-latest
    if: always()
    steps:
      - name: Send Slack notification
        run: |
          if [[ "${{ needs.tests.result }}" == "success" && \
                "${{ needs.deploy.result }}" == "success" && \
                "${{ needs.smoke-test.result }}" == "success" ]]; then
            EMOJI="✅"
            COLOR="good"
            TEXT="Deploy PROD completato con successo"
          else
            EMOJI="❌"
            COLOR="danger"
            TEXT="Deploy PROD fallito (tests=${{ needs.tests.result }}, deploy=${{ needs.deploy.result }}, smoke=${{ needs.smoke-test.result }})"
          fi
          curl -s -X POST "${{ secrets.SLACK_WEBHOOK_URL }}" \
            -H "Content-Type: application/json" \
            -d "{
              \"text\": \"$EMOJI *Maphub PROD* — $TEXT\",
              \"attachments\": [{
                \"color\": \"$COLOR\",
                \"fields\": [
                  {\"title\": \"Commit\", \"value\": \"${{ github.sha }}\", \"short\": true},
                  {\"title\": \"Autore\", \"value\": \"${{ github.actor }}\", \"short\": true},
                  {\"title\": \"Dettagli\", \"value\": \"<${{ github.server_url }}/${{ github.repository }}/actions/runs/${{ github.run_id }}|Vedi run>\", \"short\": false}
                ]
              }]
            }"
```

**Commit:**
```
feat(oc:8082): add smoke test, Slack notify and fix container name in prod-deploy.yml
```

---

## Step 8 — Review gate e commit

Eseguire `git diff --stat` su tutti i file modificati, chiedere approvazione esplicita prima di committare.

## Step 9 — PR verso `develop`

```bash
gh pr create \
  --title "feat(oc:8082): CI/CD GitHub Actions con deploy automatico e smoke test" \
  --base develop \
  --body "..."
```
