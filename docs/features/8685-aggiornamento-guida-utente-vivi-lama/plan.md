> Ticket: oc:8685

# Aggiornamento guida utente Vivi Lama — piano

> **Per chi esegue:** sotto-skill `superpowers:executing-plans`. Gli step usano le checkbox
> (`- [ ]`). **Nessun commit, `git add` o push durante l'esecuzione**: il commit si fa solo
> dopo il review-gate e l'approvazione del dev.

**Obiettivo:** riallineare la guida pubblica di Vivi Lama all'app di oggi, con screenshot nuovi
e le quattro novità spiegate nelle sezioni esistenti.

**Approccio:** gli screenshot si catturano da https://2.maphub.it con un browser senza storia
(contesto Playwright nuovo, viewport mobile), salvandoli con gli stessi nomi in `images/`. Il
testo si modifica in `index.html` sezione per sezione, e dopo ogni gruppo il dev controlla
l'anteprima locale.

**Strumenti:** HTML statico, Playwright (Chromium già in cache su questa macchina), `sips`,
`curl`, server di anteprima `python3 -m http.server`.

**Spec:** [overview.md](overview.md)

## Vincoli globali

- Repo: solo `maphub`, nessun submodule. File: `docs/guide/vivi-lama/come-usare-la-app/index.html`
  e `docs/guide/vivi-lama/come-usare-la-app/images/`.
- Fonte delle catture: **https://2.maphub.it**, mai il database locale.
- Formato: 295×600 PNG, `deviceScaleFactor: 1`. Eccezione: `04b-pulsanti-scheda.png` è un
  ritaglio dello screenshot della scheda, con la stessa inquadratura di oggi (190×336).
- `13-posizione-attiva.png` **non si tocca**.
- Nomi dei file nuovi: minuscolo, lettera successiva all'immagine che li precede nella sezione.
- App in italiano in tutte le catture; lo screenshot del cambio lingua è l'ultimo.
- Testo in italiano, solo etichette italiane, nessun nome di file, classe o configurazione
  interna. Numerazione, titoli `<h2>` e ancore delle 10 sezioni invariati.
- `loading="lazy"` su ogni `<img>` tranne `01-home.png`; `alt` descrittivo su ogni `<img>`.
- Lo script di cattura vive nella scratchpad della sessione, **non nel repo**.
- Anteprima: http://127.0.0.1:8765/vivi-lama/come-usare-la-app/ (server già attivo su
  `docs/guide/`).
- PR verso `main`, nessun back-merge su `develop`.

## Review focus

- Una cattura in inglese in mezzo alle altre (lingua del browser o `wm-lang` rimasto salvato):
  ogni PNG va guardato, non solo misurato.
- Un esempio del testo che non esiste più (la ricerca «Piane», la raccolta «Percorsi Bike», la
  categoria «Bar»): si verifica sull'app prima di catturare.
- Un nome di file nell'HTML che non corrisponde al file su disco (macOS non distingue le
  maiuscole, GitHub Pages sì): il controllo `curl` del Task 6 lo intercetta.
- Una funzione nuova che nell'app sta in un posto diverso da quello previsto (filtro percorsi
  fuori dalla Mappa, welcome che compare solo alla prima apertura): il testo segue l'app.
- La welcome più alta della finestra 295×600: si cattura la schermata così come appare
  all'apertura, senza ridurla.

## Nomi degli screenshot nuovi

| Novità | Sezione | File | Dopo |
|---|---|---|---|
| Welcome (se è una schermata separata dalla Home) | 3 | `01b-welcome.png` | `01-home.png` |
| Modalità di percorrenza | 5 | `05c-modalita-percorrenza.png` | `05b-profilo-altimetrico.png` |
| Filtro percorsi: pannello | 7 | `08c-filtro-percorsi.png` | `08b-filtri-attivi.png` |
| Filtro percorsi: risultato | 7 | `08d-filtro-percorsi-attivo.png` | `08c-filtro-percorsi.png` |
| Cambio lingua | 9 | `12b-lingua.png` | `12-impostazioni.png` |

Se la welcome è la Home stessa, ingrandita, `01b-welcome.png` non si crea e basta `01-home.png`.

---

### Task 1: ricognizione dell'app e strumento di cattura

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-1-ricognizione-e-strumento-di-cattura)

**File:**
- Crea (scratchpad, fuori dal repo): `capture.mjs`

- [ ] **Step 1:** aprire https://2.maphub.it in un contesto Playwright nuovo (`viewport
  {width: 295, height: 600}`, `deviceScaleFactor: 1`, `isMobile: true`, `hasTouch: true`,
  `locale: 'it-IT'`) e salvare una cattura di prova della prima schermata.
  Atteso: PNG 295×600 (`sips -g pixelWidth -g pixelHeight`), con l'app in italiano.
- [ ] **Step 2:** percorrere l'app e annotare, per ogni screenshot esistente e per ogni novità,
  la sequenza di click che porta alla schermata. Verificare in particolare:
  - welcome: schermata a sé o parte della Home; compare solo alla prima apertura?
  - travel mode: dove sta nella scheda del percorso, etichette, effetto sull'orario previsto;
  - filtro percorsi: dove si apre, quali filtri offre (durata, distanza, difficoltà…);
  - cambio lingua: dove si trova e con quali etichette;
  - esistono ancora la ricerca «Piane», la raccolta «Percorsi Bike», la categoria «Bar»?
- [ ] **Step 3:** scrivere `capture.mjs` con una funzione per screenshot
  (`async function shot(page, file)`) e la sequenza di navigazione dello Step 2; il cambio
  lingua per ultimo.
- [ ] **Step 4:** se la ricognizione contraddice la sezione prevista per una novità, annotarlo
  subito in `notes.md` (`## Divergenze dal piano, task per task`) e dirlo al dev prima del Task 2.

### Task 2: sostituire gli screenshot esistenti

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-2-sostituire-gli-screenshot-esistenti)

**File:**
- Modifica: `images/01-home.png` … `images/12-impostazioni.png` (17 file a schermo intero) e
  `images/04b-pulsanti-scheda.png` (ritaglio). Esclusa `13-posizione-attiva.png`.

- [ ] **Step 1:** eseguire `capture.mjs` per i 17 screenshot a schermo intero.
- [ ] **Step 2:** ritagliare `04b-pulsanti-scheda.png` dalla cattura della scheda percorso,
  con la stessa inquadratura dei tre pulsanti sul bordo destro.
- [ ] **Step 3:** verificare dimensioni:
  `for f in images/*.png; do sips -g pixelWidth -g pixelHeight "$f"; done`
  Atteso: 295×600 per tutti tranne `04b` (ritaglio) e `13` (393×800, invariata).
- [ ] **Step 4:** guardare ogni PNG: app in italiano, nessuna schermata di errore o caricamento.
- [ ] **Step 5:** chiedere al dev di controllare l'anteprima.

### Task 3: sezione 3 (welcome) e sezione 5 (modalità di percorrenza)

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-3-sezione-3-e-sezione-5)

**File:**
- Modifica: `index.html`, sezione 3 (righe ~191-208) e sezione 5 (righe ~229-281), FAQ sui tempi
  (righe ~409-411).
- Crea: `images/01b-welcome.png` (solo se la welcome è separata), `images/05c-modalita-percorrenza.png`

- [ ] **Step 1:** catturare gli screenshot nuovi.
- [ ] **Step 2:** sezione 3: descrivere la welcome come appare; aggiungere la figura nel blocco
  `.shots` esistente, con didascalia.
- [ ] **Step 3:** sezione 5: paragrafo sulla «Modalità di percorrenza» (a piedi / in bici e
  come cambia l'orario previsto, con le parole dell'app) e la figura `05c` nello stesso `.shots`
  delle immagini `04`/`05`/`05b`.
- [ ] **Step 4:** riscrivere la FAQ «Quanto sono affidabili i tempi di percorrenza indicati?»
  dicendo che il tempo dipende anche dalla modalità scelta.
- [ ] **Step 5:** dev controlla l'anteprima.

### Task 4: sezione 7 (filtro percorsi)

**File:**
- Modifica: `index.html`, sezione 7 (righe ~299-333).
- Crea: `images/08c-filtro-percorsi.png`, `images/08d-filtro-percorsi-attivo.png`

- [ ] **Step 1:** catturare gli screenshot.
- [ ] **Step 2:** aggiungere `<h3>Filtrare i percorsi</h3>` dopo il blocco «Filtrare i punti di
  interesse» e prima di «Cambiare tipo di mappa»: quali filtri ci sono, come si attivano e come
  si tolgono, poi le due figure in un `.shots`.
- [ ] **Step 3:** se il Task 1 ha trovato il filtro altrove, il sottotitolo va in quella sezione
  (divergenza già annotata).
- [ ] **Step 4:** dev controlla l'anteprima.

### Task 5: sezione 9 (cambio lingua)

**File:**
- Modifica: `index.html`, sezione 9 (righe ~371-397).
- Crea: `images/12b-lingua.png`

- [ ] **Step 1:** catturare `12b-lingua.png` (ultima cattura della sessione).
- [ ] **Step 2:** paragrafo: dove si cambia lingua, con le etichette italiane; aggiungere che
  l'app si apre nella lingua del telefono se è fra quelle disponibili.
- [ ] **Step 3:** figura `12b` nello stesso `.shots` di `11`/`12`.
- [ ] **Step 4:** dev controlla l'anteprima.

### Task 6: revisione d'insieme e controlli

**File:**
- Modifica: `index.html` (attributi `<img>`, esempi, FAQ)

- [ ] **Step 1:** aggiungere `loading="lazy"` a ogni `<img>` tranne `01-home.png`.
  Verifica: `grep -c '<img' index.html` meno 1 = `grep -c 'loading="lazy"' index.html`.
- [ ] **Step 2:** `alt` presente e descrittivo su ogni `<img>`:
  `grep -o '<img[^>]*>' index.html | grep -v 'alt="[^"]\+'` → nessun output.
- [ ] **Step 3:** correggere esempi e didascalie del corpo che non corrispondono più all'app
  (esito del Task 1, Step 2); rileggere tutte le FAQ contro l'app.
- [ ] **Step 4:** ogni immagine citata esiste con lo stesso nome esatto:
  ```bash
  for f in $(grep -o 'images/[^"]*' index.html); do
    printf '%s ' "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8765/vivi-lama/come-usare-la-app/$f)"; echo "$f"
  done | grep -v '^200'
  ```
  Atteso: nessun output. Poi `ls images/ | grep '[A-Z]'` → nessun output.
- [ ] **Step 5:** nessuna immagine orfana: ogni file in `images/` è citato in `index.html`.
- [ ] **Step 6:** titoli `<h2>` invariati: `grep -o '<h2>[^<]*' index.html` uguale a prima.
- [ ] **Step 7:** dev controlla l'anteprima completa, anche in vista telefono (DevTools ⌘⇧M).

### Task 8: guida in inglese (aggiunto dopo l'approvazione del piano)

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-8-guida-in-inglese)

**File:**
- Crea: `docs/guide/vivi-lama/how-to-use-the-app/index.html`, `docs/guide/vivi-lama/how-to-use-the-app/images/*.png`
- Modifica: `docs/guide/vivi-lama/come-usare-la-app/index.html` (link alla versione inglese)

- [ ] **Step 1:** catturare gli stessi 25 screenshot con l'app in inglese (`wm-lang = en` prima del
  caricamento), stessi nomi e formato; copiare `logo-vivi-lama.png`.
- [ ] **Step 2:** tradurre `index.html` con `lang="en"`, stesse ancore, etichette copiate dalle
  schermate inglesi.
- [ ] **Step 3:** link reciproco in cima alle due guide.
- [ ] **Step 4:** stessi controlli del Task 6 sulla guida inglese
  (http://127.0.0.1:8765/vivi-lama/how-to-use-the-app/).
- [ ] **Step 5:** dev controlla l'anteprima.

### Task 7: commit e PR (solo dopo review-gate e approvazione del dev)

- [ ] **Step 1:** commit su `feature/oc-8685-aggiornamento-guida-utente-vivi-lama`, per esempio:
  ```
  docs(oc:8685): aggiorna la guida utente Vivi Lama
  ```
  con overview, plan, notes, `index.html` e `images/`.
- [ ] **Step 2:** PR verso **`main`** (è il merge su `main` che pubblica la guida su GitHub Pages).
- [ ] **Step 3:** dopo il merge, controllare
  https://webmappsrl.github.io/maphub/guide/vivi-lama/come-usare-la-app/ (immagini caricate,
  testo nuovo).
