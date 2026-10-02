> Ticket: oc:8685

# Notes — Aggiornamento guida utente Vivi Lama

## Divergenze dal piano, task per task

### Task 1: ricognizione e strumento di cattura

- **Cattura a 393×800 poi ridotta a 295×600**, non direttamente a 295×600. Gli screenshot
  originali erano catture a 393×800 ridotte del 75% (lo dimostra `13-posizione-attiva.png`,
  rimasta a 393×800, e la proporzione dei testi). Catturare direttamente a 295×600 avrebbe
  impaginato l'app in modo diverso da com'era nella prima guida.
- **Chrome di sistema** (`/Applications/Google Chrome.app`) in headless al posto del Chromium
  di Playwright: la versione di Playwright disponibile chiedeva un browser non installato.
- **Welcome**: non è una schermata separata, è la Home con il logo molto più grande. Quindi
  `01b-welcome.png` non è stato creato e la welcome è mostrata da `01-home.png`.
- **Cambio lingua**: oltre che in Impostazioni (voce «Lingua»), si trova anche sulla Mappa,
  nel pulsante con il globo accanto a «Filtri». La sezione 9 resta il posto principale e cita
  anche il globo.
- **Filtro percorsi**: sta nel pannello «Filtri» della Mappa, come previsto, ma filtra per
  raccolta (oggi «Via Vandelli» e «Percorsi Bike»), non per durata, distanza o difficoltà.
- **Novità non richiesta dal ticket**: la Home ha una card «Come usare l'app» che apre proprio
  questa guida. Citata nella sezione 3.

### Task 2: sostituire gli screenshot esistenti

- **Percorso di esempio unico**: tutte le schermate della scheda (04, 04b, 05, 05b, 05c, 05d) e
  del download (06, 06b, 07) usano «Anello Piane - Centocroci». Prima erano usati tre percorsi
  diversi («Alpe Sigola», «Monte Cantiere e Monte S. Andrea» e un terzo); un solo percorso rende
  la sequenza più facile da seguire.
- **`13-posizione-attiva.png`** rifatta anche lei, contro il vincolo iniziale: vedi Decisioni.
- **`07-miei-download.png`**: il pulsante «VAI AI MIEI DOWNLOAD» oggi riporta alla mappa, quindi
  la cattura è fatta da Profilo → Tracce scaricate, che apre la schermata «Downloads».

### Task 3: sezione 3 e sezione 5

- **Due screenshot per la modalità di percorrenza** invece di uno: `05c-modalita-percorrenza.png`
  (a piedi, 7 h 34 m) e `05d-modalita-bici.png` (in bici, 2 h 30 m). Il confronto fra le due
  schermate spiega la funzione meglio di qualsiasi frase.

### Task 8: guida in inglese

- Aggiunto dopo l'approvazione del piano, su richiesta del dev. Gli screenshot inglesi sono
  catturati come quelli italiani, con `wm-lang = en` impostato prima del caricamento e
  `locale: en-US`.
- **Testi dell'interfaccia non tradotti nell'app** anche in inglese: «Modalità di percorrenza»
  nella scheda percorso e «Tipo mappa», «Dati», «Percorsi» nel pannello del tipo mappa. Nella
  guida sono citati così come appaiono. I contenuti (nomi di percorsi, raccolte come «Percorsi
  Bike» nei filtri, POI) restano in italiano: il dev ha detto che va bene.
- **Etichetta «Travel mode» anticipata negli screenshot** (richiesta del dev): in
  `how-to-use-the-app/images/05c-modalita-percorrenza.png` e `05d-modalita-bici.png` il testo
  «Modalità di percorrenza» è stato sostituito nella pagina con «Travel mode» prima della
  cattura, perché la traduzione arriverà a breve nell'app con questo testo. Il resto delle due
  schermate è quello reale. Finché l'app non è aggiornata, chi la usa in inglese vede ancora
  «Modalità di percorrenza».
- Link agli store nella guida inglese senza il prefisso del paese
  (`https://apps.apple.com/app/vivi-lama/id6769355992`), così l'App Store apre la pagina del
  paese di chi legge.

### Task 7: commit e PR

- **PR su `develop`, non su `main`** (cambiato dal dev prima del commit): inizialmente la PR
  doveva andare direttamente su `main`, che è il branch da cui GitHub Pages pubblica. Il dev ha
  poi scelto il flusso abituale: il branch è stato spostato su `origin/develop` (`041d1fd`)
  prima del commit, la PR va su `develop` e le guide si pubblicano con il merge da `develop` a
  `main`. Verificato prima dello spostamento che `develop` e `main` avessero lo stesso
  contenuto (`git diff origin/develop origin/main` vuoto), quindi nessun conflitto.

## Bug trovati

Nessuno.

## Decisioni

- **Tag Orchestrator saltati**: il dev ha chiesto di ignorare sia i tag di ambiente sia quelli
  di contenuto.
- **`13-posizione-attiva.png` rifatta** (modifica richiesta dal dev dopo la prima anteprima):
  inizialmente lasciata invariata, poi catturata come le altre con una posizione simulata nei
  pressi di Lama Mocogno. Serve solo a mostrare il pallino blu e i tre pulsanti (freccia,
  bussola, X); il testo descrive la funzione della sola X, l'unica verificata.
- **Filtri combinati** (richiesta del dev dopo la prima anteprima): nuovo sottotitolo
  «Combinare i filtri» nella sezione 7, con l'esempio Via Vandelli + B&B e lo screenshot
  `08e-filtri-combinati.png`.
- **Guida riferita all'app mobile, non alla web app** (richiesta del dev dopo la prima
  anteprima): la sezione 1 diceva che non serviva installare nulla dagli store, cosa vera solo
  per la web app. Tolti tutti i riferimenti alla web app (2.maphub.it, QR code, «Aggiungi a
  Home», layout da computer, «browser») e aggiunti i link agli store: App Store
  `https://apps.apple.com/it/app/vivi-lama/id6769355992` (verificato con l'API di iTunes per
  il bundle `it.lamamocogno.vivilama`, prezzo 0) e Google Play
  `https://play.google.com/store/apps/details?id=it.lamamocogno.vivilama` (risponde 200).
  Gli screenshot restano catturati dalla web app 2.maphub.it, che ha la stessa interfaccia.
- **Colori e logo dell'app** (richiesta del dev dopo la prima anteprima): il verde della guida
  è ora il primary color dell'app (`#75980d`, letto da `--ion-color-primary` su 2.maphub.it),
  usato per lo sfondo dell'intestazione e le linee. Per titoli e testi piccoli colorati si usa
  una tonalità più scura dello stesso verde (`#4f660a`): il primary sullo sfondo chiaro ha un
  contrasto di 3,2:1, sotto il 4,5:1 richiesto per il testo normale. Nell'intestazione c'è il
  logo della welcome (`icona_trasparente.png` dell'app, ridotto a 240px in
  `images/logo-vivi-lama.png`), su un cerchio bianco perché le sue parti semitrasparenti si
  schiarivano sul verde.
- **Sezione 9 solo in italiano**: niente etichette inglesi nella guida italiana. La guida
  inglese è poi stata fatta come guida separata (Task 8).
- **Bypass di PHPStan** (2026-10-02T09:51:39Z, confermato esplicitamente dal dev, che se ne assume la
  responsabilità): PHPStan non riesce a scrivere la propria cache nel container
  (`build/phpstan/cache`, «Unable to create file … Container_760b584183.php»), quindi l'analisi
  non parte (`errors: 1, file_errors: 0`, due tentativi). Il diff contiene solo HTML, PNG e
  Markdown, nessun file PHP da analizzare.
- **Procedura per rifare gli screenshot** (`docs/howto/rifare-gli-screenshot-delle-guide.md`),
  scritta a fine lavoro: era fuori scope nell'overview iniziale, ma senza di essa il metodo
  (393×800 ridotto, Chrome di sistema, lingua forzata, posizione simulata, ritaglio di `04b`)
  andava riscoperto alla prossima modifica dell'app.

## Follow-up

- Nel DB locale `android_store_link` e `ios_store_link` di Vivi Lama sono vuoti: da valorizzare
  se servono altrove.
- Tradurre nell'app «Modalità di percorrenza» come **«Travel mode»** (la guida inglese lo
  usa già): aperto **oc:8689** (Bug, `wm-core`), e «Tipo mappa», «Dati», «Percorsi», che in inglese restano in italiano.
