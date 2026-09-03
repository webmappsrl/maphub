> Ticket: oc:8162

# Notes — Fix permessi e visibilità Nova per ruolo Editor (Maphub)

## Deviazioni dal piano

- **Ordine di merge**: il piano prevedeva `git pull origin main` su wm-package come primo step del Task 10 (scenario di deploy cross-repo). Non applicabile in questo ciclo — sviluppo locale su feature branch in entrambi i repo, nessun push/merge remoto avvenuto prima dell'esecuzione. Step saltato, la dipendenza (`User::hasUgcEnabled()` del Task 1) era già disponibile nel working tree locale di wm-package.
- **Granularità dei commit**: vedi `wm-package/docs/features/8162-.../notes.md` — stessa motivazione (impossibile separare in commit 1:1 per task dato che i commit erano vietati durante l'esecuzione dei singoli task).

## Bug trovati durante l'implementazione

Vedi il dettaglio completo in `wm-package/docs/features/8162-.../notes.md`. Riassunto lato Maphub:

- **`app/Providers/NovaServiceProvider.php`**: il `canSee()` della sezione UGC era stato alterato dopo l'approvazione del Task 10 (probabile mutazione accidentale da parte di un subagent di review con accesso di scrittura non vincolato). Rilevato dalla review formale (`wm-review-ticket`), ripristinato e riverificato (6/6 test).

## Decisioni

- Nessuna decisione specifica lato Maphub oltre a quanto già documentato in `overview.md` — la logica di business vive interamente in wm-package (`hasUgcEnabled()`), Maphub si limita a consumarla nel `canSee()` del menu.

## Follow-up

- Vedi `wm-package/docs/features/8162-.../notes.md` per il rischio operativo aperto (Validator senza App possedute) e la nota sulla configurazione CI (`phpunit.xml` non include `tests/Feature` in Maphub).
