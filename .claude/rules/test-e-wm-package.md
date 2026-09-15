---
paths:
  - "tests/**"
  - "phpunit.xml"
---

# Trappole: i test

- **Usa `DatabaseTransactions`, mai `RefreshDatabase`.** In `phpunit.xml` le righe `DB_CONNECTION`
  e `DB_DATABASE` sono commentate, quindi i test girano sulla connessione del `.env`, cioè sul
  database di sviluppo: `RefreshDatabase` lo svuoterebbe.

- **Non scrivere test che referenziano classi interne di wm-package.** Sono fragili all'ordine di
  bump del submodule: se punta a un commit senza quella classe, PHPStan fallisce in CI con «Class
  not found» e l'errore non ha niente a che vedere con il codice che l'ha causato. Un test su una
  classe del package va **nel package**. Nessuno degli stub Nova qui ha un test dedicato, ed è la
  convenzione.
