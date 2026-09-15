---
paths:
  - "database/migrations/**"
  - ".github/workflows/**"
  - "scripts/**"
  - "app/Nova/**"
  - "app/Providers/NovaServiceProvider.php"
---

# Trappole: il confine con wm-package

Il perché sta in [docs/knowledge/confini-con-wm-package.md](../../docs/knowledge/confini-con-wm-package.md),
[docs/knowledge/nova-ruoli-e-permessi.md](../../docs/knowledge/nova-ruoli-e-permessi.md) e
[docs/knowledge/migration-e-stub.md](../../docs/knowledge/migration-e-stub.md).

- **L'ordine di merge è vincolante: prima wm-package, poi il bump del submodule e la modifica di
  Maphub nello stesso commit.** Invertirlo non produce un errore comprensibile: se il codice qui
  chiama un metodo che nel package ancora non esiste — è successo con `hasUgcEnabled()` — **si
  rompe il rendering dell'intero menu Nova per ogni Editor**; se riguarda un test, si apre una
  finestra con copertura persa o duplicata.


- **Se aggiungi un override in `app/Nova/User.php`, ricordati degli altri consumer.** L'`hideFromIndex()`
  su `RoleBooleanGroup` e `PermissionBooleanGroup` è una scelta di questo prodotto, non del package:
  ogni shard che aggiorna wm-package deve ripeterlo, o si ritrova ruoli e permessi come colonne
  nell'index.

