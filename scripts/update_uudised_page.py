#!/usr/bin/env python3
import re

with open("uudised.html", encoding="utf-8") as f:
    txt = f.read()

# 1. Update featured story link
txt = txt.replace(
    'href="artikkel-paide-kostitas-kalevit-uheksa-varavaga.html"',
    'href="uudised/2026/artikkel-paide-kostitas-kalevit-uheksa-varavaga.html"',
)

# 2. Replace Filter Tabs + News Grid + Script section
start_marker = "    <!-- Filter Tabs & Live Search Section -->"
if start_marker not in txt:
    start_marker = "    <!-- Filter Tabs, Year Archive & Live Search Section -->"
end_marker = "  <!-- Standardized Mobile Drawer Script across PLM -->"

assert start_marker in txt and end_marker in txt, "Markers not found!"

cards = re.findall(r'<article class="news-item[^"]*"[^>]*>.*?</article>', txt, re.S)
first_cards = []
for c in cards[:12]:
    c_updated = re.sub(r'href="(?:uudised/2026/)?(artikkel-[^"]+)"', r'href="uudised/2026/\1"', c)
    first_cards.append("        " + c_updated)
initial_grid_html = "\n\n".join(first_cards)

new_section = f"""    <!-- Filter Tabs, Year Archive & Live Search Section -->
    <section id="archive-controls" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-8">
      <div class="bg-white rounded-2xl hairline-border shadow-card p-3.5 sm:p-5 space-y-3.5">
        
        <!-- Top Row: Category Filter Tabs + Live Search Input -->
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
          
          <!-- Category Filter Tabs -->
          <div class="flex flex-wrap items-center gap-1.5 min-w-0" id="filter-tabs">
            <button type="button" data-filter="all" class="filter-btn active-tab px-3 py-1.5 rounded-lg text-[11px] font-bold uppercase tracking-wider whitespace-nowrap transition-all bg-navy text-white shadow-sm font-sans cursor-pointer">
              Kõik <span class="opacity-80" data-cat-count="all">(945)</span>
            </button>
            <button type="button" data-filter="esindus" class="filter-btn px-3 py-1.5 rounded-lg text-[11px] font-bold uppercase tracking-wider whitespace-nowrap transition-all bg-slate-50 hairline-border text-slate-600 hover:text-crimson hover:border-crimson font-sans cursor-pointer">
              Esindusmeeskond <span class="opacity-75" data-cat-count="esindus"></span>
            </button>
            <button type="button" data-filter="naiskond" class="filter-btn px-3 py-1.5 rounded-lg text-[11px] font-bold uppercase tracking-wider whitespace-nowrap transition-all bg-slate-50 hairline-border text-slate-600 hover:text-crimson hover:border-crimson font-sans cursor-pointer">
              Linnanaiskond <span class="opacity-75" data-cat-count="naiskond"></span>
            </button>
            <button type="button" data-filter="noored" class="filter-btn px-3 py-1.5 rounded-lg text-[11px] font-bold uppercase tracking-wider whitespace-nowrap transition-all bg-slate-50 hairline-border text-slate-600 hover:text-crimson hover:border-crimson font-sans cursor-pointer">
              Noored & Akadeemia <span class="opacity-75" data-cat-count="noored"></span>
            </button>
            <button type="button" data-filter="eurosari" class="filter-btn px-3 py-1.5 rounded-lg text-[11px] font-bold uppercase tracking-wider whitespace-nowrap transition-all bg-slate-50 hairline-border text-slate-600 hover:text-crimson hover:border-crimson font-sans cursor-pointer">
              Eurosari & Mängupäev <span class="opacity-75" data-cat-count="eurosari"></span>
            </button>
            <button type="button" data-filter="kogukond" class="filter-btn px-3 py-1.5 rounded-lg text-[11px] font-bold uppercase tracking-wider whitespace-nowrap transition-all bg-slate-50 hairline-border text-slate-600 hover:text-crimson hover:border-crimson font-sans cursor-pointer">
              Kogukond <span class="opacity-75" data-cat-count="kogukond"></span>
            </button>
          </div>

          <!-- Live Search Input -->
          <div class="relative w-full lg:w-64 xl:w-72 flex-shrink-0">
            <svg class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" 
                   id="news-search" 
                   placeholder="Otsi 945 artikli hulgast (2016–2026)..." 
                   class="w-full pl-8 pr-7 py-1.5 text-xs rounded-lg bg-slate-50 border border-slate-200 focus:outline-none focus:border-crimson focus:ring-1 focus:ring-crimson transition-all font-sans">
            <button type="button" id="clear-search-btn" class="hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-crimson text-xs font-bold cursor-pointer" title="Tühjenda otsing">✕</button>
          </div>

        </div>

        <!-- Bottom Row: Year Filter Archive Bar (2016–2026) -->
        <div class="pt-3 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center gap-2">
          <span class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400 font-sans flex-shrink-0 flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 text-crimson" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <span>Aasta:</span>
          </span>
          <div class="flex flex-wrap items-center gap-1.5" id="year-tabs">
            <button type="button" data-year="all" class="year-btn px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider transition-all bg-crimson text-white shadow-sm font-sans cursor-pointer">
              Kõik aastad <span class="opacity-85" data-year-count="all">(945)</span>
            </button>
            <button type="button" data-year="2026" class="year-btn px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider transition-all bg-slate-50 hairline-border text-slate-600 hover:text-crimson hover:border-crimson font-sans cursor-pointer">
              2026 <span class="opacity-70 text-[10px]" data-year-count="2026"></span>
            </button>
            <button type="button" data-year="2025" class="year-btn px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider transition-all bg-slate-50 hairline-border text-slate-600 hover:text-crimson hover:border-crimson font-sans cursor-pointer">
              2025 <span class="opacity-70 text-[10px]" data-year-count="2025"></span>
            </button>
            <button type="button" data-year="2024" class="year-btn px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider transition-all bg-slate-50 hairline-border text-slate-600 hover:text-crimson hover:border-crimson font-sans cursor-pointer">
              2024 <span class="opacity-70 text-[10px]" data-year-count="2024"></span>
            </button>
            <button type="button" data-year="2023" class="year-btn px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider transition-all bg-slate-50 hairline-border text-slate-600 hover:text-crimson hover:border-crimson font-sans cursor-pointer">
              2023 <span class="opacity-70 text-[10px]" data-year-count="2023"></span>
            </button>
            <button type="button" data-year="2022" class="year-btn px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider transition-all bg-slate-50 hairline-border text-slate-600 hover:text-crimson hover:border-crimson font-sans cursor-pointer">
              2022 <span class="opacity-70 text-[10px]" data-year-count="2022"></span>
            </button>
            <button type="button" data-year="2021" class="year-btn px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider transition-all bg-slate-50 hairline-border text-slate-600 hover:text-crimson hover:border-crimson font-sans cursor-pointer">
              2021 <span class="opacity-70 text-[10px]" data-year-count="2021"></span>
            </button>
            <button type="button" data-year="2020" class="year-btn px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider transition-all bg-slate-50 hairline-border text-slate-600 hover:text-crimson hover:border-crimson font-sans cursor-pointer">
              2020 <span class="opacity-70 text-[10px]" data-year-count="2020"></span>
            </button>
            <button type="button" data-year="2019" class="year-btn px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider transition-all bg-slate-50 hairline-border text-slate-600 hover:text-crimson hover:border-crimson font-sans cursor-pointer">
              2019 <span class="opacity-70 text-[10px]" data-year-count="2019"></span>
            </button>
            <button type="button" data-year="2018" class="year-btn px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider transition-all bg-slate-50 hairline-border text-slate-600 hover:text-crimson hover:border-crimson font-sans cursor-pointer">
              2018 <span class="opacity-70 text-[10px]" data-year-count="2018"></span>
            </button>
            <button type="button" data-year="2017" class="year-btn px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider transition-all bg-slate-50 hairline-border text-slate-600 hover:text-crimson hover:border-crimson font-sans cursor-pointer">
              2017 <span class="opacity-70 text-[10px]" data-year-count="2017"></span>
            </button>
            <button type="button" data-year="2016" class="year-btn px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider transition-all bg-slate-50 hairline-border text-slate-600 hover:text-crimson hover:border-crimson font-sans cursor-pointer">
              2016 <span class="opacity-70 text-[10px]" data-year-count="2016"></span>
            </button>
          </div>
        </div>

      </div>

      <!-- Live Counter Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between text-xs text-slate-500 font-sans px-2 mt-3 gap-1">
        <span id="news-counter">Näidatakse <strong>1–24</strong> / <strong>945</strong> artiklist</span>
        <span class="text-slate-400">Järjestatud avaldamiskuupäeva järgi (uuemad eespool • 2016–2026)</span>
      </div>
    </section>

    <!-- News Grid Container -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-12 sm:mb-16">
      
      <!-- Responsive 3-Column Grid -->
      <div id="news-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8 items-stretch">
{initial_grid_html}
      </div>

      <!-- Pagination Controls -->
      <div id="pagination-wrap" class="mt-10 sm:mt-12 flex flex-col items-center justify-between gap-4 bg-white rounded-2xl hairline-border shadow-card px-4 py-4 sm:px-6">
        <div class="flex flex-wrap items-center justify-center gap-1.5" id="pagination-buttons"></div>
        <div class="text-xs text-slate-500 font-sans" id="pagination-info"></div>
      </div>

      <!-- No Results State (Hidden by default) -->
      <div id="no-results" class="hidden text-center py-20 bg-white rounded-3xl hairline-border p-8 my-8">
        <div class="w-16 h-16 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <h3 class="font-display font-bold text-xl text-navy uppercase mb-2">Ühtegi artiklit ei leitud</h3>
        <p class="text-slate-500 text-xs sm:text-sm font-sans max-w-md mx-auto mb-6">
          Sinu otsingule vastavaid uudiseid ei leitud. Proovi teistsugust märksõna või vali mõni muu aasta või kategooria.
        </p>
        <button type="button" id="reset-filter-btn" class="px-5 py-2.5 rounded-xl bg-navy text-white text-xs font-bold uppercase tracking-wider hover:bg-crimson transition-colors cursor-pointer">
          Näita kõiki uudiseid
        </button>
      </div>

    </section>

    <!-- Community & Newsletter Banner -->
    <section class="bg-navy text-white py-14 border-t border-navy-800">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
          
          <div class="lg:col-span-7">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-crimson text-white mb-3 font-sans">
              PAIDE LINNAMEESKOND
            </span>
            <h2 class="font-display font-black text-2xl sm:text-4xl uppercase tracking-tight leading-tight mb-3">
              Ära maga ühtegi klubiuudist maha!
            </h2>
            <p class="text-slate-300 text-sm leading-relaxed font-sans max-w-xl">
              Liitu Paide Linnameeskonna fännikogukonnaga, saa esimesena infot piletite müügi, koosseisude, üleminekute ning mängupäeva fännitranspordi kohta.
            </p>
          </div>

          <div class="lg:col-span-5 flex flex-col sm:flex-row gap-3">
            <a href="https://www.facebook.com/paidelinnameeskond" target="_blank" rel="noopener noreferrer" class="flex-1 py-3.5 px-5 rounded-xl bg-crimson hover:bg-crimson-hover text-white text-center font-bold text-xs uppercase tracking-wider transition-all shadow-md hover:shadow-glow-red flex items-center justify-center gap-2">
              <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
              <span>Jälgi Facebookis</span>
            </a>
            <a href="https://www.instagram.com/paidelinnameeskond" target="_blank" rel="noopener noreferrer" class="flex-1 py-3.5 px-5 rounded-xl bg-navy-800 hover:bg-slate-800 border border-slate-700 text-white text-center font-bold text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-2">
              <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
              <span>Jälgi Instagramis</span>
            </a>
          </div>

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
            <img src="assets/img/jalgpall_logos/paide.png" alt="Paide Linnameeskond Vapp" class="w-10 h-12 object-contain">
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
            <li><a href="index.html#teams" class="hover:text-crimson-400 transition-colors">Esindusmeeskond</a></li>
            <li><a href="index.html#women-teams" class="hover:text-crimson-400 transition-colors">Naiskond</a></li>
            <li><a href="noortetoo.html" class="hover:text-crimson-400 transition-colors">Noortetöö & Jalgpallikool</a></li>
            <li class="hidden"><a href="akadeemia.html" class="hover:text-crimson-400 transition-colors">Jalgpalliakadeemia</a></li>
            <li><a href="index.html#teams" class="hover:text-crimson-400 transition-colors">Paide LM III</a></li>
            <li><a href="index.html#match-hub" class="hover:text-crimson-400 transition-colors">Mängukalender</a></li>
          </ul>
        </div>

        <!-- Column 3: Klubi & Teenused -->
        <div class="w-full text-left">
          <h4 class="text-base font-bold text-white uppercase tracking-wider mb-4 border-l-2 border-crimson pl-2.5 font-sans">
            KLUBI
          </h4>
          <ul class="space-y-2.5 text-sm leading-relaxed text-gray-300 font-sans">
            <li><a href="uudised.html" class="hover:text-crimson-400 transition-colors">Uudised ja artiklid</a></li>
            <li><a href="index.html#paide-tv" class="hover:text-crimson-400 transition-colors">Paide TV videod</a></li>
            <li class="hidden"><a href="akadeemia.html#sisseastumine" class="hover:text-crimson-400 transition-colors">Akadeemia vastuvõtt</a></li>
            <li><a href="noortetoo.html#registreeru" class="hover:text-crimson-400 transition-colors">Lapse registreerimine</a></li>
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
          <a href="privaatsuspoliitika.html" class="hover:text-white transition-colors">Privaatsuspoliitika</a>
          <span>•</span>
          <span>Jalgpall Südames</span>
        </div>
      </div>

    </div>
  </footer>

  <!-- Full 2016–2026 Archive Data & Pagination/Filter Controller -->
  <script src="assets/js/news-archive-data.js"></script>
  <script>
    (function() {{
      const archive = window.PLM_NEWS_ARCHIVE || [];
      if (!archive.length) return;

      const PAGE_SIZE = 24;
      const grid = document.getElementById('news-grid');
      const counter = document.getElementById('news-counter');
      const noResults = document.getElementById('no-results');
      const paginationWrap = document.getElementById('pagination-wrap');
      const paginationBtns = document.getElementById('pagination-buttons');
      const paginationInfo = document.getElementById('pagination-info');
      const searchInput = document.getElementById('news-search');
      const clearSearchBtn = document.getElementById('clear-search-btn');
      const resetBtn = document.getElementById('reset-filter-btn');
      const filterBtns = document.querySelectorAll('.filter-btn');
      const yearBtns = document.querySelectorAll('.year-btn');

      // Read initial state from URL params (?year=2025&cat=esindus&page=2)
      const params = new URLSearchParams(window.location.search);
      let activeCategory = params.get('cat') || 'all';
      let activeYear = params.get('year') || 'all';
      let searchQuery = (params.get('q') || '').trim().toLowerCase();
      let currentPage = Math.max(1, parseInt(params.get('page') || '1', 10) || 1);

      if (searchInput && searchQuery) {{
        searchInput.value = searchQuery;
        if (clearSearchBtn) clearSearchBtn.classList.remove('hidden');
      }}

      function escapeHtml(str) {{
        return String(str || '')
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;');
      }}

      function syncTabStyles() {{
        filterBtns.forEach(b => {{
          const isAct = b.getAttribute('data-filter') === activeCategory;
          b.classList.toggle('bg-navy', isAct);
          b.classList.toggle('text-white', isAct);
          b.classList.toggle('shadow-sm', isAct);
          b.classList.toggle('bg-slate-50', !isAct);
          b.classList.toggle('text-slate-600', !isAct);
          b.classList.toggle('hairline-border', !isAct);
        }});
        yearBtns.forEach(b => {{
          const isAct = b.getAttribute('data-year') === activeYear;
          b.classList.toggle('bg-crimson', isAct);
          b.classList.toggle('text-white', isAct);
          b.classList.toggle('shadow-sm', isAct);
          b.classList.toggle('bg-slate-50', !isAct);
          b.classList.toggle('text-slate-600', !isAct);
          b.classList.toggle('hairline-border', !isAct);
        }});
      }}

      function updateCounts() {{
        const catCounts = {{ all: 0, esindus: 0, naiskond: 0, noored: 0, eurosari: 0, kogukond: 0 }};
        const yearCounts = {{ all: 0 }};
        for (let y = 2016; y <= 2026; y++) yearCounts[String(y)] = 0;

        archive.forEach(item => {{
          const matchesSearch = !searchQuery || item.search.includes(searchQuery);
          if (!matchesSearch) return;

          if (activeYear === 'all' || item.year === activeYear) {{
            catCounts.all++;
            if (catCounts[item.cat] !== undefined) catCounts[item.cat]++;
          }}
          if (activeCategory === 'all' || item.cat === activeCategory) {{
            yearCounts.all++;
            if (yearCounts[item.year] !== undefined) yearCounts[item.year]++;
          }}
        }});

        document.querySelectorAll('[data-cat-count]').forEach(el => {{
          const k = el.getAttribute('data-cat-count');
          el.textContent = `(${{catCounts[k] || 0}})`;
        }});
        document.querySelectorAll('[data-year-count]').forEach(el => {{
          const y = el.getAttribute('data-year-count');
          el.textContent = `(${{yearCounts[y] || 0}})`;
        }});
      }}

      function renderCard(item) {{
        const basename = (item.img || '').split('/').pop();
        const newsAlt = `assets/img/news/${{basename}}`;
        const fallback = item.fallbackImg || 'assets/img/photos/main_team_article.jpeg';
        const onerrorJs = `if(!this.dataset.triedNews){{this.dataset.triedNews='1';this.src='${{newsAlt}}';}}else{{this.onerror=null;this.src='${{fallback}}';}}`;

        return `
        <article class="news-item flex">
          <a href="${{escapeHtml(item.url)}}" class="bg-white rounded-2xl hairline-border shadow-card hover:shadow-elevated hover:border-slate-300 transition-all duration-300 overflow-hidden editorial-card flex flex-col group w-full cursor-pointer">
            <div class="relative h-48 sm:h-52 overflow-hidden bg-slate-900 flex items-center justify-center">
              <img src="${{escapeHtml(item.img)}}"
                   onerror="${{onerrorJs}}"
                   loading="lazy"
                   alt="${{escapeHtml(item.title)}}"
                   class="w-full h-full object-cover object-center transform group-hover:scale-105 transition-transform duration-500">
              <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
              <div class="absolute top-3 left-3 flex items-center gap-1.5">
                <span class="px-2.5 py-1 rounded ${{escapeHtml(item.badgeClass)}} text-[10px] font-extrabold uppercase font-sans shadow-sm">
                  ${{escapeHtml(item.catLabel)}}
                </span>
              </div>
            </div>

            <div class="p-5 sm:p-6 flex-1 flex flex-col">
              <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5 font-sans flex items-center gap-2">
                <span>${{escapeHtml(item.dateDisplay)}}</span>
                <span>•</span>
                <span>${{item.readTime}} MIN</span>
              </div>
              <h3 class="font-display font-black text-xl text-navy uppercase leading-snug group-hover:text-crimson transition-colors line-clamp-2">
                ${{escapeHtml(item.title)}}
              </h3>
              <p class="text-slate-600 text-xs sm:text-sm mt-2.5 line-clamp-3 leading-relaxed font-sans">
                ${{escapeHtml(item.excerpt)}}
              </p>
            </div>
          </a>
        </article>`;
      }}

      function renderPagination(totalItems, totalPages) {{
        if (!paginationWrap || !paginationBtns || !paginationInfo) return;
        if (totalItems === 0 || totalPages <= 1) {{
          paginationWrap.classList.add('hidden');
          return;
        }}
        paginationWrap.classList.remove('hidden');

        let btnsHtml = '';
        const prevDisabled = currentPage <= 1;
        btnsHtml += `<button type="button" data-page="${{currentPage - 1}}" ${{prevDisabled ? 'disabled' : ''}} class="px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wider font-sans transition-all ${{prevDisabled ? 'bg-slate-100 text-slate-300 cursor-not-allowed' : 'bg-slate-50 hairline-border text-navy hover:bg-crimson hover:text-white cursor-pointer'}}">&larr; Eelmine</button>`;

        const pages = [];
        for (let p = 1; p <= totalPages; p++) {{
          if (p === 1 || p === totalPages || (p >= currentPage - 2 && p <= currentPage + 2)) {{
            pages.push(p);
          }} else if (pages[pages.length - 1] !== '...') {{
            pages.push('...');
          }}
        }}

        pages.forEach(p => {{
          if (p === '...') {{
            btnsHtml += `<span class="px-2 py-1 text-xs font-bold text-slate-400 font-sans">…</span>`;
          }} else {{
            const isCurr = p === currentPage;
            btnsHtml += `<button type="button" data-page="${{p}}" class="w-8 h-8 rounded-lg text-xs font-bold font-sans transition-all ${{isCurr ? 'bg-navy text-white shadow-sm' : 'bg-slate-50 hairline-border text-slate-700 hover:text-crimson hover:border-crimson cursor-pointer'}}">${{p}}</button>`;
          }}
        }});

        const nextDisabled = currentPage >= totalPages;
        btnsHtml += `<button type="button" data-page="${{currentPage + 1}}" ${{nextDisabled ? 'disabled' : ''}} class="px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wider font-sans transition-all ${{nextDisabled ? 'bg-slate-100 text-slate-300 cursor-not-allowed' : 'bg-slate-50 hairline-border text-navy hover:bg-crimson hover:text-white cursor-pointer'}}">Järgmine &rarr;</button>`;

        paginationBtns.innerHTML = btnsHtml;
        paginationInfo.innerHTML = `Lehekülg <strong>${{currentPage}}</strong> / <strong>${{totalPages}}</strong> • Kokku <strong>${{totalItems}}</strong> artiklit`;

        paginationBtns.querySelectorAll('button[data-page]').forEach(b => {{
          b.addEventListener('click', () => {{
            const targetPage = parseInt(b.getAttribute('data-page'), 10);
            if (!targetPage || targetPage === currentPage || targetPage < 1 || targetPage > totalPages) return;
            currentPage = targetPage;
            applyFilters(true);
          }});
        }});
      }}

      function updateUrlState() {{
        if (window.location.protocol === 'file:') return;
        const nextParams = new URLSearchParams();
        if (activeYear !== 'all') nextParams.set('year', activeYear);
        if (activeCategory !== 'all') nextParams.set('cat', activeCategory);
        if (searchQuery) nextParams.set('q', searchQuery);
        if (currentPage > 1) nextParams.set('page', String(currentPage));
        const qs = nextParams.toString();
        const newUrl = window.location.pathname + (qs ? '?' + qs : '');
        window.history.replaceState(null, '', newUrl);
      }}

      function applyFilters(scrollToTop) {{
        syncTabStyles();
        updateCounts();

        const filtered = archive.filter(item => {{
          const matchCat = activeCategory === 'all' || item.cat === activeCategory;
          const matchYear = activeYear === 'all' || item.year === activeYear;
          const matchSearch = !searchQuery || item.search.includes(searchQuery);
          return matchCat && matchYear && matchSearch;
        }});

        const totalItems = filtered.length;
        const totalPages = Math.max(1, Math.ceil(totalItems / PAGE_SIZE));
        if (currentPage > totalPages) currentPage = totalPages;

        const startIdx = (currentPage - 1) * PAGE_SIZE;
        const pageItems = filtered.slice(startIdx, startIdx + PAGE_SIZE);

        if (grid) {{
          grid.innerHTML = pageItems.map(renderCard).join('');
        }}

        if (counter) {{
          if (totalItems === 0) {{
            counter.innerHTML = `Artikleid ei leitud`;
          }} else {{
            const endIdx = Math.min(startIdx + PAGE_SIZE, totalItems);
            const yearLabel = activeYear === 'all' ? '2016–2026' : `Aasta ${{activeYear}}`;
            counter.innerHTML = `Näidatakse <strong>${{startIdx + 1}}–${{endIdx}}</strong> / <strong>${{totalItems}}</strong> artiklist (${{yearLabel}})`;
          }}
        }}

        if (noResults) {{
          noResults.classList.toggle('hidden', totalItems > 0);
        }}

        renderPagination(totalItems, totalPages);
        updateUrlState();

        if (scrollToTop) {{
          const controlsEl = document.getElementById('archive-controls');
          if (controlsEl) {{
            controlsEl.scrollIntoView({{ behavior: 'smooth', block: 'start' }});
          }}
        }}
      }}

      filterBtns.forEach(btn => {{
        btn.addEventListener('click', () => {{
          activeCategory = btn.getAttribute('data-filter');
          currentPage = 1;
          applyFilters(false);
        }});
      }});

      yearBtns.forEach(btn => {{
        btn.addEventListener('click', () => {{
          activeYear = btn.getAttribute('data-year');
          currentPage = 1;
          applyFilters(false);
        }});
      }});

      if (searchInput) {{
        searchInput.addEventListener('input', e => {{
          searchQuery = e.target.value.trim().toLowerCase();
          if (clearSearchBtn) clearSearchBtn.classList.toggle('hidden', !searchQuery);
          currentPage = 1;
          applyFilters(false);
        }});
      }}

      if (clearSearchBtn) {{
        clearSearchBtn.addEventListener('click', () => {{
          searchQuery = '';
          if (searchInput) searchInput.value = '';
          clearSearchBtn.classList.add('hidden');
          currentPage = 1;
          applyFilters(false);
        }});
      }}

      if (resetBtn) {{
        resetBtn.addEventListener('click', () => {{
          activeCategory = 'all';
          activeYear = 'all';
          searchQuery = '';
          currentPage = 1;
          if (searchInput) searchInput.value = '';
          if (clearSearchBtn) clearSearchBtn.classList.add('hidden');
          applyFilters(false);
        }});
      }}

      applyFilters(false);
    }})();
  </script>

"""

updated_txt = txt[: txt.index(start_marker)] + new_section + txt[txt.index(end_marker) :]
with open("uudised.html", "w", encoding="utf-8") as f:
    f.write(updated_txt)
print("Successfully updated uudised.html!")
