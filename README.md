# Paide Linnameeskond — Ametlik Kodulehe Prototüüp (PSG.fr Disainikeel)

Kõrge jõudlusega, mobiilisõbralik ja kaasaegne veebiprototüüp **Paide Linnameeskonnale**, mis on inspireeritud **psg.fr** toimetuslikust (editorial) esteetikast, jõulisest tüpograafiast ja voolujoonelisest kasutajakogemusest.

Ühendatud otse **Eesti Jalgpalli Liidu (jalgpall.ee)** reaalajas andmevooga (ametlik koosseis, liigatabelid, punktid, mängukalender ja klubide vapid).

---

## 🎨 Disainisüsteem & Bränditunnused

| Element | Väärtus / Spetsifikatsioon | Kasutus |
| :--- | :--- | :--- |
| **Põhivärv (Deep Navy)** | `#0b203e` (Tailwind: `navy-900` / `#0b203e`) | Päis, pealkirjad, taustakontrast, jalus |
| **Aktsent (Crimson Red)** | `#be2931` (Tailwind: `crimson-600` / `#be2931`) | CTA nupud ("Piletid", "Osta"), aktiivsed sildid, rõhutused |
| **Puhas Valge & Hele foon** | `#ffffff` / `#f8fafc` | Avar, hingav taust ilma "porise" tumeduseta |
| **Juusjooned (Hairline)** | `#e2e8f0` (`rgba(226, 232, 240, 0.85)`) | Subtiilsed piirjooned ja jaotused |
| **Pealkirjade kirjatüüp (Headings)** | `Marcellus` (Google Fonts) | Elegantne, väärikas ja toimetuslik antiikva pealkirjadele ja rubriikidele |
| **Põhiteksti & UI kirjatüüp (Body)** | `Space Grotesk` (300, 400, 500, 600, 700) | Kaasaegne, selge ja kõrgtehnoloogiline grotesk tekstile, tabelitele ja nuppudele |

---

## ⚡ Reaalajas Andmesünkronisatsioon (jalgpall.ee / EJL)

Veebileht on integreeritud Eesti Jalgpalli Liidu ametlike allikatega:
- **Esindusmeeskonna leht**: `https://jalgpall.ee/voistlused/52/team/5`
- **Premium Liiga tabel**: `https://jalgpall.ee/voistlused/52/premium-liiga`
- **Naiste Meistriliiga tabel**: `https://jalgpall.ee/voistlused/naised/63/naiste-meistriliiga`

### 1. Kliendipoolne reaalajas moodul (`assets/js/jalgpall-api.js`)
- **Automaatne laadimine**: Laeb andmed `data/jalgpall-data.json` failist ja teostab reaalajas päringuid.
- **Interaktiivne "Värskenda" nupp**: Liigatabelite päises asuv nupp võimaldab kasutajal andmeid reaalajas uuendada.
- **Täieliku koosseisu modal (`data-full-squad-trigger`)**: Avab kõigi 21 ametliku litsentseeritud mängija nimekirja koos särginumbrite, sünniaegade, positsioonide ja kodakondsustega.

### 2. Automaatne andmekorje skript (`scripts/sync_jalgpall.py`)
Taustaskript, mis parsib EJL lehed ja uuendab JSON struktuuri:
```bash
python3 scripts/sync_jalgpall.py
```
Väljund salvestatakse faili `data/jalgpall-data.json`, mis sisaldab:
- Klubi ametlikku infot (peatreener Tarmo Kink, esindaja Jaanus Pruuli, spordidirektor Gert Kams).
- Kogu 21 mängijaga esindusmeeskonna nimekirja.
- Liiga ja karika parimate väravaküttide edetabelit (Henri Anier 10 väravat liigas, Modou Sohna 6 väravat).
- Järgmise ja eelmiste mängude tulemusi (FCI Levadia vs Paide Linnameeskond 11.10.2026 kell 14:30 A. Le Coq Arenal).
- Mõlema liiga täispikki tabeleid (Premium Liiga 10 meeskonda ja Naiste Meistriliiga 6 naiskonda).

### 3. Ametlikud klubilogod (`assets/img/jalgpall_logos/`)
Kõik klubide logod on alla laaditud otse EJL serverist kõrge resolutsiooniga (600x600 PNG):
- `paide.png` — Paide Linnameeskond
- `levadia.png` — Tallinna FCI Levadia
- `flora.png` & `flora_women.png` — Tallinna FC Flora
- `kalju.png` — Nõmme Kalju FC
- `tammeka.png` — Tartu JK Tammeka
- `harju.png` — Harju JK Laagri
- `vaprus.png` — Pärnu JK Vaprus
- `kuressaare.png` — FC Kuressaare
- `nomme_united.png` — FC Nõmme United
- `trans.png` — JK Narva Trans
- `viimsi.png` — Viimsi JK
- `saku.png` — Saku Sporting
- `elva.png` — FC Elva

---

## 🚀 Lehe Komponendid & Arhitektuur

1. **`paide/top-ticker` (Ülemine teavitusriba)**:
   - Järgmise mängu reaalajas kiirinfo ja otsetee piletite broneerimiseks.
2. **`paide/header-navigation` (Kleepuv päis & navigatsioon)**:
   - Klubi ametlik vapp (`assets/img/jalgpall_logos/paide.png`).
   - Peamenüü: *Esindus*, *Naiskond*, *U21*, *Akadeemia*, *Uudised*, *Paide TV*, *Klubi*.
   - Mängukeskuse kiirnupp, keelevalik (`ET` / `EN`), fännipoe link ning karmiinpunane **"Piletid"** CTA.
3. **`paide/mobile-drawer` (Mobiilne küljemenüü)**:
   - Juurdepääsetav slide-over sahtel sujuvate animatsioonide, taustabluri ja ESC-klahvi toega.
4. **`paide/hero-feature` (Toimetuslik PSG-stiilis kangelasplokk)**:
   - 2/3 laiusega fookuskaart dramaatilise fotograafia, gradientfiltri ja suurte pealkirjadega.
   - 1/3 laiusega interaktiivne valikuriba teemade vahetamiseks.
5. **`paide/matchday-strip` (Mängupäeva ja tulemuste keskus)**:
   - Järgmine kohtumine: **Tallinna FCI Levadia vs Paide Linnameeskond** (11.10.2026 kell 14:30, A. Le Coq Arena).
   - Eelmine tulemus: **FC Nõmme United 1 - 3 Paide Linnameeskond** (19.09.2026).
6. **`paide/standings-hub` (Topelttabeli komponent reaalajas EJL toega)**:
   - Kaasaegne vahekaartide süsteem: **Premium Liiga (Mehed)** vs **Naiste Meistriliiga (Naised)**.
   - Reaalajas andmed, esimesed 5 rida + *"Vaata täispikka tabelit"* laiendusnupp.
   - UEFA Konverentsiliiga edenemisriba ja esikütt **Henri Anieri** väravastatistika (10 liigaväravat).
7. **`paide/news-media-grid` (Uudiste ja meedia võrgustik)**:
   - 1 suur esiletõstetud juhtlugu + 3 tundlikku kõrvallugu kategooriafiltritega.
8. **`paide/video-hub` (Paide TV & Ametlik Videokanal @PaideLinnameeskondTV)**:
   - Sügava öösinise taustaga moodul, mis kuvab **klubi tegelikke YouTube videoid** (`@PaideLinnameeskondTV`):
     - Rapid Wieni euromängu tipphetked
     - IIZI TALK Podcast episoodid
     - Dokumentaalsari "Jalgpall südames" (Viktor Mets)
     - Klubi ja taristu ülevaated
9. **`paide/squad-showcase` (Mängijate kaardid & 21-mängijaga modal)**:
   - Henri Anier (#09, Ründaja - 10 väravat)
   - Henrik Ojamaa (#08, Poolkaitsja - Eesti koondislane)
   - Ebrima Jarju (#99, Väravavaht - Gambia esinumber)
   - Modou Sohna (#11, Poolkaitsja - 6 väravat)
   - Nupp *"Kogu koosseis (21)"*, mis avab täieliku modali.
10. **`paide/partners-strip` (Toetajate galerii)**:
    - Exmet, Verston, Nike, Ramirent, Betsafe, IIZI, Paide Linn, Järva Teataja.
11. **`paide/footer` (Mitmeveeruline minimalistlik jalus)**:
    - Klubi kontaktid, peatreener Tarmo Kink, spordidirektor Gert Kams, esindaja Jaanus Pruuli, Paide linnastaadion, noorteakadeemia lingid ja kõik 6 ametlikku sotsiaalmeediakanalit.

---

## 💻 Kuidas käivitada

Prototüüp ei vaja ehitussamme:

1. **Lihtne avamine brauseris**:
   Ava fail `index.html` otse oma veebibrauseris.
   
2. **Kohaliku veebiserveriga (soovitatav live JSON andmetele)**:
   Käivita terminalis:
   ```bash
   python3 -m http.server 8080
   ```
   Seejärel ava brauseris: `http://localhost:8080`

---

## 📱 Ametlikud Sotsiaalmeediakanalid

| Kanal / Meeskond | Kasutajanimi | Otselink |
| :--- | :--- | :--- |
| **YouTube (Paide TV)** | `@PaideLinnameeskondTV` | [youtube.com/@PaideLinnameeskondTV](https://www.youtube.com/@PaideLinnameeskondTV) |
| **Instagram (Esindusmeeskond)** | `@paidelinnameeskond` | [instagram.com/paidelinnameeskond](https://www.instagram.com/paidelinnameeskond/) |
| **Instagram (Naiskond)** | `@paidelinnanaiskond` | [instagram.com/paidelinnanaiskond](https://www.instagram.com/paidelinnanaiskond/) |
| **Instagram (U21 / Akadeemia)** | `@plm_nextgen` | [instagram.com/plm_nextgen](https://www.instagram.com/plm_nextgen/) |
| **Facebook** | `PaideLinnameeskond` | [facebook.com/PaideLinnameeskond](https://www.facebook.com/PaideLinnameeskond) |
| **TikTok** | `@paidelinnameeskond` | [tiktok.com/@paidelinnameeskond](https://www.tiktok.com/@paidelinnameeskond) |
