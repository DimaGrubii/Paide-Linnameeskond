#!/usr/bin/env python3
"""
Builds the full Paide Linnameeskond News Archive (2016–2026):
1. Moves and updates existing 2026 root `artikkel-*.html` pages into `uudised/2026/`.
2. Restores all historical news articles (2016–2026) from `/Users/dumitrugrubii/Downloads/paidelinnameeskond-content/`
   into `uudised/<YYYY>/artikkel-<slug>.html` with original Estonian publication dates and chronology.
3. Generates `assets/js/news-archive-data.js` for client-side year filtering, category filtering,
   full-text live search, and pagination on `uudised.html`.
4. Exports `data/uploads-image-list.txt` listing all `wp-content/uploads/...` image files used.
"""

import glob
import html
import json
import os
import re
import shutil
import unicodedata

WORKSPACE = "/Users/dumitrugrubii/Documents/Paide Linnameeskond    Website"
BACKUP_DIR = "/Users/dumitrugrubii/Downloads/paidelinnameeskond-content"
RESOLVED_MAP_FILE = os.path.join(WORKSPACE, "data", "uploads-resolved-map.json")
RESOLVED_MAP = (
    json.load(open(RESOLVED_MAP_FILE, encoding="utf-8"))
    if os.path.exists(RESOLVED_MAP_FILE)
    else {}
)


def resolve_upload_rel(raw_rel):
    nfc = unicodedata.normalize("NFC", raw_rel)
    return RESOLVED_MAP.get(nfc, nfc)

MONTHS_ET_LOWER = {
    1: "jaanuar", 2: "veebruar", 3: "märts", 4: "aprill",
    5: "mai", 6: "juuni", 7: "juuli", 8: "august",
    9: "september", 10: "oktoober", 11: "november", 12: "detsember"
}
MONTHS_ET_UPPER = {k: v.upper() for k, v in MONTHS_ET_LOWER.items()}
MONTHS_ET_PARSE = {v: k for k, v in MONTHS_ET_LOWER.items()}


def parse_estonian_date(date_str):
    """Parses '13. NOVEMBER 2026' or '19. jaanuar 2026' into 'YYYY-MM-DD'."""
    m = re.search(r"(\d{1,2})\.\s*([a-zäöüõA-ZÄÖÜÕ]+)\s*(20\d\d)", date_str.strip())
    if not m:
        return "2026-01-01"
    day = int(m.group(1))
    month_name = m.group(2).lower()
    year = int(m.group(3))
    month = MONTHS_ET_PARSE.get(month_name, 1)
    return f"{year:04d}-{month:02d}-{day:02d}"


def format_date_et(iso_date, uppercase=False):
    """Formats 'YYYY-MM-DD...' into 'D. kuu YYYY' or 'D. KUU YYYY'."""
    m = re.match(r"(\d{4})-(\d{2})-(\d{2})", iso_date)
    if not m:
        return iso_date
    year, month, day = int(m.group(1)), int(m.group(2)), int(m.group(3))
    month_str = MONTHS_ET_UPPER[month] if uppercase else MONTHS_ET_LOWER[month]
    return f"{day}. {month_str} {year}"


def classify_article(cats, title, body_text=""):
    """
    Maps WordPress categories & title to PLM filter category key, display badge text,
    badge CSS class, and default fallback image.
    """
    t_low = title.lower()
    b_low = body_text.lower()
    cats_set = set(cats)

    # 1. Naiskond
    if "Naiskond" in cats_set or "naiskon" in t_low:
        return (
            "naiskond",
            "Linnanaiskond",
            "bg-crimson text-white",
            "assets/img/photos/female_team_article.jpg",
        )

    # 2. Eurosari & Mängupäev
    euro_keywords = [
        "eurosar", "euromäng", "konverentsiliiga", "euroopa liiga", "uefa",
        "hegelmann", "rapid wien", "bala town", "stjarnan", "häcken", "hacken",
        "dinamo", "ararat", "torshavn", "slask", "sabah", "floriana",
        "mänguinfo", "piletiinfo", "piletid müügil", "kodumäng!", "fännibuss"
    ]
    if any(k in t_low for k in euro_keywords):
        return (
            "eurosari",
            "Eurosari & Mängupäev",
            "bg-crimson text-white",
            "assets/img/photos/main_team_article.jpeg",
        )

    # 3. Noored & Akadeemia
    youth_keywords = [
        "noortetöö", "suvelaager", "suvelaagrit", "akadeemia", "laste vastuvõtt",
        "tulge trenni", "tule trenni", "u19", "u17", "u16", "u15", "u14", "u13",
        "noorte", "jalgpallikool", "duubel", "u21"
    ]
    if ("Noored" in cats_set and "Esindus" not in cats_set) or (
        "Duubel" in cats_set and "Esindus" not in cats_set
    ) or any(k in t_low for k in youth_keywords):
        return (
            "noored",
            "Noortetöö & Akadeemia",
            "bg-navy-700 text-white",
            "assets/img/photos/nextgen_team_article.jpg",
        )

    # 4. Kogukond
    comm_keywords = [
        "kogukond", "sinilille", "heategevus", "hooajakaart", "toetaja",
        "spordiedendaja", "horticom", "connecto", "kompetentsikeskus",
        "klubibuss", "koostööprojekt", "teenuskeskus", "staadion", "jalgpallihall",
        "sünnipäev", "perepäev", "turniir"
    ]
    if ("Kogukond" in cats_set and "Esindus" not in cats_set) or "Roheline" in cats_set or any(
        k in t_low for k in comm_keywords
    ):
        return (
            "kogukond",
            "Kogukond & Klubi",
            "bg-navy-700 text-white",
            "assets/img/news/1920x1080.jpg",
        )

    # 5. Default: Esindusmeeskond
    return (
        "esindus",
        "Esindusmeeskond",
        "bg-navy text-white",
        "assets/img/photos/main_team_article.jpeg",
    )


def rewrite_existing_2026_html(html_content):
    """Updates relative paths in an existing root `artikkel-*.html` so it lives in `uudised/2026/`."""
    # 1. Update index.html cleanup script in <head>
    old_script = """      document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll('a[href^="index.html"]').forEach(function(a) {
          var href = a.getAttribute("href");
          if (href === "index.html") {
            a.setAttribute("href", "./");
          } else if (href.indexOf("index.html#") === 0 || href.indexOf("index.html?") === 0) {
            a.setAttribute("href", "./" + href.slice("index.html".length));
          }
        });
      });"""
    new_script = """      document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll('a[href^="../../index.html"]').forEach(function(a) {
          var href = a.getAttribute("href");
          if (href === "../../index.html") {
            a.setAttribute("href", "../../");
          } else if (href.indexOf("../../index.html#") === 0 || href.indexOf("../../index.html?") === 0) {
            a.setAttribute("href", "../../" + href.slice("../../index.html".length));
          }
        });
      });"""
    out = html_content.replace(old_script, new_script)

    # 2. Replace asset paths: href="assets/...", src="assets/...", content="assets/..."
    out = re.sub(r'(href|src|content)="assets/', r'\1="../../assets/', out)

    # 3. Replace top-level page links: index.html, uudised.html, noortetoo.html, klubi.html, akadeemia.html, privaatsuspoliitika.html, pood.html
    out = re.sub(
        r'href="(index\.html|uudised\.html|noortetoo\.html|klubi\.html|akadeemia\.html|privaatsuspoliitika\.html|pood\.html)([#?][^"]*)?"',
        r'href="../../\1\2"',
        out,
    )
    return out


def render_article_html(art, related_articles):
    """Generates a complete, standardized PLM article HTML page located at `uudised/<year>/<filename>`."""
    title_escaped = html.escape(art["title"])
    excerpt_escaped = html.escape(art["excerpt"])
    cat_label_upper = html.escape(art["category_label"].upper())
    cat_label = html.escape(art["category_label"])
    date_lower = html.escape(art["date_display_lower"])
    read_min = art["read_time"]
    photo_credit = html.escape(art.get("photo_credit", "FOTO: Paide Linnameeskond"))

    img_rel = "../../" + art["img"]
    fallback_rel = "../../" + art["fallback_img"]
    alt_basename = os.path.basename(art["img"])
    news_alt_rel = f"../../assets/img/news/{alt_basename}"

    onerror_attr = (
        f"if(!this.dataset.triedNews){{this.dataset.triedNews='1';this.src='{news_alt_rel}';}}"
        f"else{{this.onerror=null;this.src='{fallback_rel}';}}"
    )

    # Build 4 related article cards
    related_cards_html = []
    for rel in related_articles[:4]:
        rel_href = f"../{rel['year']}/{rel['filename']}" if rel["year"] != art["year"] else rel["filename"]
        rel_img = "../../" + rel["img"]
        rel_fallback = "../../" + rel["fallback_img"]
        rel_news_alt = f"../../assets/img/news/{os.path.basename(rel['img'])}"
        rel_onerror = (
            f"if(!this.dataset.triedNews){{this.dataset.triedNews='1';this.src='{rel_news_alt}';}}"
            f"else{{this.onerror=null;this.src='{rel_fallback}';}}"
        )
        related_cards_html.append(f"""          <!-- Suggested Article Card -->
          <a href="{rel_href}" class="bg-white rounded-2xl hairline-border shadow-card hover:shadow-elevated hover:border-slate-300 transition-all duration-300 overflow-hidden editorial-card flex flex-col justify-between group block cursor-pointer">
            <div>
              <div class="relative h-44 overflow-hidden bg-slate-900 flex items-center justify-center">
                <img src="{rel_img}"
                     onerror="{rel_onerror}"
                     alt="{html.escape(rel['title'])}"
                     loading="lazy"
                     class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-500">
                <div class="absolute top-3 left-3">
                  <span class="px-2.5 py-1 rounded {rel['badge_class']} text-[10px] font-extrabold uppercase font-sans">
                    {html.escape(rel['category_label'])}
                  </span>
                </div>
              </div>

              <div class="p-5">
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1 font-sans">
                  {html.escape(rel['date_display_upper'])} • {rel['read_time']} MIN
                </div>
                <h3 class="font-display font-bold text-lg text-navy uppercase leading-snug group-hover:text-crimson transition-colors line-clamp-2">
                  {html.escape(rel['title'])}
                </h3>
                <p class="text-slate-500 text-xs mt-2 line-clamp-2 leading-relaxed font-sans">
                  {html.escape(rel['excerpt'])}
                </p>
              </div>
            </div>

            <div class="p-5 pt-0">
              <span class="inline-flex items-center gap-1.5 text-xs font-bold text-crimson group-hover:underline font-sans">
                <span>Loe edasi</span>
                <span class="transform group-hover:translate-x-1 transition-transform">→</span>
              </span>
            </div>
          </a>""")

    related_block = "\n\n".join(related_cards_html)

    return f"""<!DOCTYPE html>
<html lang="et" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>{title_escaped} | Uudised {art['year']} | Paide Linnameeskond</title>
  <meta name="description" content="{excerpt_escaped}">

  <!-- Favicon / Crest Icon -->
  <link rel="icon" type="image/svg+xml" href="../../assets/img/crests/paide-crest.svg">
  <link rel="icon" type="image/png" href="../../assets/img/jalgpall_logos/paide.png">
  <link rel="apple-touch-icon" href="../../assets/img/jalgpall_logos/paide.png">
  <script>
    if (window.location.protocol !== "file:") {{
      if (/\\/index\\.html$/i.test(window.location.pathname)) {{
        window.history.replaceState(null, "", window.location.pathname.replace(/\\/index\\.html$/i, "/") + window.location.search + window.location.hash);
      }}
      document.addEventListener("DOMContentLoaded", function() {{
        document.querySelectorAll('a[href^="../../index.html"]').forEach(function(a) {{
          var href = a.getAttribute("href");
          if (href === "../../index.html") {{
            a.setAttribute("href", "../../");
          }} else if (href.indexOf("../../index.html#") === 0 || href.indexOf("../../index.html?") === 0) {{
            a.setAttribute("href", "../../" + href.slice("../../index.html".length));
          }}
        }});
      }});
    }}
  </script>

  <!-- Open Graph Meta Tags -->
  <meta property="og:title" content="{title_escaped} | Paide Linnameeskond">
  <meta property="og:description" content="{excerpt_escaped}">
  <meta property="og:image" content="{img_rel}">
  <meta property="og:type" content="article">

  <!-- Google Fonts: Marcellus & Space Grotesk -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Marcellus&family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <!-- Tailwind CSS via CDN with customized Paide Design Tokens -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {{
      darkMode: 'class',
      theme: {{
        extend: {{
          colors: {{
            navy: {{
              DEFAULT: '#0b203e',
              50: '#f0f5fb',
              100: '#e1ebf7',
              200: '#c3d8ef',
              300: '#94bce4',
              400: '#5e9bd5',
              500: '#397ec5',
              600: '#2764a7',
              700: '#204f86',
              800: '#1d4370',
              900: '#0b203e',
              950: '#07152b',
            }},
            crimson: {{
              DEFAULT: '#be2931',
              50: '#fdf2f2',
              100: '#fde5e6',
              200: '#fbced0',
              300: '#f6a7ab',
              400: '#ee7177',
              500: '#e1434c',
              600: '#be2931',
              700: '#a61f26',
              800: '#8a1c22',
              900: '#731c21',
              950: '#400a0d',
            }}
          }},
          fontFamily: {{
            sans: ['"Space Grotesk"', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'sans-serif'],
            display: ['"Marcellus"', 'Georgia', 'Cambria', 'serif'],
            heading: ['"Marcellus"', 'Georgia', 'Cambria', 'serif'],
            marcellus: ['"Marcellus"', 'Georgia', 'Cambria', 'serif'],
            grotesk: ['"Space Grotesk"', 'system-ui', 'sans-serif'],
          }},
          boxShadow: {{
            'subtle': '0 1px 3px 0 rgba(11, 32, 62, 0.05), 0 1px 2px -1px rgba(11, 32, 62, 0.03)',
            'card': '0 4px 20px -2px rgba(11, 32, 62, 0.07)',
            'elevated': '0 20px 35px -8px rgba(11, 32, 62, 0.12)',
            'glow-red': '0 0 25px rgba(190, 41, 49, 0.45)',
          }}
        }}
      }}
    }}
  </script>

  <!-- Bespoke Stylesheet -->
  <link rel="stylesheet" href="../../assets/css/styles.css">
</head>

<body class="bg-[#f8fafc] text-slate-800 antialiased selection:bg-crimson selection:text-white [overflow-x:clip] w-full flex flex-col min-h-screen">
  <!-- ========================================================================= -->
  <!-- COMPONENT: Site Navigation Header (Standardized across PLM)              -->
  <!-- ========================================================================= -->
  <header id="main-header" class="sticky top-0 z-50 header-glass hairline-b transition-all duration-300 py-2.5 lg:py-3 w-full">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
      <div class="flex items-center justify-between">
        
        <!-- Left: Club Crest & Wordmark in Marcellus -->
        <a href="../../index.html" class="flex items-center space-x-2.5 sm:space-x-3 group focus:outline-none flex-shrink-0" title="Paide Linnameeskond">
          <div class="w-9 h-9 sm:w-10 sm:h-10 transition-transform duration-300 group-hover:scale-105 flex-shrink-0">
            <img src="../../assets/img/jalgpall_logos/paide.png" alt="Paide Linnameeskond Ametlik Vapp" class="club-crest-img w-full h-full object-contain filter drop-shadow-sm">
          </div>
          <div class="flex flex-col justify-center">
            <span class="font-display font-black text-lg sm:text-xl lg:text-[21px] tracking-wider text-navy leading-none group-hover:text-crimson transition-colors uppercase">
              PAIDE
            </span>
            <span class="font-display font-bold text-[9px] sm:text-[10px] tracking-[0.22em] text-slate-500 uppercase leading-tight">
              LINNAMEESKOND
            </span>
          </div>
        </a>
        <!-- Center: Primary Desktop Navigation Links -->
        <nav class="hidden lg:flex items-center space-x-1 xl:space-x-2 2xl:space-x-2.5 font-display" aria-label="Peamenüü">
          <a href="../../index.html" class="whitespace-nowrap px-2 xl:px-2.5 py-1.5 text-xs xl:text-[13px] 2xl:text-sm font-bold tracking-wider uppercase font-display transition-colors rounded-md text-navy hover:text-crimson hover:bg-slate-50/80">
            Avaleht
          </a>
          <a href="../../index.html#teams" class="whitespace-nowrap px-2 xl:px-2.5 py-1.5 text-xs xl:text-[13px] 2xl:text-sm font-bold tracking-wider uppercase font-display transition-colors rounded-md text-navy hover:text-crimson hover:bg-slate-50/80">
            Esindus
          </a>
          <a href="../../index.html#women-teams" class="whitespace-nowrap px-2 xl:px-2.5 py-1.5 text-xs xl:text-[13px] 2xl:text-sm font-bold tracking-wider uppercase font-display transition-colors rounded-md text-navy hover:text-crimson hover:bg-slate-50/80">
            Naiskond
          </a>
          <a href="../../noortetoo.html" class="whitespace-nowrap px-2 xl:px-2.5 py-1.5 text-xs xl:text-[13px] 2xl:text-sm font-bold tracking-wider uppercase font-display transition-colors rounded-md text-navy hover:text-crimson hover:bg-slate-50/80">
            Noortetöö
          </a>
          <a href="../../uudised.html" class="whitespace-nowrap px-2 xl:px-2.5 py-1.5 text-xs xl:text-[13px] 2xl:text-sm font-bold tracking-wider uppercase font-display transition-colors rounded-md text-crimson font-black">
            Uudised
          </a>
          <a href="https://varustus.paidelinnameeskond.ee/" target="_blank" rel="noopener noreferrer" class="whitespace-nowrap px-2 xl:px-2.5 py-1.5 text-xs xl:text-[13px] 2xl:text-sm font-bold tracking-wider uppercase font-display transition-colors rounded-md text-navy hover:text-crimson hover:bg-slate-50/80">
            E-pood
          </a>
          <a href="../../klubi.html" class="whitespace-nowrap px-2 xl:px-2.5 py-1.5 text-xs xl:text-[13px] 2xl:text-sm font-bold tracking-wider uppercase font-display transition-colors rounded-md text-navy hover:text-crimson hover:bg-slate-50/80">
            Klubi
          </a>
        </nav>

        <!-- Right: Action CTAs -->
        <div class="flex items-center space-x-1.5 sm:space-x-2 xl:space-x-2.5 flex-shrink-0">
          <!-- Match Center Button -->
          <a href="../../index.html#match-hub" class="whitespace-nowrap hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold tracking-wider uppercase font-display text-navy hover:bg-slate-100 hairline-border transition-all flex-shrink-0 leading-none h-8 sm:h-9" title="Mängukalender ja tulemused">
            <svg class="w-3.5 h-3.5 text-crimson flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <span class="whitespace-nowrap">Mängud</span>
          </a>

          <!-- Prominent Piletid CTA Button -->
          <a href="https://www.piletilevi.ee/korraldajad/21-69-665/jalgpalliklubi-paide-linnameeskond" target="_blank" rel="noopener noreferrer" class="whitespace-nowrap inline-flex items-center gap-1 sm:gap-1.5 px-3 sm:px-4 py-1.5 text-xs font-bold tracking-wider uppercase font-display bg-crimson hover:bg-crimson-hover text-white rounded-lg shadow-sm hover:shadow-glow-red transition-all flex-shrink-0 leading-none h-8 sm:h-9">
            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
            <span class="whitespace-nowrap">Piletid</span>
          </a>

          <!-- Mobile Hamburger Toggle -->
          <button id="mobile-menu-btn" type="button" onclick="openMobileDrawer()" class="lg:hidden p-1.5 sm:p-2 text-navy hover:text-crimson hover:bg-slate-100 rounded-lg focus:outline-none transition-colors h-8 w-8 sm:h-9 sm:w-9 flex items-center justify-center flex-shrink-0 cursor-pointer" aria-label="Ava menüü" aria-expanded="false">
            <svg class="w-5 h-5 sm:w-6 sm:h-6 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
          </button>
        </div>

      </div>
    </div>
  </header>

  <!-- ========================================================================= -->
  <!-- COMPONENT: Mobile Drawer Slide-Over Menu (Standardized across PLM)        -->
  <!-- ========================================================================= -->
  <div id="mobile-drawer" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true">
    <div id="drawer-backdrop" onclick="closeMobileDrawer()" class="fixed inset-0 bg-navy-950/70 backdrop-blur-sm opacity-0 transition-opacity duration-300"></div>

    <div id="drawer-panel" class="fixed inset-y-0 right-0 max-w-xs w-full bg-white shadow-2xl flex flex-col justify-between translate-x-full transition-transform duration-300 ease-in-out z-10">
      
      <div class="p-4 sm:p-5 hairline-b flex items-center justify-between bg-slate-50">
        <div class="flex items-center space-x-3">
          <img src="../../assets/img/jalgpall_logos/paide.png" alt="Paide Crest" class="w-8 h-9 object-contain">
          <div>
            <div class="font-display font-black text-base sm:text-lg text-navy leading-none uppercase">PAIDE LM</div>
            <div class="text-[9px] font-bold tracking-widest text-slate-400 font-sans uppercase">Ametlik Menüü</div>
          </div>
        </div>
        <button id="drawer-close-btn" type="button" onclick="closeMobileDrawer()" class="p-2 text-slate-500 hover:text-crimson rounded-lg hover:bg-slate-200 focus:outline-none cursor-pointer" aria-label="Sulge menüü">
          <svg class="w-5 h-5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <!-- Navigation links in Marcellus font -->
      <div class="px-4 sm:px-5 py-5 space-y-1 overflow-y-auto flex-1 font-display tracking-wide uppercase text-base text-navy">
        <a href="../../index.html" class="block px-3 py-2 rounded-lg transition-colors font-display hover:bg-slate-100 hover:text-crimson">Avaleht</a>
        <a href="../../index.html#teams" class="block px-3 py-2 rounded-lg transition-colors font-display hover:bg-slate-100 hover:text-crimson">Esindusmeeskond</a>
        <a href="../../index.html#women-teams" class="block px-3 py-2 rounded-lg transition-colors font-display hover:bg-slate-100 hover:text-crimson">Naiskond</a>
        <a href="../../noortetoo.html" class="block px-3 py-2 rounded-lg transition-colors font-display hover:bg-slate-100 hover:text-crimson">Noortetöö</a>
        <a href="../../uudised.html" class="block px-3 py-2 rounded-lg transition-colors font-display bg-crimson/10 text-crimson font-black">Uudised</a>
        <a href="https://varustus.paidelinnameeskond.ee/" target="_blank" rel="noopener noreferrer" class="block px-3 py-2 rounded-lg transition-colors font-display hover:bg-slate-100 hover:text-crimson">E-pood</a>
        <a href="../../index.html#match-hub" class="block px-3 py-2 rounded-lg transition-colors font-display hover:bg-slate-100 hover:text-crimson">Mängukalender</a>
        <a href="../../klubi.html" class="block px-3 py-2 rounded-lg transition-colors font-display hover:bg-slate-100 hover:text-crimson">Klubi & Kontakt</a>
      </div>

      <div class="p-4 sm:p-5 hairline-t bg-slate-50 space-y-3">
        <a href="https://www.piletilevi.ee/korraldajad/21-69-665/jalgpalliklubi-paide-linnameeskond" target="_blank" rel="noopener noreferrer" class="w-full py-2.5 rounded-lg text-xs font-bold tracking-wider uppercase font-display bg-crimson hover:bg-crimson-hover text-white shadow-md flex items-center justify-center gap-2 cursor-pointer">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
          <span>Osta mängupilet (Piletilevi)</span>
        </a>
        <div class="pt-2 border-t border-slate-200">
          <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block text-center mb-2 font-sans">Jälgi Paide Linnameeskonda</span>
          <div class="flex items-center justify-center gap-3 text-slate-600">
            <a href="https://www.facebook.com/PaideLinnameeskond" target="_blank" rel="noopener noreferrer" class="w-7 h-7 rounded-full bg-white border border-slate-200 hover:text-[#1877f2] hover:border-[#1877f2] flex items-center justify-center transition-colors" title="Facebook">
              <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
            </a>
            <a href="https://www.instagram.com/paidelinnameeskond/" target="_blank" rel="noopener noreferrer" class="w-7 h-7 rounded-full bg-white border border-slate-200 hover:text-[#e4405f] hover:border-[#e4405f] flex items-center justify-center transition-colors" title="Instagram (Esindus)">
              <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
            </a>
            <a href="https://www.tiktok.com/@paidelinnameeskond" target="_blank" rel="noopener noreferrer" class="w-7 h-7 rounded-full bg-white border border-slate-200 hover:text-black hover:border-black flex items-center justify-center transition-colors" title="TikTok">
              <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64c.298-.002.595.042.88.13V9.4a6.33 6.33 0 0 0-1-.08A6.34 6.34 0 0 0 3 15.66a6.34 6.34 0 0 0 10.82 4.49 6.3 6.3 0 0 0 1.96-4.49V8.62a8.27 8.27 0 0 0 4.81 1.53v-3.46h-1z"/></svg>
            </a>
            <a href="https://www.youtube.com/@PaideLinnameeskondTV" target="_blank" rel="noopener noreferrer" class="w-7 h-7 rounded-full bg-white border border-slate-200 hover:text-[#ff0000] hover:border-[#ff0000] flex items-center justify-center transition-colors" title="YouTube (@PaideLinnameeskondTV)">
              <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
            </a>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- ========================================================================= -->
  <!-- MAIN CONTENT CONTAINER                                                    -->
  <!-- ========================================================================= -->
  <main class="flex-1 w-full [overflow-x:clip]">

    <!-- Central Container Constraints -->
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10">

      <!-- Breadcrumbs & Back Navigation -->
      <div class="flex items-center justify-between gap-4 mb-6">
        <nav class="text-xs font-semibold text-slate-500 flex items-center gap-2 overflow-x-auto whitespace-nowrap" aria-label="Breadcrumb">
          <a href="../../index.html" class="hover:text-crimson transition-colors">Avaleht</a>
          <span class="text-slate-300">/</span>
          <a href="../../uudised.html" class="hover:text-crimson transition-colors">Uudised</a>
          <span class="text-slate-300">/</span>
          <a href="../../uudised.html?year={art['year']}" class="hover:text-crimson transition-colors">{art['year']}</a>
          <span class="text-slate-300">/</span>
          <span class="text-navy font-bold">{cat_label}</span>
        </nav>
        
        <a href="../../uudised.html?year={art['year']}" class="hidden sm:inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 hover:text-crimson transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
          <span>Tagasi uudiste juurde ({art['year']})</span>
        </a>
      </div>

      <!-- Article Header -->
      <header class="mb-8">
        
        <!-- Category & Archive Year Badges -->
        <div class="flex flex-wrap items-center gap-2.5 mb-4">
          <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider {art['badge_class']} shadow-sm font-sans">
            {cat_label_upper}
          </span>
          <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-navy/10 text-navy font-sans">
            ARHIIV • {art['year']}
          </span>
        </div>

        <!-- Main Headline in Marcellus -->
        <h1 class="font-display font-black text-3xl sm:text-4xl lg:text-5xl text-navy uppercase tracking-tight leading-[1.08] sm:leading-[1.04] mb-5 break-words [overflow-wrap:anywhere]">
          {title_escaped}
        </h1>

        <!-- Subtitle / Standfirst -->
        <p class="text-base sm:text-xl text-slate-700 leading-relaxed font-normal mb-6">
          {excerpt_escaped}
        </p>

        <!-- Meta Author & Publication Details -->
        <div class="pt-4 border-t border-slate-200/80 flex flex-wrap items-center justify-between gap-4 text-xs font-sans">
          
          <div class="flex items-center gap-3">
            <img src="../../assets/img/photos/main_team_article.jpeg" alt="Paide Linnameeskond" class="w-10 h-10 rounded-full object-cover shadow-sm flex-shrink-0">
            <div>
              <div class="font-bold text-navy text-sm">Paide Linnameeskond</div>
              <div class="text-slate-500">{date_lower} • {read_min} min lugemist</div>
            </div>
          </div>

          <!-- Social Share Buttons -->
          <div class="flex items-center gap-2">
            <span class="text-slate-400 font-bold uppercase text-[11px] mr-1 hidden sm:inline">Jaga:</span>
            
            <button type="button" 
                    onclick="navigator.clipboard.writeText(window.location.href); alert('Artikli link on kopeeritud!');" 
                    class="h-9 w-9 inline-flex items-center justify-center rounded-xl bg-white hairline-border text-slate-600 hover:text-crimson hover:border-crimson transition-all cursor-pointer shadow-subtle flex-shrink-0" 
                    title="Kopeeri link">
              <svg class="w-4 h-4 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            </button>

            <a href="https://www.facebook.com/sharer/sharer.php?u=https://paidelinnameeskond.ee" 
               target="_blank" 
               rel="noopener noreferrer" 
               class="h-9 px-3.5 inline-flex items-center justify-center gap-2 rounded-xl bg-white hairline-border text-slate-600 hover:text-[#1877f2] hover:border-[#1877f2] transition-all shadow-subtle text-xs font-bold font-sans flex-shrink-0 leading-none" 
               title="Jaga Facebookis">
              <svg class="w-4 h-4 fill-current text-[#1877f2] flex-shrink-0" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
              <span class="whitespace-nowrap">Facebook</span>
            </a>
          </div>

        </div>

      </header>

      <!-- Featured Image Hero (Full Width & Natural Aspect Ratio - Zero Cropping) -->
      <figure class="mb-10 w-full">
        <div class="relative rounded-2xl sm:rounded-3xl overflow-hidden shadow-elevated border border-slate-200/90 bg-slate-100 group">
          <img src="{img_rel}"
               onerror="{onerror_attr}"
               alt="{title_escaped}" 
               class="w-full h-auto block object-contain transform group-hover:scale-[1.008] transition-transform duration-500">
        </div>
        <figcaption class="mt-3 flex items-center justify-between text-xs text-slate-500 font-sans px-1">
          <span class="inline-flex items-center gap-1.5 font-semibold text-slate-600">
            <span class="w-1.5 h-1.5 rounded-full bg-crimson"></span>
            {cat_label} • {date_lower}
          </span>
          <span class="text-[11px] font-medium text-slate-400">
            {photo_credit}
          </span>
        </figcaption>
      </figure>

      <!-- Narrative Article Body -->
      <article class="prose prose-slate max-w-none mb-14 text-slate-800">
{art['body_html']}
      </article>

      <!-- Social Share Footer Banner -->
      <div class="p-6 sm:p-8 rounded-2xl bg-slate-50 hairline-border mb-16 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div>
          <h4 class="font-display font-bold text-navy uppercase text-lg">Meeldis see uudis?</h4>
          <p class="text-xs text-slate-500 font-sans mt-0.5">Jaga artiklit oma sõpradega ja toeta Järvamaa esindusklubi!</p>
        </div>
        <div class="flex items-center gap-3">
          <button type="button" 
                  onclick="navigator.clipboard.writeText(window.location.href); alert('Artikli link on kopeeritud!');" 
                  class="h-10 px-4 inline-flex items-center justify-center rounded-xl bg-white hairline-border text-navy hover:text-crimson font-bold text-xs uppercase tracking-wider transition-all shadow-subtle gap-2 cursor-pointer">
            <svg class="w-4 h-4 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            <span>Kopeeri link</span>
          </button>
          <a href="../../uudised.html?year={art['year']}" class="h-10 px-4 inline-flex items-center justify-center rounded-xl bg-crimson hover:bg-crimson-hover text-white font-bold text-xs uppercase tracking-wider transition-all shadow-subtle">
            Kõik {art['year']} uudised
          </a>
        </div>
      </div>

    </div>

    <!-- ========================================================================= -->
    <!-- SUGGESTED 4 RELATED ARTICLES SECTION                                      -->
    <!-- ========================================================================= -->
    <section class="bg-slate-100/70 border-t border-slate-200/90 py-12 sm:py-16 w-full">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="flex items-end justify-between mb-8 pb-3 hairline-b">
          <div>
            <div class="flex items-center gap-2 text-crimson text-xs font-bold tracking-widest uppercase mb-1 font-sans">
              <span class="w-2 h-2 rounded-full bg-crimson"></span>
              Loe Lisaks
            </div>
            <h2 class="font-display font-black text-2xl sm:text-3xl text-navy tracking-tight uppercase">
              SEOTUD UUDISED JA ARTIKLID
            </h2>
          </div>
          <a href="../../uudised.html" class="text-xs font-bold text-navy hover:text-crimson uppercase tracking-wider font-sans hidden sm:inline-flex items-center gap-1">
            <span>Vaata kõiki uudiseid</span>
            <svg class="w-3.5 h-3.5 text-crimson" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
          </a>
        </div>

        <!-- 4-Card Responsive Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
{related_block}
        </div>

      </div>
    </section>

  </main>

  <!-- ========================================================================= -->
  <!-- STANDARDIZED RESPONSIVE FOOTER                                            -->
  <!-- ========================================================================= -->
  <footer class="bg-navy-950 text-white border-t border-navy-900 pt-12 pb-8 w-full mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      
      <!-- Footer Grid: 4 Clean Columns -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 mb-12">
        
        <!-- Column 1: Club Branding -->
        <div class="w-full text-left">
          <div class="flex items-center space-x-3 mb-4">
            <img src="../../assets/img/jalgpall_logos/paide.png" alt="Paide Linnameeskond Vapp" class="w-10 h-12 object-contain">
            <div>
              <span class="font-display font-black text-lg text-white uppercase block leading-none">PAIDE</span>
              <span class="font-display font-medium text-xs text-crimson-400 uppercase tracking-widest block">LINNAMEESKOND</span>
            </div>
          </div>
          <p class="text-sm leading-relaxed text-gray-300 mb-4 font-sans">
            Süda, Kirg, Ühtsus. Järvamaa jalgpalli uhkus ja noorte tee tippsporti.
          </p>
          <div class="text-xs text-gray-400 space-y-1 font-sans">
            <div>Klubihoone: Staadioni 4, Paide</div>
            <div>Telefon: +372 5656 7076</div>
            <div>E-post: info@paidelinnameeskond.ee</div>
          </div>
        </div>

        <!-- Column 2: Meeskonnad -->
        <div class="w-full text-left">
          <h4 class="text-base font-bold text-white uppercase tracking-wider mb-4 border-l-2 border-crimson pl-2.5 font-sans">
            MEESKONNAD
          </h4>
          <ul class="space-y-2.5 text-sm leading-relaxed text-gray-300 font-sans">
            <li><a href="../../index.html#teams" class="hover:text-crimson-400 transition-colors">Esindusmeeskond</a></li>
            <li><a href="../../index.html#women-teams" class="hover:text-crimson-400 transition-colors">Naiskond</a></li>
            <li><a href="../../noortetoo.html" class="hover:text-crimson-400 transition-colors">Noortetöö & Jalgpallikool</a></li>
            <li class="hidden"><a href="../../akadeemia.html" class="hover:text-crimson-400 transition-colors">Jalgpalliakadeemia</a></li>
            <li><a href="../../index.html#teams" class="hover:text-crimson-400 transition-colors">Paide LM III</a></li>
            <li><a href="../../index.html#match-hub" class="hover:text-crimson-400 transition-colors">Mängukalender</a></li>
          </ul>
        </div>

        <!-- Column 3: Klubi & Teenused -->
        <div class="w-full text-left">
          <h4 class="text-base font-bold text-white uppercase tracking-wider mb-4 border-l-2 border-crimson pl-2.5 font-sans">
            KLUBI
          </h4>
          <ul class="space-y-2.5 text-sm leading-relaxed text-gray-300 font-sans">
            <li><a href="../../uudised.html" class="hover:text-crimson-400 transition-colors">Uudised ja artiklid</a></li>
            <li><a href="../../index.html#paide-tv" class="hover:text-crimson-400 transition-colors">Paide TV videod</a></li>
            <li class="hidden"><a href="../../akadeemia.html#sisseastumine" class="hover:text-crimson-400 transition-colors">Akadeemia vastuvõtt</a></li>
            <li><a href="../../noortetoo.html#registreeru" class="hover:text-crimson-400 transition-colors">Lapse registreerimine</a></li>
            <li><a href="https://www.piletilevi.ee/korraldajad/21-69-665/jalgpalliklubi-paide-linnameeskond" target="_blank" rel="noopener noreferrer" class="hover:text-crimson-400 transition-colors">Piletid Piletilevis</a></li>
          </ul>
        </div>

        <!-- Column 4: Sotsiaalmeedia & Toetus -->
        <div class="w-full text-left">
          <h4 class="text-base font-bold text-white uppercase tracking-wider mb-4 border-l-2 border-crimson pl-2.5 font-sans">
            JÄLGI MEID
          </h4>
          <p class="text-sm leading-relaxed text-gray-300 mb-4 font-sans">
            Ole kursis Paide Linnameeskonna tegemistega sotsiaalmeedias.
          </p>
          <div class="flex items-center space-x-3 text-slate-300">
            <a href="https://www.facebook.com/paidelinnameeskond" target="_blank" rel="noopener noreferrer" class="w-9 h-9 rounded-xl bg-navy-900 hover:bg-crimson flex items-center justify-center transition-colors text-slate-300 hover:text-white" title="Facebook">
              <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
            </a>
            <a href="https://www.instagram.com/paidelinnameeskond/" target="_blank" rel="noopener noreferrer" class="w-9 h-9 rounded-xl bg-navy-900 hover:bg-crimson flex items-center justify-center transition-colors text-slate-300 hover:text-white" title="Instagram">
              <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
            </a>
            <a href="https://www.tiktok.com/@paidelinnameeskond" target="_blank" rel="noopener noreferrer" class="w-9 h-9 rounded-xl bg-navy-900 hover:bg-crimson flex items-center justify-center transition-colors text-slate-300 hover:text-white" title="TikTok">
              <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64c.298-.002.595.042.88.13V9.4a6.33 6.33 0 0 0-1-.08A6.34 6.34 0 0 0 3 15.66a6.34 6.34 0 0 0 10.82 4.49 6.3 6.3 0 0 0 1.96-4.49V8.62a8.27 8.27 0 0 0 4.81 1.53v-3.46h-1z"/></svg>
            </a>
            <a href="https://www.youtube.com/@PaideLinnameeskondTV" target="_blank" rel="noopener noreferrer" class="w-9 h-9 rounded-xl bg-navy-900 hover:bg-crimson flex items-center justify-center transition-colors text-slate-300 hover:text-white" title="YouTube">
              <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
            </a>
          </div>
        </div>

      </div>

      <!-- Bottom Bar -->
      <div class="pt-6 border-t border-navy-900/80 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500 font-sans gap-3">
        <p>&copy; 2026 Paide Linnameeskond. Kõik õigused kaitstud.</p>
        <div class="flex items-center gap-4 text-slate-400">
          <a href="../../privaatsuspoliitika.html" class="hover:text-white transition-colors">Privaatsuspoliitika</a>
          <span>•</span>
          <span>Jalgpall Südames</span>
        </div>
      </div>

    </div>
  </footer>

  <!-- Standardized Mobile Drawer Script across PLM -->
  <script>
    function openMobileDrawer() {{
      const drawer = document.getElementById('mobile-drawer');
      const panel = document.getElementById('drawer-panel');
      const backdrop = document.getElementById('drawer-backdrop');
      const openBtn = document.getElementById('mobile-menu-btn');
      if (!drawer) return;

      drawer.classList.remove('hidden');
      drawer.classList.add('drawer-open');
      void drawer.offsetWidth;
      if (panel) {{
        panel.classList.remove('translate-x-full');
        panel.classList.add('translate-x-0');
      }}
      if (backdrop) {{
        backdrop.classList.remove('opacity-0');
        backdrop.classList.add('opacity-100');
      }}
      document.body.classList.add('overflow-hidden');
      if (openBtn) openBtn.setAttribute('aria-expanded', 'true');
    }}

    function closeMobileDrawer() {{
      const drawer = document.getElementById('mobile-drawer');
      const panel = document.getElementById('drawer-panel');
      const backdrop = document.getElementById('drawer-backdrop');
      const openBtn = document.getElementById('mobile-menu-btn');
      if (!drawer) return;

      drawer.classList.remove('drawer-open');
      if (panel) {{
        panel.classList.remove('translate-x-0');
        panel.classList.add('translate-x-full');
      }}
      if (backdrop) {{
        backdrop.classList.remove('opacity-100');
        backdrop.classList.add('opacity-0');
      }}
      document.body.classList.remove('overflow-hidden');
      if (openBtn) openBtn.setAttribute('aria-expanded', 'false');

      setTimeout(function() {{
        if (!drawer.classList.contains('drawer-open')) {{
          drawer.classList.add('hidden');
        }}
      }}, 300);
    }}

    function toggleMobileDrawer() {{
      const drawer = document.getElementById('mobile-drawer');
      if (!drawer) return;
      if (drawer.classList.contains('hidden') || !drawer.classList.contains('drawer-open')) {{
        openMobileDrawer();
      }} else {{
        closeMobileDrawer();
      }}
    }}

    window.openMobileDrawer = openMobileDrawer;
    window.closeMobileDrawer = closeMobileDrawer;
    window.toggleMobileDrawer = toggleMobileDrawer;

    (function() {{
      const openBtn = document.getElementById('mobile-menu-btn');
      const closeBtn = document.getElementById('drawer-close-btn');
      const drawer = document.getElementById('mobile-drawer');
      const backdrop = document.getElementById('drawer-backdrop');

      if (openBtn) {{
        openBtn.addEventListener('click', function(e) {{
          e.preventDefault();
          openMobileDrawer();
        }});
        openBtn.addEventListener('touchend', function(e) {{
          e.preventDefault();
          openMobileDrawer();
        }}, {{ passive: false }});
      }}
      if (closeBtn) {{
        closeBtn.addEventListener('click', function(e) {{
          e.preventDefault();
          closeMobileDrawer();
        }});
      }}
      if (backdrop) {{
        backdrop.addEventListener('click', function(e) {{
          e.preventDefault();
          closeMobileDrawer();
        }});
      }}
      document.addEventListener('keydown', function(e) {{
        if (e.key === 'Escape' && drawer && !drawer.classList.contains('hidden')) {{
          closeMobileDrawer();
        }}
      }});
    }})();
  </script>
  <script src="../../assets/js/cookie-consent.js"></script>
</body>
</html>
"""


def style_html_body(raw_article_html, year):
    """Extracts and styles the inner body of an exported `html/posts/YYYY/*.html` file."""
    # Extract everything inside <article>...</article>
    m = re.search(r"<article>(.*?)</article>", raw_article_html, re.S)
    inner = m.group(1) if m else raw_article_html

    # Remove <h1>, <p class="meta">, first <figure> (featured image), <p class="lead">, <p class="wp-note">
    inner = re.sub(r"<h1>.*?</h1>\s*", "", inner, count=1, flags=re.S)
    inner = re.sub(r'<p class="meta">.*?</p>\s*', "", inner, count=1, flags=re.S)
    inner = re.sub(r"^(\s*<figure>.*?</figure>\s*)", "", inner, count=1, flags=re.S)
    inner = re.sub(r'<p class="lead">.*?</p>\s*', "", inner, count=1, flags=re.S)
    inner = re.sub(r'<p class="wp-note">.*?</p>\s*', "", inner, flags=re.S)

    # Extract photo credit if present in first paragraph like (FOTO: ...) or FOTO: ...
    photo_credit = "FOTO: Paide Linnameeskond"
    foto_m = re.search(r"\(?(FOTO:\s*[^)<\n]+)\)?", inner, re.I)
    if foto_m:
        photo_credit = foto_m.group(1).strip().rstrip(".")

    # Rewrite inline images src="../../../uploads/YYYY/MM/file.ext" -> "../../assets/img/uploads/YYYY/MM/file.ext"
    def replace_img(match):
        full_tag = match.group(0)
        src_m = re.search(r'src="([^"]+)"', full_tag)
        if not src_m:
            return full_tag
        src = src_m.group(1)
        rel_upload = re.sub(r"^(?:\.\./)+uploads/", "", src)
        rel_upload = re.sub(r"^https?://(?:www\.)?paidelinnameeskond\.ee/wp-content/uploads/", "", rel_upload)
        rel_upload = resolve_upload_rel(rel_upload)
        new_src = f"../../assets/img/uploads/{rel_upload}"
        return (
            f'<figure class="my-8 sm:my-10 w-full">'
            f'<div class="relative rounded-2xl sm:rounded-3xl overflow-hidden shadow-card border border-slate-200/90 bg-slate-100">'
            f'<img src="{new_src}" loading="lazy" onerror="this.closest(\'figure\').style.display=\'none\'" '
            f'alt="" class="w-full h-auto block object-contain">'
            f'</div></figure>'
        )

    inner = re.sub(r"<figure>\s*<img[^>]+>\s*(?:<figcaption>.*?</figcaption>\s*)?</figure>", replace_img, inner, flags=re.S)
    inner = re.sub(r"<p>\s*<img[^>]+>\s*</p>", replace_img, inner, flags=re.S)
    inner = re.sub(r"<img[^>]+>", replace_img, inner)

    # Style YouTube/video iframes
    inner = re.sub(
        r'<iframe([^>]+)></iframe>',
        r'<div class="my-8 aspect-video w-full rounded-2xl overflow-hidden shadow-card border border-slate-200"><iframe class="w-full h-full"\1 allowfullscreen></iframe></div>',
        inner,
    )

    # Style headings, paragraphs, lists, blockquotes, tables
    inner = re.sub(
        r"<h2>",
        '<h2 class="font-display font-black text-xl sm:text-2xl text-navy uppercase tracking-tight mt-10 mb-4">',
        inner,
    )
    inner = re.sub(
        r"<h3>",
        '<h3 class="font-display font-bold text-lg sm:text-xl text-navy uppercase tracking-tight mt-8 mb-3">',
        inner,
    )
    inner = re.sub(
        r"<p>",
        '<p class="text-base sm:text-lg text-slate-700 leading-relaxed font-sans mb-6">',
        inner,
    )
    inner = re.sub(
        r"<ul>",
        '<ul class="list-disc pl-6 space-y-2 text-base sm:text-lg text-slate-700 font-sans mb-6">',
        inner,
    )
    inner = re.sub(
        r"<ol>",
        '<ol class="list-decimal pl-6 space-y-2 text-base sm:text-lg text-slate-700 font-sans mb-6">',
        inner,
    )
    inner = re.sub(
        r"<blockquote>",
        '<blockquote class="my-8 p-6 sm:p-8 bg-crimson/5 rounded-2xl sm:rounded-3xl border-l-4 border-crimson text-slate-800 italic font-display text-lg leading-relaxed shadow-subtle">',
        inner,
    )
    inner = re.sub(
        r"<table>",
        '<div class="overflow-x-auto my-6 rounded-xl border border-slate-200"><table class="w-full text-sm text-left text-slate-700 font-sans border-collapse">',
        inner,
    )
    inner = re.sub(r"</table>", "</table></div>", inner)
    inner = re.sub(r"<td>", '<td class="px-4 py-2.5 border-b border-slate-200">', inner)

    # Make first paragraph bold if it has no <strong> yet and is a normal lead paragraph
    first_p_m = re.search(
        r'(<p class="text-base sm:text-lg text-slate-700 leading-relaxed font-sans mb-6">)(.*?)(</p>)',
        inner,
        re.S,
    )
    if first_p_m and "<strong>" not in first_p_m.group(2):
        lead_inner = f"<strong>{first_p_m.group(2)}</strong>"
        inner = inner[: first_p_m.start()] + first_p_m.group(1) + lead_inner + first_p_m.group(3) + inner[first_p_m.end() :]

    # Indent nicely
    indented = "\n".join("        " + line for line in inner.strip().splitlines())
    return indented, photo_credit


def main():
    os.chdir(WORKSPACE)

    # 1. Create `uudised/YYYY` directories and `assets/img/uploads`
    for y in range(2016, 2027):
        os.makedirs(os.path.join(WORKSPACE, "uudised", str(y)), exist_ok=True)
    os.makedirs(os.path.join(WORKSPACE, "assets", "img", "uploads"), exist_ok=True)

    # 2. Parse existing 44 articles from `uudised.html` so we preserve their exact metadata,
    #    and move their root `artikkel-*.html` files into `uudised/2026/`.
    uudised_txt = open(os.path.join(WORKSPACE, "uudised.html"), encoding="utf-8").read()
    existing_cards = re.findall(
        r'<article class="news-item[^"]*"[^>]*>.*?</article>', uudised_txt, re.S
    )

    existing_articles_by_file = {}
    for idx, card_html in enumerate(existing_cards):
        href_m = re.search(r'href="([^"]+)"', card_html)
        if not href_m:
            continue
        filename = os.path.basename(href_m.group(1))
        cat_m = re.search(r'data-category="([^"]+)"', card_html)
        cat_key = cat_m.group(1).strip() if cat_m else "esindus"
        search_m = re.search(r'data-search="([^"]+)"', card_html)
        search_txt = search_m.group(1).strip() if search_m else ""

        img_m = re.search(r'<img src="([^"]+)"', card_html)
        img_src = img_m.group(1).strip() if img_m else "assets/img/photos/main_team_article.jpeg"
        img_src = re.sub(r"^(\.\./)+", "", img_src)

        badge_m = re.search(
            r'<span class="px-2\.5 py-1 rounded ([^"]+)">\s*(.*?)\s*</span>', card_html, re.S
        )
        badge_cls = badge_m.group(1).strip() if badge_m else "bg-navy text-white"
        cat_label = badge_m.group(2).strip() if badge_m else "Esindusmeeskond"

        dt_m = re.search(r"<span>(\d{1,2}\.\s*[A-ZÄÖÜÕa-zäöüõ]+\s*202\d)</span>", card_html)
        dt_upper = dt_m.group(1).strip().upper() if dt_m else "1. JAANUAR 2026"
        iso_date = parse_estonian_date(dt_upper)
        # Add tie-breaking time so cards on the same day keep their exact order from uudised.html
        iso_datetime = f"{iso_date} {23 - (idx % 12):02d}:{59 - idx:02d}"

        min_m = re.search(r"<span>(\d+)\s*MIN</span>", card_html, re.I)
        read_time = int(min_m.group(1)) if min_m else 2

        title_m = re.search(r"<h3[^>]*>(.*?)</h3>", card_html, re.S)
        title = re.sub(r"\s+", " ", title_m.group(1)).strip() if title_m else filename

        exc_m = re.search(r"<p[^>]*>(.*?)</p>", card_html, re.S)
        excerpt = re.sub(r"\s+", " ", exc_m.group(1)).strip() if exc_m else ""

        existing_articles_by_file[filename] = {
            "id": filename.replace(".html", ""),
            "filename": filename,
            "url": f"uudised/2026/{filename}",
            "title": html.unescape(title),
            "excerpt": html.unescape(excerpt),
            "date_iso": iso_datetime,
            "date_display_upper": dt_upper,
            "date_display_lower": format_date_et(iso_date, uppercase=False),
            "year": "2026",
            "read_time": read_time,
            "category_key": cat_key,
            "category_label": html.unescape(cat_label),
            "badge_class": badge_cls,
            "img": img_src,
            "fallback_img": "assets/img/photos/main_team_article.jpeg",
            "search_text": html.unescape(search_txt),
            "is_existing_2026": True,
        }

        # Move/copy root file into `uudised/2026/` with updated relative paths
        root_file_path = os.path.join(WORKSPACE, filename)
        target_2026_path = os.path.join(WORKSPACE, "uudised", "2026", filename)
        if os.path.exists(root_file_path):
            raw_html = open(root_file_path, encoding="utf-8").read()
            updated_html = rewrite_existing_2026_html(raw_html)
            with open(target_2026_path, "w", encoding="utf-8") as out_f:
                out_f.write(updated_html)
            os.remove(root_file_path)

    print(f"Processed {len(existing_articles_by_file)} existing 2026 articles into uudised/2026/.")

    # Known mapping of the 30 already-restored 2026 markdown files so we skip duplicating them
    already_restored_2026_md = {
        "2026-01-19-uued-treenerid-portugalist.md": "artikkel-uued-treenerid-portugalist.html",
        "2026-01-30-juksar-ja-oun-linnanaiskonnas.md": "artikkel-juksar-oun-naiskonnas.html",
        "2026-02-09-linnameeskonnaga-liitus-keskpoolkaitsja-daniel-cabral.md": "artikkel-daniel-cabral.html",
        "2026-02-26-toeta-klubi-linnameeskonna-hooajakaart-muugil.md": "artikkel-hooajakaart-2026.html",
        "2026-03-09-manguinfo-tipneri-karika-kodumang-kolmapaeval-11-martsil.md": "artikkel-tipneri-karikas-kodumang.html",
        "2026-03-13-kodumang-puhapaeval-paide-vs-narva-trans.md": "artikkel-paide-narva-trans.html",
        "2026-03-19-linnameeskonna-noortetoo-palvis-kolmanda-tarni.md": "artikkel-noortetoo-kolmas-tarn.html",
        "2026-03-20-linnanaiskonna-toetajaks-saab-tunnustatud-advokaadiburoo.md": "artikkel-naiskond-eversheds.html",
        "2026-03-26-jalgpalliakadeemia-sai-suurtoetaja.md": "artikkel-akadeemia-comfort.html",
        "2026-03-29-sinilillejooks-2026.md": "artikkel-sinilillejooks-2026.html",
        "2026-04-06-kesknadala-mang-toob-nomme-paidesse.md": "artikkel-nomme-united-paidesse.html",
        "2026-04-27-koostoo-vladimir-vassiljeviga-loppes.md": "artikkel-vassiljev-koostoo-lopp.html",
        "2026-05-04-bolufer-esindusmeeskonda-freitas-duubli-peatreeneriks.md": "artikkel-bolufer-freitas.html",
        "2026-06-20-horticom-vaetab-klubi-kasvab.md": "artikkel-horticom-vaetab.html",
        "2026-06-26-esindusmeeskonna-treeneritetiimiga-liitub-mika-laurikainen.md": "artikkel-mika-laurikainen.html",
        "2026-07-06-fc-hegelmann-vs-paide-linnameeskond.md": "artikkel-fc-hegelmann-avaloog.html",
        "2026-07-12-media-pressiinfo-information-for-press-16-07-2026.md": "artikkel-media-pressiinfo-1607.html",
        "2026-07-12-piletiinfo-euromangule-osta-pilet-parnusse-siit.md": "artikkel-euromang-hegelmann-piletid.html",
        "2026-07-14-linnameeskond-taienes-kahe-spordiedendajaga.md": "artikkel-spordiedendajad.html",
        "2026-07-21-media-pressiinfo-press-info-22-23-07-2026.md": "artikkel-media-pressiinfo-2307.html",
        "2026-07-21-piletid-muugil-eurosarja-teine-ring-parnus-juba-neljapaeval.md": "artikkel-eurosari-zira-piletid.html",
        "2026-08-02-kolmas-ring-piletid-paide-vs-rapid-wien-mangule-on-muugil.md": "artikkel-rapid-wien-piletid.html",
        "2026-08-02-kristofer-kait-liitub-paide-linnameeskonnaga.md": "artikkel-kristofer-kait.html",
        "2026-08-06-naiskond-taiendas-ridu-aarekaitsjaga.md": "artikkel-naiskond-nora-mai-kool.html",
        "2026-08-10-natural-suvelaagrit-nauditi-taiest-sudamest.md": "artikkel-natural-suvelaager.html",
        "2026-08-17-paide-hankis-aarerundaja-kolumbiast.md": "artikkel-palacios-kolumbia.html",
        "2026-08-21-paidega-liitus-gambia-rundaja-dawda-darboe.md": "artikkel-dawda-darboe.html",
        "2026-08-31-uute-laste-vastuvott-klubisse-on-alanud.md": "artikkel-uute-laste-vastuvott.html",
        "2026-09-08-badamosi-tsehhi-korgliigasse.md": "artikkel-badamosi-tsehhi-korgliigasse.html",
        "2026-09-18-linnanaiskond-sai-esimese-valismangija.md": "artikkel-linnanaiskond-mayo.html",
    }

    # Update exact timestamp on those 30 existing articles from their markdown files
    for md_base, existing_fn in already_restored_2026_md.items():
        md_path = os.path.join(BACKUP_DIR, "markdown", "posts", "2026", md_base)
        if os.path.exists(md_path) and existing_fn in existing_articles_by_file:
            md_txt = open(md_path, encoding="utf-8").read()
            d_m = re.search(r'date:\s*"([^"]+)"', md_txt)
            if d_m:
                existing_articles_by_file[existing_fn]["date_iso"] = d_m.group(1)

    # 3. Parse all 2016–2026 backup news posts
    md_files = sorted(glob.glob(os.path.join(BACKUP_DIR, "markdown", "posts", "*", "*.md")))
    restored_articles = []
    uploads_needed = set()

    for md_path in md_files:
        md_base = os.path.basename(md_path)
        year = os.path.basename(os.path.dirname(md_path))

        # Skip if already among the 30 restored 2026 articles
        if year == "2026" and md_base in already_restored_2026_md:
            continue

        md_txt = open(md_path, encoding="utf-8").read()
        cats_m = re.search(r"categories:\s*(\[.*?\])", md_txt)
        cats = json.loads(cats_m.group(1)) if cats_m else []

        # Skip player roster profile posts (e.g. "Meeskond" or "2021-08-23-7-pa-assan-corr.md")
        if ("Meeskond" in cats and "Uudised" not in cats) or md_base == "2021-08-23-7-pa-assan-corr.md":
            continue

        title_m = re.search(r'title:\s*"((?:[^"\\]|\\.)*)"', md_txt)
        title = title_m.group(1).replace('\\"', '"') if title_m else md_base

        date_m = re.search(r'date:\s*"([^"]+)"', md_txt)
        date_iso = date_m.group(1) if date_m else f"{year}-01-01 12:00"

        slug_m = re.search(r'slug:\s*"([^"]+)"', md_txt)
        slug = slug_m.group(1) if slug_m else md_base.replace(".md", "")
        # Ensure clean filename `artikkel-<slug>.html`
        clean_slug = re.sub(r"[^a-z0-9-]", "-", slug.lower()).strip("-")
        filename = f"artikkel-{clean_slug}.html"

        feat_m = re.search(r'featured_image:\s*"([^"]+)"', md_txt)
        feat_url = feat_m.group(1) if feat_m else ""

        exc_m = re.search(r'excerpt:\s*"((?:[^"\\]|\\.)*)"', md_txt)
        excerpt = exc_m.group(1).replace('\\"', '"').strip() if exc_m else ""

        # Read corresponding HTML post
        html_path = os.path.join(BACKUP_DIR, "html", "posts", year, md_base.replace(".md", ".html"))
        raw_html_post = open(html_path, encoding="utf-8").read() if os.path.exists(html_path) else ""

        # Collect inline upload URLs
        for inline_u in re.findall(
            r"https?://(?:www\.)?paidelinnameeskond\.ee/wp-content/uploads/([^\s\"\)\]]+)", md_txt
        ):
            uploads_needed.add(inline_u)

        body_html, photo_credit = style_html_body(raw_html_post, year)

        # Plain text for excerpt / word count / search
        plain_body = re.sub(r"<[^>]+>", " ", body_html)
        plain_body = html.unescape(re.sub(r"\s+", " ", plain_body)).strip()
        # Strip leading (FOTO: ...) from excerpt if present
        clean_plain = re.sub(r"^\s*\(?FOTO:[^)]+\)?\.?\s*", "", plain_body, flags=re.I)

        if not excerpt:
            excerpt = clean_plain[:210].rsplit(" ", 1)[0] + ("..." if len(clean_plain) > 210 else "")
        if not excerpt:
            excerpt = title

        words = len(plain_body.split())
        read_time = max(1, round(words / 180))

        cat_key, cat_label, badge_cls, fallback_img = classify_article(cats, title, plain_body)

        if feat_url:
            rel_upload = re.sub(
                r"^https?://(?:www\.)?paidelinnameeskond\.ee/wp-content/uploads/", "", feat_url
            )
            uploads_needed.add(unicodedata.normalize("NFC", rel_upload))
            img_path = f"assets/img/uploads/{resolve_upload_rel(rel_upload)}"
        else:
            # Check if an inline image exists in the post
            inline_m = re.search(
                r"https?://(?:www\.)?paidelinnameeskond\.ee/wp-content/uploads/([^\s\"\)\]]+)", md_txt
            )
            if inline_m:
                rel_upload = inline_m.group(1)
                uploads_needed.add(unicodedata.normalize("NFC", rel_upload))
                img_path = f"assets/img/uploads/{resolve_upload_rel(rel_upload)}"
            else:
                img_path = fallback_img

        date_upper = format_date_et(date_iso, uppercase=True)
        date_lower = format_date_et(date_iso, uppercase=False)

        search_str = f"{title} {excerpt} {cat_label} {date_lower} {year}".lower()

        restored_articles.append(
            {
                "id": f"{year}-{clean_slug}",
                "filename": filename,
                "url": f"uudised/{year}/{filename}",
                "title": title,
                "excerpt": excerpt,
                "date_iso": date_iso,
                "date_display_upper": date_upper,
                "date_display_lower": date_lower,
                "year": year,
                "read_time": read_time,
                "category_key": cat_key,
                "category_label": cat_label,
                "badge_class": badge_cls,
                "img": img_path,
                "fallback_img": fallback_img,
                "search_text": search_str,
                "body_html": body_html,
                "photo_credit": photo_credit,
                "is_existing_2026": False,
            }
        )

    print(f"Parsed {len(restored_articles)} historical news articles to restore (2016–2026).")

    # Combine all articles and sort in strict reverse chronological order (newest first)
    all_articles = list(existing_articles_by_file.values()) + restored_articles
    all_articles.sort(key=lambda x: x["date_iso"], reverse=True)

    # 4. Render all restored article HTML files in `uudised/<year>/<filename>`
    for idx, art in enumerate(all_articles):
        if art.get("is_existing_2026"):
            continue
        # Pick 4 chronologically adjacent articles (e.g. next/previous in timeline)
        neighbors = []
        for offset in [-2, -1, 1, 2, 3, 4, -3, -4]:
            n_idx = idx + offset
            if 0 <= n_idx < len(all_articles) and n_idx != idx:
                neighbors.append(all_articles[n_idx])
            if len(neighbors) == 4:
                break

        page_html = render_article_html(art, neighbors)
        out_path = os.path.join(WORKSPACE, "uudised", art["year"], art["filename"])
        with open(out_path, "w", encoding="utf-8") as f:
            f.write(page_html)

    # 5. Export `assets/js/news-archive-data.js` for `uudised.html`
    archive_json_list = []
    for art in all_articles:
        archive_json_list.append(
            {
                "url": art["url"],
                "title": art["title"],
                "excerpt": art["excerpt"],
                "dateIso": art["date_iso"][:10],
                "dateDisplay": art["date_display_upper"],
                "year": art["year"],
                "readTime": art["read_time"],
                "cat": art["category_key"],
                "catLabel": art["category_label"],
                "badgeClass": art["badge_class"],
                "img": art["img"],
                "fallbackImg": art["fallback_img"],
                "search": art["search_text"],
            }
        )

    js_out_path = os.path.join(WORKSPACE, "assets", "js", "news-archive-data.js")
    with open(js_out_path, "w", encoding="utf-8") as f:
        f.write("// Auto-generated Paide Linnameeskond News Archive Index (2016–2026)\n")
        f.write("window.PLM_NEWS_ARCHIVE = ")
        json.dump(archive_json_list, f, ensure_ascii=False, separators=(",", ":"))
        f.write(";\n")

    # 6. Export `data/uploads-image-list.txt`
    uploads_list_path = os.path.join(WORKSPACE, "data", "uploads-image-list.txt")
    with open(uploads_list_path, "w", encoding="utf-8") as f:
        for u in sorted(uploads_needed):
            f.write(f"wp-content/uploads/{u}\n")

    print(f"Total articles in archive: {len(all_articles)}")
    print(f"Wrote {js_out_path} ({os.path.getsize(js_out_path) // 1024} KB)")
    print(f"Wrote {uploads_list_path} ({len(uploads_needed)} image paths)")


if __name__ == "__main__":
    main()
