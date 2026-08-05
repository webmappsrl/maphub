> Ticket: oc:8158

# Notes — Import GeoHub: estensione UGC (maphub)

## Deviazioni dal piano

Nessuna. Pubblicazione migration, stub Nova e registrazione menu eseguiti come da piano.

## Bug trovati

Nessuno lato maphub — vedi `wm-package/docs/features/8158-.../notes.md` per i bug trovati e corretti lato package (`AppClassificationService`).

## Decisioni

Nessuna decisione specifica a questo repo — vedi overview/notes in wm-package per il dettaglio completo della feature.

## Follow-up

- Verifica e2e reale (`wm:import-from-geohub app 49 --dependencies=...`) da eseguire quando l'isolamento di rete Docker tra i progetti maphub/geohub sarà risolto — vedi `wm-package/docs/features/8158-.../notes.md` per il dettaglio del blocco e il comando pronto.
