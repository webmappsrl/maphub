> Ticket: oc:8685

# Aggiornamento guida utente Vivi Lama

## Cosa cambia

La guida pubblica «Come usare la app» di Vivi Lama
(`docs/guide/vivi-lama/come-usare-la-app/`, pubblicata su
https://webmappsrl.github.io/maphub/guide/vivi-lama/come-usare-la-app/) torna a corrispondere
all'app di oggi:

- tutti gli screenshot vengono rifatti sull'app pubblicata **2.maphub.it**;
- le quattro novità vengono spiegate dentro le sezioni che già esistono, senza aggiungerne né
  rinumerarle:
  - **welcome più grande** → sezione 3 (La Home: ricerca e raccolte);
  - **travel mode** («Modalità di percorrenza» a piedi / in bici, che ricalcola l'orario
    previsto) → sezione 5 (La scheda di un percorso);
  - **filtro percorsi** → sezione 7 (La mappa: filtri e tipo di mappa), accanto a «Filtrare i
    punti di interesse»;
  - **cambio lingua** (italiano / inglese) → sezione 9 (Profilo e impostazioni);
- indice e FAQ vengono riallineati alle modifiche.

La guida italiana resta in italiano; la versione inglese è una guida separata (vedi Requisiti).

## Perché

Il cliente segnala che la guida non corrisponde più all'app: l'aspetto è cambiato, quindi gli
screenshot sono vecchi, e dalla configurazione sono state attivate funzioni nuove che la guida
non spiega. Una guida che mostra schermate diverse da quelle che l'utente vede sul telefono
genera confusione e richieste di assistenza.

## Requisiti

- [x] Gli screenshot sono catturati da 2.maphub.it (l'app in produzione, dove il dev conferma
      che tutte le novità sono attive), in emulazione mobile nel browser, come per la prima
      versione (oc:8557). Non dal database locale, dove Vivi Lama non ha le novità attive.
- [x] Ogni screenshot esistente viene sostituito mantenendo **lo stesso nome di file** e lo
      stesso formato: 295×600 per le schermate intere, ritaglio per `04b-pulsanti-scheda.png`
      (oggi 190×336).
- [x] `13-posizione-attiva.png` rifatta come le altre, con una posizione simulata (inizialmente
      lasciata invariata; rifatta su richiesta del dev dopo la prima anteprima, vedi notes.md).
- [x] Gli screenshot nuovi prendono il nome dall'immagine che li precede nella stessa sezione
      con una lettera successiva (es. dopo `05b-…` viene `05c-…`), in minuscolo, formato 295×600.
      I nomi esatti si fissano nel piano.
- [x] Gli screenshot si catturano da un profilo browser pulito (nessuna cache o service worker
      di visite precedenti a 2.maphub.it), con l'app in italiano; lo screenshot del cambio
      lingua è l'ultimo.
- [x] Sezione 3 descrive la welcome come appare oggi.
- [x] Sezione 5 spiega la «Modalità di percorrenza»: cosa cambia scegliendo a piedi o in bici.
      La FAQ «Quanto sono affidabili i tempi di percorrenza indicati?» viene riscritta di
      conseguenza (il tempo dipende dalla modalità scelta).
- [x] Sezione 7 ha un sottotitolo sul filtro dei percorsi, con i filtri effettivamente
      presenti nell'app pubblicata. Se nell'app il filtro non sta nella Mappa, va spiegato nella
      sezione dove si trova davvero.
- [x] Sezione 9 spiega come passare dall'italiano all'inglese, con le etichette in italiano.
- [x] Numerazione e ancore delle 10 sezioni restano invariate. L'indice elenca solo le
      sezioni (`<h2>`), quindi non cambia.
- [x] Testo, didascalie ed esempi del corpo (es. la ricerca «Piane», la raccolta «Percorsi
      Bike», la categoria «Bar») vengono ricontrollati sull'app pubblicata e corretti se non
      corrispondono più.
- [x] Le FAQ vengono riviste: nessuna risposta contraddice l'app di oggi.
- [x] Ogni testo descrive ciò che l'utente vede, senza nomi di file, classi o configurazioni
      interne (la guida è pubblica).
- [x] [UX] Ogni screenshot ha un `alt` che descrive cosa mostra.
- [x] [UX] Gli screenshot non superano la risoluzione attuale (mostrati a 220px): niente
      catture a piena risoluzione del telefono.
- [x] [UX] Gli screenshot hanno `loading="lazy"` (oggi nessuno ce l'ha), tranne il primo
      (`01-home.png`), che sta nella parte visibile all'apertura della pagina.
- [x] [UX] La welcome più grande sta nello stesso riquadro `figure` delle altre immagini. Se
      è una schermata che compare solo alla prima apertura, si cattura da un profilo pulito.
- [x] [UX] I paragrafi nuovi seguono lo schema delle sezioni esistenti: testo breve, poi
      screenshot affiancati in `.shots` con didascalia.
- [x] Una procedura in `docs/howto/` spiega come rifare gli screenshot delle guide (aggiunta a
      fine lavoro), con il rimando nel `CLAUDE.md`.
- [x] Ogni modifica si vede nell'anteprima locale
      http://127.0.0.1:8765/vivi-lama/come-usare-la-app/ (server statico su `docs/guide/`),
      che il dev controlla prima del commit.
- [x] **Guida in inglese, separata** (aggiunta su richiesta del dev dopo la prima anteprima):
      `docs/guide/vivi-lama/how-to-use-the-app/` con la sua cartella `images/`. Stesse 10 sezioni,
      stessa struttura e stesse ancore della guida italiana; screenshot rifatti con l'app in
      inglese, stessi nomi e formato; nel testo le etichette che l'app mostra in inglese. I
      contenuti non tradotti dall'app (nomi di percorsi, raccolte, POI) restano come sono.
- [x] Le due guide si collegano a vicenda con un link in cima («English version» / «Versione
      italiana»).
- [ ] Il branch parte da `develop` e la PR va verso `develop`; le guide si pubblicano con il
      merge successivo da `develop` a `main` (`.github/workflows/pages.yml` parte solo sui push
      su `main` che toccano `docs/guide/**`).

## Rischi

- [UX] **Screenshot incoerenti fra loro** (lingua, mappa di base, dati diversi da
  un'immagine all'altra): vanno catturati tutti nella stessa sessione, con la stessa finestra e
  l'app in italiano.
- [UX] **Funzioni legate alla configurazione**: travel mode, lingue e filtri dipendono da come
  è configurata l'app. Se in futuro la configurazione di 2.maphub.it cambia, la guida torna
  disallineata. La guida descrive l'app Vivi Lama così com'è oggi, senza promettere funzioni
  che dipendono da impostazioni.
- **Comportamento reale diverso dal codice locale**: il travel mode è stato letto in `wm-core`
  locale (giugno 2026); il testo si scrive su ciò che si vede su 2.maphub.it, non sul codice.
- **Pubblicazione al merge su `main`**: il merge da `develop` a `main` pubblica subito le
  pagine che legge il cliente. Il dev rivede ogni screenshot e il testo prima del merge.

## Out of scope

- Modifiche all'app o alla sua configurazione.

## Moduli toccati

Tutto nel repo principale `maphub`, nessun submodule:

- `docs/guide/vivi-lama/come-usare-la-app/index.html`
- `docs/guide/vivi-lama/come-usare-la-app/images/*.png` (sostituiti + nuovi)
- `docs/guide/vivi-lama/how-to-use-the-app/index.html` e `images/*.png` (nuovi)
- `docs/howto/rifare-gli-screenshot-delle-guide.md` (nuovo) e `CLAUDE.md` (rimando e regola sulla
  pubblicazione di `docs/guide/`)
- `docs/features/8685-aggiornamento-guida-utente-vivi-lama/` (overview, plan, notes)
