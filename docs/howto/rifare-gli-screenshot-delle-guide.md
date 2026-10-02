# Rifare gli screenshot delle guide utente

Le guide pubbliche stanno in `docs/guide/` e si pubblicano su GitHub Pages con il merge su
`main` (`.github/workflows/pages.yml`). Qui c'è come si rifanno gli screenshot quando l'app
cambia, nello stesso formato di quelli esistenti, così le immagini restano coerenti fra loro.
La prima volta è stato fatto per Vivi Lama in oc:8685.

## Il formato

- **Cattura a 393×800, poi ridotta a 295×600** (`deviceScaleFactor: 1`). Non catturare
  direttamente a 295×600: l'app si impagina diversamente e i testi non tornano con le altre
  immagini.
- **Stessi nomi di file** di quelli che si sostituiscono: l'HTML non cambia. I file nuovi
  prendono il nome dell'immagine che li precede nella sezione, con la lettera successiva
  (`05b-…` → `05c-…`), tutto minuscolo: macOS non distingue le maiuscole, GitHub Pages sì.
- **Ritagli** (es. `04b-pulsanti-scheda.png`, 190×336): si ritagliano dalla cattura a 393×800,
  non dall'immagine già ridotta.
- **Sempre dall'app pubblicata** (es. https://2.maphub.it), mai dal database locale, che può
  non avere le funzioni attive.

## Lo strumento

Playwright con il **Chrome di sistema** in headless, da uno script tenuto fuori dal repo. Ogni
esecuzione parte da un contesto nuovo, cioè senza cache, service worker o lingua salvata da
visite precedenti.

```js
import { chromium } from 'playwright';

const browser = await chromium.launch({
  executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
  headless: true,
});
const context = await browser.newContext({
  viewport: { width: 393, height: 800 },
  deviceScaleFactor: 1,
  isMobile: true,
  hasTouch: true,
  locale: 'it-IT', // 'en-US' per la guida inglese
  userAgent: 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36',
  // solo per lo screenshot della posizione:
  geolocation: { latitude: 44.3075, longitude: 10.7310, accuracy: 15 },
  permissions: ['geolocation'],
});
// guida inglese: forza la lingua prima del caricamento
// await context.addInitScript(() => localStorage.setItem('wm-lang', 'en'));
const page = await context.newPage();
await page.goto('https://2.maphub.it', { waitUntil: 'networkidle' });
await page.waitForTimeout(3000);
await page.screenshot({ path: 'raw/01-home.png' });
```

Il Chromium che Playwright scarica da sé non serve, e la versione installata può chiederne uno
diverso: con `executablePath` si evita il problema.

## La procedura

Nei comandi, `<guida>` è il percorso della guida sotto `docs/guide/`, per esempio
`vivi-lama/come-usare-la-app`. Ogni lingua è una guida separata con le sue immagini: la
versione inglese è `vivi-lama/how-to-use-the-app`, e gli screenshot vanno rifatti anche lì,
con l'app in inglese.

1. **Ricognizione**: aprire l'app e verificare, schermata per schermata, che i testi e gli
   esempi della guida esistano ancora (nomi di percorsi, raccolte, categorie). Le funzioni
   nuove vanno cercate dove stanno davvero, non dove ci si aspetta.
2. **Catture in una sola sessione**, con lo stesso percorso di esempio per tutte le schermate
   della scheda e del download (per Vivi Lama: «Anello Piane - Centocroci», `track=12`).
   - Lo scorrimento dei pannelli si fa con la rotella (`page.mouse.wheel`) sopra il pannello;
     `scrollToPoint` su `ion-content` non sposta il contenuto visibile.
   - Un elemento con lo stesso testo può esistere nascosto: usare
     `getByText(…).locator('visible=true')`.
   - Il cambio lingua va catturato per ultimo: la scelta resta salvata (`wm-lang`) e le catture
     successive uscirebbero nell'altra lingua.
3. **Riduzione e ritagli**:
   ```bash
   for f in raw/*.png; do sips -z 600 295 "$f" --out "docs/guide/<guida>/images/$(basename "$f")"; done
   sips -c 336 190 --cropOffset 50 203 raw/04-dettaglio-mappa.png --out docs/guide/<guida>/images/04b-pulsanti-scheda.png
   ```
4. **Guardare ogni immagine**, non solo misurarla: lingua giusta, nessuna schermata di
   caricamento o di errore.
5. **Controlli sull'HTML**, con un'anteprima locale
   (`cd docs/guide && python3 -m http.server 8765`):
   ```bash
   cd docs/guide/<guida>
   # ogni immagine citata risponde 200 (stesso nome esatto, maiuscole comprese)
   for f in $(grep -o 'images/[^"]*' index.html); do
     printf '%s ' "$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:8765/<guida>/$f")"; echo "$f"
   done | grep -v '^200'
   # nessuna immagine senza alt
   grep -o '<img[^>]*>' index.html | grep -v 'alt="[^"]\+'
   # nessuna immagine orfana
   for f in images/*.png; do grep -q "$f" index.html || echo "orfana $f"; done
   ```
   Tutti e tre i comandi non devono stampare nulla.

## Casi particolari

- **Posizione** (`13-posizione-attiva.png`): da computer non c'è; si simula con `geolocation` e
  `permissions` nel contesto, poi si tocca il pulsante del mirino sulla mappa.
- **Etichetta non ancora tradotta nell'app**: se il dev lo chiede, si sostituisce il solo testo
  nella pagina prima della cattura (`document.createTreeWalker` sui nodi di testo), e lo si
  annota nelle note del ticket: la schermata mostra qualcosa che l'app non ha ancora. In
  oc:8685 è successo per «Modalità di percorrenza» → «Travel mode» (traduzione in oc:8689).
