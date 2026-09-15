# Avviare il progetto in locale

I nomi dei container e la convenzione che li governa stanno nel `CLAUDE.md`, fra le convenzioni:
qui c'è come si mette in piedi l'ambiente.

## Setup

Usare lo script `scripts/install.sh` per l'installazione guidata completa, oppure manualmente:

```bash
# 1. Configurare .env (copiare da .env-example)
cp .env-example .env
# Modificare: APP_NAME, DOCKER_PHP_PORT, DOCKER_PROJECT_DIR_NAME

# 2. Avviare Docker
bash docker/init-docker.sh

# 3. Installare dipendenze e configurare Laravel
docker exec -it php-${APP_NAME} composer install
docker exec -it php-${APP_NAME} php artisan key:generate
docker exec -it php-${APP_NAME} php artisan optimize
# Bootstrap iniziale migration wm-package (solo prima installazione; vedi install.sh)
docker exec -it php-${APP_NAME} php artisan vendor:publish --tag=wm-package-migrations
docker exec -it php-${APP_NAME} php artisan migrate

# 4. Creare ruoli base
docker exec -it php-${APP_NAME} php artisan tinker --execute="
foreach (['Administrator', 'Editor', 'Validator', 'Guest'] as \$name) {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => \$name, 'guard_name' => 'web']);
}
"

# 5. Creare utente Administrator
docker exec -it php-${APP_NAME} php artisan nova:user
```

## I tre ambienti Docker

Il progetto ha tre file compose con scopi distinti:

| File | Scopo |
|------|-------|
| `compose.yml` | Base condivisa (prod). Non si usa direttamente. |
| `develop.compose.yml` | Sviluppo locale con nginx/proxy. Aggiunge minio, mailpit. |
| `local.compose.yml` | Sviluppo locale standalone con `php artisan serve`. Aggiunge scout-init, kibana, laravel server. |

```bash
# Produzione
docker compose up -d

# Sviluppo (con nginx)
docker compose -f develop.compose.yml up -d

# Sviluppo standalone (senza nginx)
docker compose -f local.compose.yml up -d
```
