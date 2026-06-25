> Ticket: oc:8082

# Notes — CI/CD Maphub – Workflow GitHub Actions con deploy automatico e smoke test

## Deviazioni dal piano

- `deploy_dev.sh` nel piano usava `optimize:clear` + `config:clear` separati; allineato a `deploy_prod.sh` con solo `optimize` (che include l'ottimizzazione della config cache).
- Il loop Horizon è stato estratto in `scripts/horizon_terminate_wait.sh` (cleanup emerso dalla code review) invece di essere duplicato nei due script come da piano iniziale.
- Il job `notify` è stato estratto in un workflow riusabile `notify-slack.yml` invece di essere duplicato (cleanup emerso dalla code review).
- URL smoke test parametrizzate come `vars.URLDEV` / `vars.URLPROD` invece di hardcoded (cleanup emerso dalla code review).

## Bug trovati

- `CheckCacheHealth` scriveva ma non leggeva il valore — `Cache::put` silenzioso su driver degradato. Aggiunto `Cache::get` con check esplicito.
- Loop Horizon non usciva con errore dopo 60s — `php artisan up` veniva chiamato comunque. Aggiunto `exit 1`.
- Grep loop includeva `stopped` che Horizon non emette mai. Rimosso.
- `config:clear` duplicato in `run-tests.yml` (riga 51 preesistente + nuova riga 57). Rimossa la nuova.
- Smoke test potenzialmente troppo vicino al deploy: aggiunto `sleep 15` prima dei curl.

## Decisioni

- **Container name hardcoded** (`php-maphub`, `php-maphubdev`): scelta intenzionale, allineata al pattern camminiditalia. Non usare `${APP_NAME}` che dipende dalla variabile d'ambiente del server.
- **`horizon:terminate` cross-container**: il comando usa Redis come canale (non POSIX signals), quindi funziona correttamente anche se Horizon gira in un container separato (`horizon-maphub`). Da verificare sul server con `docker ps`.
- **Rollback manuale**: in caso di deploy rotto, la procedura è: SSH sul server → `git checkout <tag-precedente>` in `/var/www/html/maphub` → rieseguire `scripts/deploy_prod.sh` manualmente via `docker exec php-maphub bash scripts/deploy_prod.sh`.

## Follow-up

- Verificare il nome esatto del container Horizon sul server prod (`docker ps --filter name=horizon`) prima del merge su `main`.
- Aggiungere su GitHub i secret/variabili: `HOSTDEV`, `USERNAMEDEV`, `PORTDEV`, `SSHKEYDEV`, `SLACK_WEBHOOK_URL`, `URLDEV`, `URLPROD`.
- Valutare in futuro: rollback automatico post-smoke test quando il sistema di deploy supporta release atomiche.
