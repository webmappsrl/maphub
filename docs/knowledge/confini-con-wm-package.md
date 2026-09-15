# Cosa vive nel package e cosa qui

## Come funziona oggi

Le risorse Nova di questo progetto **estendono** quelle di wm-package e spesso sono stub vuoti:

```php
class App extends Wm\WmPackage\Nova\App {}
```

L'import dei POI da OSM è tutto nel package — Nova Action, servizi, DTO, comando CLI, controller,
route, view, config e test — e `app/Nova/EcPoi.php` è uno stub che non ha più bisogno di
sovrascrivere `actions()`.

Nessuno degli stub Nova ha un test dedicato in questo repo.

## Perché così

- **Un test che referenzia classi interne del package è fragile per costruzione** (oc:8348): è già
  successo — nel commit `c3d7876` il submodule puntava a un commit senza
  `Wm\WmPackage\Nova\Actions\ImportEcPoiFromOsm`, e PHPStan in CI falliva con «Class not found». Il
  test è stato eliminato da qui senza sostituto, coerentemente con la convenzione, e ricreato nel
  package puntando alla classe del package invece che allo stub locale.
- **Le env var hanno preso il prefisso del package** (oc:8239): `OSM_IMPORT_*` è diventato
  `WM_OSM_IMPORT_*`. Se in produzione erano state tarate diversamente dai default — 350 ms e 500 —
  vanno riverificate al deploy.
- **Il comando CLI si chiama `wm-package:import-ec-pois-from-osm`** (oc:8239), non più
  `maphub:`.

## Debito noto

- **Le traduzioni fr, es e de non sono state portate nel package** (oc:8239): restano solo en e it.
  Regressione accettata esplicitamente, non un bug.
- **Il pattern dei test accoppiati alle classi interne non è stato verificato altrove** (oc:8348):
  gli altri consumer di wm-package — camminiditalia, osm2cai2 — potrebbero averlo. Nessun audit
  trasversale è stato fatto.
