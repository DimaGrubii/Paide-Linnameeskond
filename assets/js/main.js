/**
 * Paide Linnameeskond - Official Prototype JavaScript
 * High performance vanilla JS for interactive modules
 */

document.addEventListener('DOMContentLoaded', () => {
  initStickyHeader();
  initMobileDrawer();
  initStandingsTabs();
  initStandingsExpand();
  initMatchdayToggle();
  initHeroStories();
  initNewsFilters();
  initVideoModal();
  initVideoFilters();
  initTicketModal();
  initLanguageSwitcher();
  initNewsletterForms();
});

/* -------------------------------------------------------------------------- */
/* 1. STICKY HEADER & SCROLL BEHAVIOR                                         */
/* -------------------------------------------------------------------------- */
function initStickyHeader() {
  const header = document.getElementById('main-header');
  if (!header) return;

  const onScroll = () => {
    if (window.scrollY > 30) {
      header.classList.add('shadow-md', 'py-2');
      header.classList.remove('py-2.5', 'lg:py-3');
    } else {
      header.classList.remove('shadow-md', 'py-2');
      header.classList.add('py-2.5', 'lg:py-3');
    }
  };

  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
}

/* -------------------------------------------------------------------------- */
/* 2. MOBILE DRAWER NAVIGATION                                                */
/* -------------------------------------------------------------------------- */
function openMobileDrawer() {
  const drawer = document.getElementById('mobile-drawer');
  const panel = document.getElementById('drawer-panel');
  const backdrop = document.getElementById('drawer-backdrop');
  const openBtn = document.getElementById('mobile-menu-btn');
  if (!drawer) return;

  drawer.classList.remove('hidden');
  drawer.classList.add('drawer-open');
  void drawer.offsetWidth;
  if (panel) {
    panel.classList.remove('translate-x-full');
    panel.classList.add('translate-x-0');
  }
  if (backdrop) {
    backdrop.classList.remove('opacity-0');
    backdrop.classList.add('opacity-100');
  }
  document.body.classList.add('overflow-hidden');
  if (openBtn) openBtn.setAttribute('aria-expanded', 'true');
}

function closeMobileDrawer() {
  const drawer = document.getElementById('mobile-drawer');
  const panel = document.getElementById('drawer-panel');
  const backdrop = document.getElementById('drawer-backdrop');
  const openBtn = document.getElementById('mobile-menu-btn');
  if (!drawer) return;

  drawer.classList.remove('drawer-open');
  if (panel) {
    panel.classList.remove('translate-x-0');
    panel.classList.add('translate-x-full');
  }
  if (backdrop) {
    backdrop.classList.remove('opacity-100');
    backdrop.classList.add('opacity-0');
  }
  document.body.classList.remove('overflow-hidden');
  if (openBtn) openBtn.setAttribute('aria-expanded', 'false');

  setTimeout(() => {
    if (!drawer.classList.contains('drawer-open')) {
      drawer.classList.add('hidden');
    }
  }, 300);
}

function toggleMobileDrawer() {
  const drawer = document.getElementById('mobile-drawer');
  if (!drawer) return;
  if (drawer.classList.contains('hidden') || !drawer.classList.contains('drawer-open')) {
    openMobileDrawer();
  } else {
    closeMobileDrawer();
  }
}

window.openMobileDrawer = openMobileDrawer;
window.closeMobileDrawer = closeMobileDrawer;
window.toggleMobileDrawer = toggleMobileDrawer;

function initMobileDrawer() {
  const openBtn = document.getElementById('mobile-menu-btn');
  const closeBtn = document.getElementById('drawer-close-btn');
  const drawer = document.getElementById('mobile-drawer');
  const backdrop = document.getElementById('drawer-backdrop');

  if (openBtn) {
    openBtn.addEventListener('click', (e) => {
      e.preventDefault();
      openMobileDrawer();
    });
    openBtn.addEventListener('touchend', (e) => {
      e.preventDefault();
      openMobileDrawer();
    }, { passive: false });
  }

  if (closeBtn) {
    closeBtn.addEventListener('click', (e) => {
      e.preventDefault();
      closeMobileDrawer();
    });
  }

  if (backdrop) {
    backdrop.addEventListener('click', (e) => {
      e.preventDefault();
      closeMobileDrawer();
    });
  }

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && drawer && !drawer.classList.contains('hidden')) {
      closeMobileDrawer();
    }
  });
}

/* -------------------------------------------------------------------------- */
/* 3. LEAGUE STANDINGS HUB (DUAL TABLE TABS)                                   */
/* -------------------------------------------------------------------------- */
/* 3. LEAGUE STANDINGS TABS (MEN VS WOMEN)                                    */
/* -------------------------------------------------------------------------- */
function updateBestPlayer(gender) {
  const card = document.getElementById('stats-best-player-card');
  const label = document.getElementById('stats-best-player-label');
  const league = document.getElementById('stats-best-player-league');
  const name = document.getElementById('stats-best-player-name') || document.getElementById('stats-top-scorer-name');
  const role = document.getElementById('stats-best-player-role');
  const photo = document.getElementById('stats-best-player-photo');
  const number = document.getElementById('stats-best-player-number') || document.getElementById('stats-top-scorer-number');
  const goals = document.getElementById('stats-best-player-goals') || document.getElementById('stats-top-scorer-goals');
  const assists = document.getElementById('stats-best-player-assists');

  if (!card) return;

  card.style.opacity = '0.35';
  card.style.transform = 'translateY(2px)';

  setTimeout(() => {
    if (gender === 'women') {
      if (label) label.textContent = 'NAISKONNA PARIM';
      if (league) league.textContent = 'NAISTE MEISTRILIIGA 2026';
      if (name) name.textContent = 'KIRKELIIS LILLEMETS';
      if (role) role.textContent = 'Ründaja • Paide Linnanaiskond';
      if (photo) {
        photo.src = 'assets/img/players/women/kirkeliis_lillemets.jpg';
        photo.alt = 'Kirkeliis Lillemets';
      }
      if (number) number.textContent = '#9';
      if (goals) {
        goals.innerHTML = '20 <span class="text-[10px] font-medium text-slate-400 uppercase">väravat</span>';
        goals.title = '20 väravat (16 liigas + 4 karikas)';
      }
      if (assists) {
        assists.innerHTML = '7 <span class="text-[10px] font-medium text-slate-400 uppercase">söötu</span>';
        assists.title = '7 väravasöötu';
      }
    } else {
      if (label) label.textContent = 'MEESKONNA PARIM';
      if (league) league.textContent = 'PREMIUM LIIGA 2026';
      if (name) name.textContent = 'HENRI ANIER';
      if (role) role.textContent = 'Ründaja • Esindusmeeskond';
      if (photo) {
        photo.src = 'assets/img/players/anier.jpeg';
        photo.alt = 'Henri Anier';
      }
      if (number) number.textContent = '#9';
      if (goals) {
        goals.innerHTML = '11 <span class="text-[10px] font-medium text-slate-400 uppercase">väravat</span>';
        goals.title = '11 väravat (10 liigas + 1 karikas)';
      }
      if (assists) {
        assists.innerHTML = '6 <span class="text-[10px] font-medium text-slate-400 uppercase">söötu</span>';
        assists.title = '6 väravasöötu';
      }
    }
    card.style.opacity = '1';
    card.style.transform = 'translateY(0)';
  }, 120);
}

function updateTrackerCard(gender) {
  const card = document.getElementById('standings-tracker-card');
  const badge = document.getElementById('standings-tracker-badge');
  const season = document.getElementById('standings-tracker-season');
  const title = document.getElementById('standings-tracker-title');
  const desc = document.getElementById('standings-tracker-desc');
  const gapLabel = document.getElementById('standings-tracker-gap-label');
  const gapVal = document.getElementById('standings-tracker-gap-val');
  const progress = document.getElementById('standings-tracker-progress');
  const played = document.getElementById('standings-tracker-played');

  if (!card) return;

  card.style.opacity = '0.4';

  setTimeout(() => {
    if (gender === 'women') {
      if (badge) badge.textContent = 'NAISTE MEISTRILIIGA';
      if (season) season.textContent = 'HOOAEG 2026';
      if (title) title.textContent = 'VÕITLUS MEISTRITIITLI NIMEL';
      if (desc) desc.textContent = 'Paide Linnanaiskond alistas kodus FC Elva 4:0 ning on 48 punktiga liidri Tallinna FC Floraga samal pulgal 2. positsioonil, võideldes meistritiitli nimel!';
      if (gapLabel) gapLabel.textContent = 'Edu 3. koha ees (Saku):';
      if (gapVal) gapVal.textContent = '+7 punkti';
      if (progress) progress.style.width = '92.0%';
      if (played) played.textContent = 'Mängitud 23/25 vooru';
    } else {
      if (badge) badge.textContent = 'UEFA CONFERENCE LEAGUE';
      if (season) season.textContent = 'HOOAEG 2026';
      if (title) title.textContent = 'EUROSARJA PÄÄSE';
      if (desc) desc.textContent = 'Paide tõusis pärast 1:0 võitu FC Kuressaare üle Premium Liigas 60 punktiga 2. positsioonile, edestades Tallinna FC Florat 2 punktiga ja püsides kindlal kursil UEFA Konverentsiliigasse!';
      if (gapLabel) gapLabel.textContent = 'Edu 4. koha ees (Kalju):';
      if (gapVal) gapVal.textContent = '+6 punkti';
      if (progress) progress.style.width = '91.7%';
      if (played) played.textContent = 'Mängitud 33/36 vooru';
    }
    card.style.opacity = '1';
  }, 120);
}

function initStandingsTabs() {
  const menTab = document.getElementById('tab-standings-men');
  const womenTab = document.getElementById('tab-standings-women');
  const menTable = document.getElementById('table-standings-men');
  const womenTable = document.getElementById('table-standings-women');
  const competitionTitle = document.getElementById('standings-competition-title');
  const competitionLogo = document.getElementById('standings-competition-logo');
  const expandBtn = document.getElementById('standings-expand-btn');

  if (!menTab || !womenTab || !menTable || !womenTable) return;

  const setActiveTab = (selected) => {
    if (selected === 'men') {
      menTab.classList.add('bg-navy', 'text-white', 'shadow-sm');
      menTab.classList.remove('text-slate-600', 'hover:text-navy', 'hover:bg-slate-100');
      womenTab.classList.remove('bg-navy', 'text-white', 'shadow-sm');
      womenTab.classList.add('text-slate-600', 'hover:text-navy', 'hover:bg-slate-100');

      menTable.classList.remove('hidden');
      womenTable.classList.add('hidden');
      if (competitionTitle) competitionTitle.textContent = 'A. Le Coq Premium Liiga 2026';
      if (competitionLogo) {
        competitionLogo.src = 'assets/img/sponsors/premiumliiga.svg';
        competitionLogo.alt = 'A. Le Coq Premium Liiga';
      }
      updateBestPlayer('men');
      updateTrackerCard('men');
    } else {
      womenTab.classList.add('bg-navy', 'text-white', 'shadow-sm');
      womenTab.classList.remove('text-slate-600', 'hover:text-navy', 'hover:bg-slate-100');
      menTab.classList.remove('bg-navy', 'text-white', 'shadow-sm');
      menTab.classList.add('text-slate-600', 'hover:text-navy', 'hover:bg-slate-100');

      womenTable.classList.remove('hidden');
      menTable.classList.add('hidden');
      if (competitionTitle) competitionTitle.textContent = 'Naiste Meistriliiga 2026';
      if (competitionLogo) {
        competitionLogo.src = 'assets/img/sponsors/naisteliiga.svg';
        competitionLogo.alt = 'Naiste Meistriliiga';
      }
      updateBestPlayer('women');
      updateTrackerCard('women');
    }

    // Reset expander state when switching tabs
    if (expandBtn) {
      expandBtn.setAttribute('data-expanded', 'false');
      expandBtn.querySelector('.btn-text').textContent = 'Vaata täispikka tabelit';
      expandBtn.querySelector('.btn-icon').classList.remove('rotate-180');
      resetTableRows(menTable);
      resetTableRows(womenTable);
    }
  };

  menTab.addEventListener('click', () => setActiveTab('men'));
  womenTab.addEventListener('click', () => setActiveTab('women'));
}

/* -------------------------------------------------------------------------- */
/* 4. STANDINGS EXPAND / COLLAPSE                                             */
/* -------------------------------------------------------------------------- */
function initStandingsExpand() {
  initSeasonHistoryGraph();

  const expandBtn = document.getElementById('standings-expand-btn');
  if (!expandBtn) return;

  expandBtn.addEventListener('click', () => {
    const isExpanded = expandBtn.getAttribute('data-expanded') === 'true';
    const activeTable = document.querySelector('#table-standings-men:not(.hidden), #table-standings-women:not(.hidden)');
    if (!activeTable) return;

    const extraRows = activeTable.querySelectorAll('.standings-extra-row');
    const btnText = expandBtn.querySelector('.btn-text');
    const btnIcon = expandBtn.querySelector('.btn-icon');

    if (isExpanded) {
      extraRows.forEach(row => {
        row.classList.add('hidden');
        const toggleBtn = row.querySelector('[data-season-toggle]');
        if (toggleBtn) {
          const key = toggleBtn.getAttribute('data-season-toggle');
          const historyRow = activeTable.querySelector(`[data-season-row="${key}"]`);
          if (historyRow) historyRow.classList.add('hidden');
          toggleBtn.setAttribute('aria-expanded', 'false');
          toggleBtn.classList.remove('bg-navy', 'text-white', 'border-navy');
          toggleBtn.classList.add('border-slate-300', 'text-slate-500');
          const icon = toggleBtn.querySelector('.season-toggle-icon');
          if (icon) icon.classList.remove('rotate-180');
        }
      });
      expandBtn.setAttribute('data-expanded', 'false');
      if (btnText) btnText.textContent = 'Vaata täispikka tabelit';
      if (btnIcon) btnIcon.classList.remove('rotate-180');
    } else {
      extraRows.forEach(row => row.classList.remove('hidden'));
      expandBtn.setAttribute('data-expanded', 'true');
      if (btnText) btnText.textContent = 'Ahenda tabelit';
      if (btnIcon) btnIcon.classList.add('rotate-180');
    }
  });
}

function resetTableRows(table) {
  if (!table) return;
  const extraRows = table.querySelectorAll('.standings-extra-row');
  extraRows.forEach(row => row.classList.add('hidden'));
  const historyRows = table.querySelectorAll('.season-history-row');
  historyRows.forEach(row => row.classList.add('hidden'));
  const toggleBtns = table.querySelectorAll('[data-season-toggle]');
  toggleBtns.forEach(btn => {
    btn.setAttribute('aria-expanded', 'false');
    btn.classList.remove('bg-navy', 'text-white', 'border-navy');
    btn.classList.add('border-slate-300', 'text-slate-500');
    const icon = btn.querySelector('.season-toggle-icon');
    if (icon) icon.classList.remove('rotate-180');
  });
}

function initSeasonHistoryGraph() {
  const standingsSection = document.getElementById('standings');
  if (!standingsSection || standingsSection.hasAttribute('data-season-graph-bound')) return;
  standingsSection.setAttribute('data-season-graph-bound', 'true');

  standingsSection.addEventListener('click', (e) => {
    const toggleBtn = e.target.closest('[data-season-toggle]');
    if (!toggleBtn) return;
    e.preventDefault();

    const rowKey = toggleBtn.getAttribute('data-season-toggle');
    const historyRow = document.querySelector(`[data-season-row="${rowKey}"]`);
    if (!historyRow) return;

    const isOpening = historyRow.classList.contains('hidden');
    historyRow.classList.toggle('hidden', !isOpening);
    toggleBtn.setAttribute('aria-expanded', isOpening ? 'true' : 'false');

    const icon = toggleBtn.querySelector('.season-toggle-icon');
    if (icon) icon.classList.toggle('rotate-180', isOpening);

    if (isOpening) {
      toggleBtn.classList.add('bg-navy', 'text-white', 'border-navy');
      toggleBtn.classList.remove('border-slate-300', 'text-slate-500');
    } else {
      toggleBtn.classList.remove('bg-navy', 'text-white', 'border-navy');
      toggleBtn.classList.add('border-slate-300', 'text-slate-500');
    }
  });

  const updateReadoutPill = (historyRow, summary, outcome, type) => {
    const readoutText = historyRow.querySelector('.season-readout-text');
    const readoutBadge = historyRow.querySelector('.season-readout-badge');
    if (readoutText && summary) {
      readoutText.textContent = summary;
    }
    if (readoutBadge && outcome) {
      readoutBadge.textContent = outcome;
      let badgeColor = 'bg-slate-200/80 text-slate-600';
      if (type === 'win') {
        badgeColor = 'bg-emerald-100 text-emerald-800';
      } else if (type === 'draw') {
        badgeColor = 'bg-amber-100 text-amber-800';
      } else if (type === 'loss') {
        badgeColor = 'bg-rose-100 text-rose-800';
      }
      readoutBadge.className = `season-readout-badge px-1.5 py-0.5 rounded text-[9px] font-bold uppercase ${badgeColor}`;
    }
  };

  const handleRoundFocusOrHover = (e) => {
    const roundNode = e.target.closest('[data-round-node]');
    if (!roundNode) return;

    const historyRow = roundNode.closest('.season-history-row');
    if (!historyRow) return;

    const summary = roundNode.getAttribute('data-round-summary');
    const outcome = roundNode.getAttribute('data-round-outcome');
    const type = roundNode.getAttribute('data-round-type');
    updateReadoutPill(historyRow, summary, outcome, type);
  };

  const handleRoundLeave = (e) => {
    const historyRow = e.target.closest('.season-history-row');
    if (!historyRow) return;
    if (e.relatedTarget && historyRow.contains(e.relatedTarget)) return;

    let defSummary = historyRow.getAttribute('data-default-summary');
    let defOutcome = historyRow.getAttribute('data-default-outcome');
    let defType = historyRow.getAttribute('data-default-type');

    if (!defSummary) {
      const playedNodes = historyRow.querySelectorAll('[data-round-node][data-round-type="win"], [data-round-node][data-round-type="draw"], [data-round-node][data-round-type="loss"]');
      const lastNode = playedNodes.length > 0 ? playedNodes[playedNodes.length - 1] : null;
      if (lastNode) {
        defSummary = lastNode.getAttribute('data-round-summary');
        defOutcome = lastNode.getAttribute('data-round-outcome');
        defType = lastNode.getAttribute('data-round-type');
      }
    }

    if (defSummary && defOutcome) {
      updateReadoutPill(historyRow, defSummary, defOutcome, defType);
    }
  };

  standingsSection.addEventListener('mouseover', handleRoundFocusOrHover);
  standingsSection.addEventListener('focusin', handleRoundFocusOrHover);
  standingsSection.addEventListener('mouseout', handleRoundLeave);
}

/* -------------------------------------------------------------------------- */
/* 5. MATCHDAY STRIP TOGGLE (Men vs Women Next Match)                         */
/* -------------------------------------------------------------------------- */
function initMatchdayToggle() {
  const btnMen = document.getElementById('match-toggle-men') || document.getElementById('match-toggle-next');
  const btnWomen = document.getElementById('match-toggle-women') || document.getElementById('match-toggle-prev');
  const cardMen = document.getElementById('match-card-men') || document.getElementById('match-card-next');
  const cardWomen = document.getElementById('match-card-women');

  if (!btnMen || !btnWomen || !cardMen || !cardWomen) return;

  const setActive = (team) => {
    if (team === 'men') {
      btnMen.classList.add('bg-white', 'text-navy', 'shadow-sm', 'font-bold');
      btnMen.classList.remove('text-slate-600', 'hover:text-navy');
      btnWomen.classList.remove('bg-white', 'text-navy', 'shadow-sm', 'font-bold');
      btnWomen.classList.add('text-slate-600', 'hover:text-navy');

      cardMen.classList.remove('hidden');
      cardWomen.classList.add('hidden');
    } else {
      btnWomen.classList.add('bg-white', 'text-navy', 'shadow-sm', 'font-bold');
      btnWomen.classList.remove('text-slate-600', 'hover:text-navy');
      btnMen.classList.remove('bg-white', 'text-navy', 'shadow-sm', 'font-bold');
      btnMen.classList.add('text-slate-600', 'hover:text-navy');

      cardWomen.classList.remove('hidden');
      cardMen.classList.add('hidden');
    }
  };

  btnMen.addEventListener('click', () => setActive('men'));
  btnWomen.addEventListener('click', () => setActive('women'));

  // Wire up "Vaata liigatabelit" button in women's match card to switch standings tab to women
  const viewTableWomen = document.getElementById('match-view-table-women');
  if (viewTableWomen) {
    viewTableWomen.addEventListener('click', () => {
      const womenTab = document.getElementById('tab-standings-women');
      if (womenTab) womenTab.click();
    });
  }

  const viewTableMen = document.getElementById('match-view-table-men');
  if (viewTableMen) {
    viewTableMen.addEventListener('click', () => {
      const menTab = document.getElementById('tab-standings-men');
      if (menTab) menTab.click();
    });
  }
}

/* -------------------------------------------------------------------------- */
/* 6. HERO EDITORIAL STORIES SWITCHER                                         */
/* -------------------------------------------------------------------------- */
/* 6. HERO EDITORIAL STORIES SWITCHER                                         */
/* -------------------------------------------------------------------------- */
const defaultHeroStory = {
  tag: 'NAISTE MEISTRILIIGA • PAIDE 4:0 ELVA',
  title: 'LINNANAISKOND ALISTAS ELVA JA JÄTKAB MEISTRITIITLI JAHTI!',
  date: '26. OKTOOBER 2026',
  readTime: '2 MIN LUGEMIST',
  desc: 'Paide Linnanaiskond jätkas naiste Meistriliiga hooaja lõpusirgel võidulainel, kui laupäeval, 24. oktoobril alistati kodusel kunstmuruväljakul FC Elva naiskond kindlalt 4:0. Kaks väravat said kirja nii Lisandra Rannasto kui ka Katriin Rodi!',
  image: 'assets/img/news/PLN_Elva_Victory.JPEG',
  link: 'artikkel-linnanaiskond-elva-voit.html',
  btnText: 'Loe täispikka uudist'
};

const heroStories = [
  {
    tag: 'KOGUKOND & FÄNNID',
    title: 'FÄNNIBUSSIGA FINAALI VAATAMA! 🏁',
    date: '25. SEPTEMBER 2026',
    readTime: '2 MIN LUGEMIST',
    desc: 'Sõidame ühiselt suurele finaalturniirile! Paide Linnameeskonna fänniklubi korraldab fännibussi Tallinnasse A. Le Coq Arenale Väikeste karikavõistluste finaalile.',
    image: 'assets/img/photos/Fanbus_web.jpg',
    link: 'artikkel-fannibuss.html',
    btnText: 'Loe fännireisist'
  },
  {
    tag: 'ESINDUSMEESKOND • ÜLEMINEK',
    title: 'ABDOURAHMAN BADAMOSI SIIRDUS TŠEHHI KÕRGLIIGASSE',
    date: '08. SEPTEMBER 2026',
    readTime: '3 MIN LUGEMIST',
    desc: 'Paide Linnameeskonna ründaja Abdourahman Badamosi karjäär jätkub Tšehhi kõrgliigaklubis FK Teplice. Paide teenis üleminekust märkimisväärse üleminekutasu.',
    image: 'assets/img/news/badamosi-tsehhi-korgliigasse.jpeg',
    link: 'artikkel-badamosi-tsehhi-korgliigasse.html',
    btnText: 'Loe üleminekust'
  },
  {
    tag: 'NOORTEAKADEEMIA • TULE TRENNI',
    title: 'UUTE LASTE VASTUVÕTT KLUBISSE ON ALANUD!',
    date: '31. AUGUST 2026',
    readTime: '2 MIN LUGEMIST',
    desc: 'Paide Linnameeskond kutsub oma jalgpallikooli kõiki Järvamaa poisse ja tüdrukuid. Tule trenni ja saa osa meie jalgpalliperest – esimene proovikuu on kõigile tasuta!',
    image: 'assets/img/news/uute-laste-vastuvott-klubisse-on-alanud.jpg',
    link: 'artikkel-uute-laste-vastuvott.html',
    btnText: 'Loe liitumisinfot'
  },
  {
    tag: 'ESINDUSMEESKOND • UUS LIITUJA #29',
    title: 'PAIDEGA LIITUS GAMBIA RÜNDAJA DAWDA DARBOE',
    date: '21. AUGUST 2026',
    readTime: '2 MIN LUGEMIST',
    desc: 'Paide Linnameeskonnaga sõlmis mitmeaastase lepingu 21-aastane Gambia noortekoondise ründaja Dawda Darboe, kes hakkab kandma särginumbrit 29.',
    image: 'assets/img/news/paidega-liitus-gambia-rundaja-dawda-darboe.jpg',
    link: 'artikkel-dawda-darboe.html',
    btnText: 'Loe liitujast'
  }
];

function initHeroStories() {
  const heroImage = document.getElementById('hero-main-image');
  const heroTag = document.getElementById('hero-tag');
  const heroTitle = document.getElementById('hero-title');
  const heroTitleLink = document.getElementById('hero-title-link');
  const heroDesc = document.getElementById('hero-desc');
  const heroDate = document.getElementById('hero-date');
  const heroReadTime = document.getElementById('hero-read-time');
  const heroLink = document.getElementById('hero-link');
  const heroBtnText = document.getElementById('hero-btn-text');
  const storyButtons = document.querySelectorAll('.hero-story-btn');
  const asideContainer = document.querySelector('aside[aria-label="Tähtsündmused"]');

  if (!heroTitle || storyButtons.length === 0) return;

  // Pre-cache all hero images into browser memory for instant, flicker-free swapping
  [defaultHeroStory, ...heroStories].forEach(s => {
    if (s && s.image) {
      const img = new Image();
      img.src = s.image;
    }
  });

  let activeLockedIndex = null; // When clicked on a story, locks it

  function renderHero(story, activeIdx = null) {
    if (!story) return;

    // Update active button visual state
    storyButtons.forEach((b, idx) => {
      const isCurrent = (activeIdx !== null && idx === activeIdx);
      b.classList.toggle('border-crimson', isCurrent);
      b.classList.toggle('border-transparent', !isCurrent);
      const tagSpan = b.querySelector('.story-tag');
      if (tagSpan) {
        tagSpan.classList.toggle('text-crimson', isCurrent);
        tagSpan.classList.toggle('text-slate-500', !isCurrent);
      }
    });

    // Update hero visual image
    if (heroImage && heroImage.src !== story.image) {
      heroImage.src = story.image;
      heroImage.alt = story.title;
    }

    // Update text content
    if (heroTag) heroTag.textContent = story.tag;
    if (heroTitle) heroTitle.textContent = story.title;
    if (heroTitleLink) heroTitleLink.href = story.link;
    if (heroDesc) heroDesc.textContent = story.desc;
    if (heroDate) heroDate.textContent = story.date;
    if (heroReadTime) heroReadTime.textContent = story.readTime;
    if (heroLink) heroLink.href = story.link;
    if (heroBtnText) heroBtnText.textContent = story.btnText;
  }

  storyButtons.forEach((btn, index) => {
    // 1. Mouseenter: instant preview on hover
    btn.addEventListener('mouseenter', () => {
      const story = heroStories[index];
      if (story) {
        renderHero(story, index);
      }
    });

    // 2. Click: select/lock preview or navigate to article
    btn.addEventListener('click', (e) => {
      // If user clicked directly on an <a> tag inside the card, allow standard link navigation
      if (e.target.tagName.toLowerCase() === 'a' || e.target.closest('a')) {
        return;
      }

      const story = heroStories[index];
      if (!story) return;

      // On mobile view (<1024px) or if card is already locked, clicking navigates directly to the article
      if (window.innerWidth < 1024 || activeLockedIndex === index) {
        if (story.link) {
          window.location.href = story.link;
          return;
        }
      }

      // First click on desktop: lock this story as active selection
      activeLockedIndex = index;
      renderHero(story, index);
    });

    // 3. Keyboard accessibility
    btn.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        const story = heroStories[index];
        if (story) {
          if (activeLockedIndex === index) {
            window.location.href = story.link;
          } else {
            activeLockedIndex = index;
            renderHero(story, index);
          }
        }
      }
    });
  });

  // When mouse leaves the entire suggested section, revert to locked selection or default Mayo
  if (asideContainer) {
    asideContainer.addEventListener('mouseleave', () => {
      if (activeLockedIndex !== null && heroStories[activeLockedIndex]) {
        renderHero(heroStories[activeLockedIndex], activeLockedIndex);
      } else {
        renderHero(defaultHeroStory, null);
      }
    });
  }
}

/* -------------------------------------------------------------------------- */
/* 7. PAIDE TV VIDEO MODAL & FILTER SYSTEM                                    */
/* -------------------------------------------------------------------------- */
function initVideoModal() {
  const modal = document.getElementById('video-modal');
  const closeBtn = document.getElementById('video-modal-close');
  const closeFooterBtn = document.getElementById('video-modal-close-footer');
  const modalTitle = document.getElementById('video-modal-title');
  const modalTag = document.getElementById('video-modal-tag');
  const modalDesc = document.getElementById('video-modal-desc');
  const modalIframe = document.getElementById('video-modal-iframe');
  const modalExternalLink = document.getElementById('video-modal-external-link');
  const featuredModalBtn = document.getElementById('featured-video-modal-btn');

  // Main featured player elements on page
  const mainIframe = document.getElementById('main-featured-iframe');
  const featuredTitle = document.getElementById('featured-video-title');
  const featuredCategory = document.getElementById('featured-video-category');
  const featuredViews = document.getElementById('featured-video-views');
  const featuredTime = document.getElementById('featured-video-time');
  const featuredDesc = document.getElementById('featured-video-desc');
  const featuredExtLink = document.getElementById('featured-video-external-link');

  const playlistItems = document.querySelectorAll('#video-playlist-container .video-item');

  function extractInitialVideoId() {
    if (!mainIframe) return 'oWMw6sEdOg0';
    const dataId = mainIframe.getAttribute('data-video-id');
    if (dataId) return dataId;
    const src = mainIframe.getAttribute('src') || '';
    const match = src.match(/\/embed\/([^?&/]+)/);
    return match ? match[1] : 'oWMw6sEdOg0';
  }

  const initialVideoId = extractInitialVideoId();
  const defaultVideoData = {
    videoId: initialVideoId,
    title: featuredTitle ? featuredTitle.textContent.trim() : 'IIZI TALK EP2 | Jalgpalliakadeemiast ja noore teekonnast tippu',
    category: featuredCategory ? featuredCategory.textContent.trim() : 'IIZI TALK PODCAST',
    duration: '51:15',
    views: featuredViews ? featuredViews.textContent.trim() : '1.6K vaatamist',
    time: featuredTime ? featuredTime.textContent.trim() : '9 päeva tagasi',
    desc: featuredDesc ? featuredDesc.textContent.trim() : 'Klubi ametlik podcast noortetööst ja Paide Jalgpalliakadeemiast.',
    url: featuredExtLink ? featuredExtLink.getAttribute('href') : `https://www.youtube.com/watch?v=${initialVideoId}`
  };

  let currentVideoData = Object.assign({}, defaultVideoData);

  function getEmbedUrl(videoId, autoplay = false) {
    const autoParam = autoplay ? 'autoplay=1&' : '';
    if (videoId === 'oWMw6sEdOg0') {
      return `https://www.youtube.com/embed/oWMw6sEdOg0?si=bKcqwEyGmYoyri5v&${autoParam}enablejsapi=1`;
    }
    return `https://www.youtube.com/embed/${videoId}?${autoParam}enablejsapi=1`;
  }

  function selectVideo(videoData, autoPlay = true) {
    currentVideoData = Object.assign({}, currentVideoData, videoData);

    // Update main featured video iframe
    if (mainIframe) {
      mainIframe.src = getEmbedUrl(currentVideoData.videoId, autoPlay);
    }

    // Update text elements in main card
    if (featuredTitle && currentVideoData.title) featuredTitle.textContent = currentVideoData.title;
    if (featuredCategory && currentVideoData.category) featuredCategory.textContent = currentVideoData.category;
    if (featuredViews && currentVideoData.views) featuredViews.textContent = currentVideoData.views;
    if (featuredTime && currentVideoData.time) featuredTime.textContent = currentVideoData.time;
    if (featuredDesc && currentVideoData.desc) featuredDesc.textContent = currentVideoData.desc;
    if (featuredExtLink && currentVideoData.url) featuredExtLink.href = currentVideoData.url;

    // Highlight active item in playlist
    playlistItems.forEach(item => {
      const itemId = item.getAttribute('data-video-id');
      if (itemId === currentVideoData.videoId) {
        item.classList.add('border-crimson', 'bg-navy-800');
        item.classList.remove('border-navy-800', 'bg-navy-900/90');
      } else {
        item.classList.remove('border-crimson', 'bg-navy-800');
        item.classList.add('border-navy-800', 'bg-navy-900/90');
      }
    });

    // Smooth scroll into view on mobile
    if (autoPlay && window.innerWidth < 1024) {
      const playerWrap = document.getElementById('featured-video-player-wrap');
      if (playerWrap) playerWrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }

  window.resetFeaturedVideo = function() {
    selectVideo(defaultVideoData, false);
  };

  function openVideoModal(data) {
    if (!modal) return;
    const vData = data || currentVideoData;

    if (modalTitle && vData.title) modalTitle.textContent = vData.title;
    if (modalTag && vData.category) modalTag.textContent = vData.category;
    if (modalDesc && vData.desc) modalDesc.textContent = vData.desc;
    if (modalExternalLink && vData.url) modalExternalLink.href = vData.url;

    if (modalIframe && vData.videoId) {
      modalIframe.src = getEmbedUrl(vData.videoId, true);
      modalIframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
      modalIframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
    }

    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
  }

  function closeVideoModal() {
    if (!modal) return;
    if (modalIframe) {
      modalIframe.src = '';
    }
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
  }

  // Playlist items click handler
  playlistItems.forEach(item => {
    item.addEventListener('click', (e) => {
      e.preventDefault();
      const clickedId = item.getAttribute('data-video-id') || defaultVideoData.videoId;
      const videoData = {
        videoId: clickedId,
        title: item.getAttribute('data-video-title') || 'Paide TV Video',
        category: item.getAttribute('data-video-category') || 'PAIDE TV',
        duration: item.getAttribute('data-video-duration') || '10:00',
        views: item.getAttribute('data-video-views') || item.getAttribute('data-video-duration') || '3.0K vaatamist',
        time: item.getAttribute('data-video-time') || 'YouTube @PaideLinnameeskondTV',
        desc: item.getAttribute('data-video-desc') || 'Vaata videot Paide Linnameeskonna ametlikul YouTube kanalil.',
        url: item.getAttribute('data-video-url') || `https://www.youtube.com/watch?v=${clickedId}`
      };
      selectVideo(videoData, true);
    });
  });

  // Standalone [data-video-trigger] buttons outside the playlist
  const externalTriggers = document.querySelectorAll('[data-video-trigger]:not(.video-item)');
  externalTriggers.forEach(trigger => {
    trigger.addEventListener('click', (e) => {
      e.preventDefault();
      const vidId = trigger.getAttribute('data-video-id') || defaultVideoData.videoId;
      openVideoModal({
        videoId: vidId,
        title: trigger.getAttribute('data-video-title') || defaultVideoData.title,
        category: trigger.getAttribute('data-video-category') || 'PAIDE TV',
        desc: trigger.getAttribute('data-video-desc') || defaultVideoData.desc,
        url: trigger.getAttribute('data-video-url') || `https://www.youtube.com/watch?v=${vidId}`
      });
    });
  });

  // Featured modal button
  if (featuredModalBtn) {
    featuredModalBtn.addEventListener('click', (e) => {
      e.preventDefault();
      openVideoModal(currentVideoData);
    });
  }

  // Modal close handlers
  if (closeBtn) closeBtn.addEventListener('click', closeVideoModal);
  if (closeFooterBtn) closeFooterBtn.addEventListener('click', closeVideoModal);
  
  if (modal) {
    modal.addEventListener('click', (e) => {
      if (e.target === modal || e.target.classList.contains('modal-backdrop')) {
        closeVideoModal();
      }
    });
  }

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
      closeVideoModal();
    }
  });
}

function initVideoFilters() {
  const filterButtons = document.querySelectorAll('.video-filter-btn');
  const playlistItems = document.querySelectorAll('#video-playlist-container .video-item');

  if (filterButtons.length === 0 || playlistItems.length === 0) return;

  filterButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const filter = btn.getAttribute('data-video-filter');

      // Update button styling
      filterButtons.forEach(b => {
        b.classList.remove('bg-crimson', 'text-white', 'shadow-sm');
        b.classList.add('bg-navy-900', 'text-slate-300', 'border', 'border-navy-800');
      });
      btn.classList.add('bg-crimson', 'text-white', 'shadow-sm');
      btn.classList.remove('bg-navy-900', 'text-slate-300', 'border', 'border-navy-800');

      // Filter playlist cards
      playlistItems.forEach(item => {
        const itemCategory = item.getAttribute('data-category');
        if (filter === 'all' || itemCategory === filter) {
          item.classList.remove('hidden');
          item.classList.add('flex');
        } else {
          item.classList.add('hidden');
          item.classList.remove('flex');
        }
      });

      if (filter === 'all' && typeof window.resetFeaturedVideo === 'function') {
        window.resetFeaturedVideo();
      }
    });
  });
}

/* -------------------------------------------------------------------------- */
/* 8. TICKET PURCHASE MODAL                                                   */
/* -------------------------------------------------------------------------- */
function initTicketModal() {
  const modal = document.getElementById('ticket-modal');
  const triggers = document.querySelectorAll('[data-ticket-trigger]');
  const closeBtn = document.getElementById('ticket-modal-close');

  if (!modal) return;

  function openTicketModal() {
    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
  }

  function closeTicketModal() {
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
  }

  triggers.forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      openTicketModal();
    });
  });

  if (closeBtn) closeBtn.addEventListener('click', closeTicketModal);
  modal.addEventListener('click', (e) => {
    if (e.target === modal || e.target.classList.contains('ticket-backdrop')) {
      closeTicketModal();
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
      closeTicketModal();
    }
  });
}

/* -------------------------------------------------------------------------- */
/* 9. LANGUAGE SWITCHER (ET / EN)                                             */
/* -------------------------------------------------------------------------- */
function initLanguageSwitcher() {
  const langToggle = document.getElementById('lang-switch-btn');
  const langCurrent = document.getElementById('lang-current');

  if (!langToggle || !langCurrent) return;

  langToggle.addEventListener('click', () => {
    const isEt = langCurrent.textContent.trim() === 'ET';
    const nextLang = isEt ? 'EN' : 'ET';
    langCurrent.textContent = nextLang;

    // Show temporary feedback toast
    showToast(isEt ? 'Language changed to English' : 'Keel vahetatud: Eesti');
  });
}

function showToast(message) {
  window.showToast = showToast;
  let toast = document.getElementById('status-toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'status-toast';
    toast.className = 'fixed bottom-6 right-6 z-50 bg-navy text-white text-sm font-semibold px-4 py-3 rounded-lg shadow-2xl border border-slate-700 transition-all duration-300 opacity-0 transform translate-y-3';
    document.body.appendChild(toast);
  }

  toast.textContent = message;
  toast.classList.remove('opacity-0', 'translate-y-3');
  toast.classList.add('opacity-100', 'translate-y-0');

  setTimeout(() => {
    toast.classList.remove('opacity-100', 'translate-y-0');
    toast.classList.add('opacity-0', 'translate-y-3');
  }, 2400);
}
window.showToast = showToast;
window.updateBestPlayer = updateBestPlayer;
window.updateTrackerCard = updateTrackerCard;

/* -------------------------------------------------------------------------- */
/* 10. NEWS & ARTICLES CATEGORY FILTER (MAIN PAGE #news)                      */
/* -------------------------------------------------------------------------- */
function initNewsFilters() {
  const filterBtns = document.querySelectorAll('.news-filter-btn');
  const grid = document.getElementById('news-cards-grid');
  const leadImg = document.getElementById('news-lead-img');
  const leadBadge1 = document.getElementById('news-lead-badge-1');
  const leadBadge2 = document.getElementById('news-lead-badge-2');
  const leadMetaLeft = document.getElementById('news-lead-meta-left');
  const leadMetaRight = document.getElementById('news-lead-meta-right');
  const leadKicker = document.getElementById('news-lead-kicker');
  const leadTitleLink = document.getElementById('news-lead-title-link');
  const leadExcerpt = document.getElementById('news-lead-excerpt');
  const leadAuthor = document.getElementById('news-lead-author');
  const leadAvatar = document.getElementById('news-lead-avatar');
  const leadCta = document.getElementById('news-lead-cta');
  const leadCtaText = document.getElementById('news-lead-cta-text');
  const secondaryList = document.getElementById('news-secondary-list');

  if (!filterBtns.length || !secondaryList) return;

  const newsByCategory = {
    all: {
      lead: {
        img: 'assets/img/youth/suvelaager.jpg',
        alt: 'Paide Linnameeskonna jalgpallikool — Tule trenni!',
        badge1: 'TULE TRENNI!',
        badge2: '1. KUU TASUTA',
        metaLeft: 'Paide • Türi • Koeru • Järva-Jaani',
        metaRight: 'HOOAEG 2026',
        kicker: 'NOORTEAKADEEMIA • JALGPALLIKOOL',
        title: 'Tule trenni! Paide Linnameeskonna jalgpallikool ootab uusi noormängijaid',
        href: 'noortetoo.html',
        excerpt: 'Ootame kõiki 5–16-aastaseid poisse ja tüdrukuid liituma Paide Linnameeskonna noortetreeningutega üle kogu Järvamaa. UEFA litsentsiga professionaalsed treenerid, kaasaegne treeningkeskkond ja sõbralik kogukond – esimene proovikuu on kõigile tasuta!',
        author: 'Paide Noortetöö',
        avatar: 'assets/img/photos/nextgen_team_article.jpg',
        ctaText: 'Vaata gruppe & liitu'
      },
      secondary: [
        {
          href: 'artikkel-linnanaiskond-elva-voit.html',
          img: 'assets/img/news/PLN_Elva_Victory.JPEG',
          alt: 'Linnanaiskond alistas Elva ja jätkab meistritiitli jahti!',
          badge: 'NAISMEESKOND',
          badgeClass: 'bg-crimson',
          date: '26. OKTOOBER 2026 • 2 MIN',
          title: 'Linnanaiskond alistas Elva ja jätkab meistritiitli jahti!',
          excerpt: 'Paide Linnanaiskond alistas kodusel kunstmuruväljakul FC Elva naiskonna kindlalt 4:0. Kaks väravat said kirja nii Lisandra Rannasto kui ka Katriin Rodi!'
        },
        {
          href: 'artikkel-linnanaiskond-flora-voit.html',
          img: 'assets/img/news/PLN_FCF_Victory.JPEG',
          alt: 'Linnanaiskond alistas Flora ja tõusis tiitliheitluses ühele pulgale!',
          badge: 'NAISMEESKOND',
          badgeClass: 'bg-crimson',
          date: '17. OKTOOBER 2026 • 2 MIN',
          title: 'Linnanaiskond alistas Flora ja tõusis tiitliheitluses ühele pulgale!',
          excerpt: 'Paide Linnanaiskond alistas reedel, 16. oktoobril naiste Meistriliiga senise tabeliliidri FC Flora 2:0 ning tõusis pealinna rohelistega võrdsele punktisummale!'
        },
        {
          href: 'artikkel-lilander-tammeka-voit.html',
          img: 'assets/img/news/lilander_winning_goal_tammeka.JPEG',
          alt: 'Lilanderi üleminutite värav tõi Linnameeskonnale magusa võidu Tammeka üle!',
          badge: 'ESINDUSMEESKOND',
          badgeClass: 'bg-navy',
          date: '15. OKTOOBER 2026 • 2 MIN',
          title: 'Lilanderi üleminutite värav tõi Linnameeskonnale magusa võidu Tammeka üle!',
          excerpt: 'Paide Linnameeskond teenis Premium liiga 31. voorus koduväljakul väärtusliku 2:1 võidu Tartu JK Tammeka üle. Kohtumise kangelaseks kerkis Michael Lilander!'
        }
      ]
    },
    esindus: {
      lead: {
        img: 'assets/img/news/lilander_winning_goal_tammeka.JPEG',
        alt: 'Lilanderi üleminutite värav tõi Linnameeskonnale magusa võidu Tammeka üle!',
        badge1: 'PREMIUM LIIGA',
        badge2: 'ESINDUSMEESKOND',
        metaLeft: 'Paide Linnameeskond 2 : 1 Tartu JK Tammeka',
        metaRight: '15. OKTOOBER 2026',
        kicker: 'ESINDUSMEESKOND • MÄNGUÜLEVAADE',
        title: 'Lilanderi üleminutite värav tõi Linnameeskonnale magusa võidu Tammeka üle!',
        href: 'artikkel-lilander-tammeka-voit.html',
        excerpt: 'Paide Linnameeskond teenis kolmapäeval, 14. oktoobril A. Le Coq Premium liiga 31. voorus koduväljakul väärtusliku 2:1 võidu Tartu JK Tammeka üle. Kohtumise kangelaseks kerkis Michael Lilander, kelle neljandal üleminutil löödud tabamus jättis kolm punkti Eestimaa südamesse!',
        author: 'Klubi meedia',
        avatar: 'assets/img/photos/main_team_article.jpeg',
        ctaText: 'Loe täispikka uudist'
      },
      secondary: [
        {
          href: 'artikkel-badamosi-tsehhi-korgliigasse.html',
          img: 'assets/img/news/badamosi-tsehhi-korgliigasse.jpeg',
          alt: 'Abdourahman Badamosi siirdus Tšehhi kõrgliigasse',
          badge: 'ESINDUSMEESKOND',
          badgeClass: 'bg-navy',
          date: '08. SEPTEMBER 2026 • 3 MIN',
          title: 'Abdourahman Badamosi siirdus Tšehhi kõrgliigasse',
          excerpt: 'Paide Linnameeskonna ründaja Abdourahman Badamosi sõlmis lepingu Tšehhi kõrgliigaklubiga FK Teplice.'
        },
        {
          href: 'artikkel-dawda-darboe.html',
          img: 'assets/img/news/paidega-liitus-gambia-rundaja-dawda-darboe.jpg',
          alt: 'Paidega liitus Gambia ründaja Dawda Darboe',
          badge: 'ESINDUSMEESKOND',
          badgeClass: 'bg-navy',
          date: '21. AUGUST 2026 • 2 MIN',
          title: 'Paidega liitus Gambia ründaja Dawda Darboe',
          excerpt: 'Paide Linnameeskonnaga sõlmis mitmeaastase lepingu 21-aastane Gambia noortekoondise ründaja Dawda Darboe, kes hakkab kandma särginumbrit 29.'
        },
        {
          href: 'artikkel-palacios-kolumbia.html',
          img: 'assets/img/news/paide-hankis-aarerundaja-kolumbiast.jpg',
          alt: 'Paide hankis ääreründaja Kolumbiast',
          badge: 'ESINDUSMEESKOND',
          badgeClass: 'bg-navy',
          date: '17. AUGUST 2026 • 2 MIN',
          title: 'Paide hankis ääreründaja Kolumbiast',
          excerpt: 'Paide Linnameeskond täiendas rünnakut Kolumbia ääreründaja Juan Palaciosega, kes liitus meeskonnaga laenulepingu alusel ostuvõimalusega.'
        }
      ]
    },
    naiskond: {
      lead: {
        img: 'assets/img/news/PLN_Elva_Victory.JPEG',
        alt: 'Paide Linnanaiskonna 4:0 võit FC Elva üle',
        badge1: 'NAISTE MEISTRILIIGA',
        badge2: 'PAIDE 4:0 ELVA',
        metaLeft: 'Paide Linnanaiskond 4 : 0 FC Elva',
        metaRight: '26. OKTOOBER 2026',
        kicker: 'PAIDE LINNANAISKOND • MÄNGUÜLEVAADE',
        title: 'Linnanaiskond alistas Elva ja jätkab meistritiitli jahti!',
        href: 'artikkel-linnanaiskond-elva-voit.html',
        excerpt: 'Paide Linnanaiskond jätkas naiste Meistriliiga hooaja lõpusirgel võidulainel, kui laupäeval, 24. oktoobril alistati kodusel kunstmuruväljakul FC Elva naiskond kindlalt 4:0. Kaks väravat said kirja nii Lisandra Rannasto kui ka Katriin Rodi!',
        author: 'Paide Linnanaiskond',
        avatar: 'assets/img/photos/female_team_article.jpg',
        ctaText: 'Loe täispikka uudist'
      },
      secondary: [
        {
          href: 'artikkel-linnanaiskond-flora-voit.html',
          img: 'assets/img/news/PLN_FCF_Victory.JPEG',
          alt: 'Linnanaiskond alistas Flora ja tõusis tiitliheitluses ühele pulgale!',
          badge: 'NAISMEESKOND',
          badgeClass: 'bg-crimson',
          date: '17. OKTOOBER 2026 • 2 MIN',
          title: 'Linnanaiskond alistas Flora ja tõusis tiitliheitluses ühele pulgale!',
          excerpt: 'Paide Linnanaiskond alistas reedel, 16. oktoobril naiste Meistriliiga senise tabeliliidri FC Flora 2:0 ning tõusis pealinna rohelistega võrdsele punktisummale!'
        },
        {
          href: 'artikkel-linnanaiskond-mayo.html',
          img: 'assets/img/photos/Mayo_presentation_1_web.jpg',
          alt: 'Kennedy Avery Mayo esitleb Paide särki',
          badge: 'NAISMEESKOND',
          badgeClass: 'bg-crimson',
          date: '18. SEPTEMBER 2026 • 2 MIN',
          title: 'Linnanaiskond sai esimese välismängija',
          excerpt: 'Paide Linnanaiskonnaga liitus 23-aastane ameeriklannast ründaja Kennedy Avery Mayo (#21), kellest saab ühtlasi meie naiskonna ajaloo esimene välismängija.'
        },
        {
          href: 'artikkel-naiskond-nora-mai-kool.html',
          img: 'assets/img/news/naiskond-taiendas-ridu-aarekaitsjaga.jpg',
          alt: 'Naiskond täiendas ridu äärekaitsjaga',
          badge: 'NAISMEESKOND',
          badgeClass: 'bg-crimson',
          date: '06. AUGUST 2026 • 2 MIN',
          title: 'Naiskond täiendas ridu äärekaitsjaga',
          excerpt: 'Paide Linnanaiskonnaga liitus Eesti noortekoondise äärekaitsja Nora-Mai Kool, tuues naiskonna kaitseliini lisajõudu ja kiirust.'
        }
      ]
    },
    akadeemia: {
      lead: {
        img: 'assets/img/youth/suvelaager.jpg',
        alt: 'Paide Linnameeskonna jalgpallikool — Tule trenni!',
        badge1: 'TULE TRENNI!',
        badge2: '1. KUU TASUTA',
        metaLeft: 'Paide • Türi • Koeru • Järva-Jaani',
        metaRight: 'HOOAEG 2026',
        kicker: 'NOORTEAKADEEMIA • JALGPALLIKOOL',
        title: 'Tule trenni! Paide Linnameeskonna jalgpallikool ootab uusi noormängijaid',
        href: 'noortetoo.html',
        excerpt: 'Ootame kõiki 5–16-aastaseid poisse ja tüdrukuid liituma Paide Linnameeskonna noortetreeningutega üle kogu Järvamaa. UEFA litsentsiga professionaalsed treenerid, kaasaegne treeningkeskkond ja sõbralik kogukond – esimene proovikuu on kõigile tasuta!',
        author: 'Paide Noortetöö',
        avatar: 'assets/img/photos/nextgen_team_article.jpg',
        ctaText: 'Vaata gruppe & liitu'
      },
      secondary: [
        {
          href: 'artikkel-uute-laste-vastuvott.html',
          img: 'assets/img/news/uute-laste-vastuvott-klubisse-on-alanud.jpg',
          alt: 'Uute laste vastuvõtt klubisse on alanud!',
          badge: 'NOORTETÖÖ',
          badgeClass: 'bg-navy',
          date: '31. AUGUST 2026 • 2 MIN',
          title: 'Uute laste vastuvõtt klubisse on alanud!',
          excerpt: 'Paide Linnameeskond alustab uue hooaja eel laste vastuvõttu treeninggruppidesse Paides, Türil, Väätsal, Koerus ja Järva-Jaanis. Esimene treeningkuu on tasuta!'
        },
        {
          href: 'artikkel-natural-suvelaager.html',
          img: 'assets/img/news/natural-suvelaagrit-nauditi-taiest-sudamest.jpg',
          alt: 'Natural Suvelaagrit nauditi täiest südamest',
          badge: 'AKADEEMIA',
          badgeClass: 'bg-crimson',
          date: '10. AUGUST 2026 • 4 MIN',
          title: 'Natural Suvelaagrit nauditi täiest südamest',
          excerpt: 'Järvamaa noorte jalgpallisuve suursündmus Natural Suvelaager tõi Paidesse ja Türile kokku rekordilised 101 last.'
        },
        {
          href: 'artikkel-akadeemia-comfort.html',
          img: 'assets/img/news/jalgpalliakadeemia-sai-suurtoetaja.jpg',
          alt: 'Jalgpalliakadeemia sai suurtoetaja',
          badge: 'AKADEEMIA',
          badgeClass: 'bg-navy',
          date: '26. MÄRTS 2026 • 3 MIN',
          title: 'Jalgpalliakadeemia sai suurtoetaja',
          excerpt: 'Paide Linnameeskonna Jalgpalliakadeemia sõlmis kolmeaastase suurtoetajalepingu ettevõttega Comfort AE AS.'
        }
      ]
    },
    kogukond: {
      lead: {
        img: 'assets/img/photos/Fanbus_web.jpg',
        alt: 'Paide Linnameeskonna fännibuss',
        badge1: 'KOGUKOND & FÄNNID',
        badge2: 'KARIKAFINAAL',
        metaLeft: 'Paide → A. Le Coq Arena',
        metaRight: '25. SEPTEMBER 2026',
        kicker: 'FÄNNIREIS • VÄIKESTE KARIKAVÕISTLUSTE FINAAL',
        title: 'Fännibussiga finaali vaatama! 🏁',
        href: 'artikkel-fannibuss.html',
        excerpt: 'Sel laupäeval ootab Paide Linnameeskond III ees kuues Väikeste karikavõistluste finaal Harju JK Laagri U21 vastu. Tule fännibussiga A. Le Coq Arenale ja toome karika Paidesse!',
        author: 'Paide Kogukond',
        avatar: 'assets/img/photos/main_team_article.jpeg',
        ctaText: 'Loe täispikka uudist'
      },
      secondary: [
        {
          href: 'artikkel-spordiedendajad.html',
          img: 'assets/img/news/linnameeskond-taienes-kahe-spordiedendajaga.jpg',
          alt: 'Linnameeskond täienes kahe spordiedendajaga',
          badge: 'KOGUKOND',
          badgeClass: 'bg-navy',
          date: '14. JUULI 2026 • 3 MIN',
          title: 'Linnameeskond täienes kahe spordiedendajaga',
          excerpt: 'Paide Linnameeskonna klubistruktuur ja sporditöö laienesid kahe kogenud spordiedendaja liitumisega, et tugevdada kohalikku kogukonnatööd.'
        },
        {
          href: 'artikkel-horticom-vaetab.html',
          img: 'assets/img/news/horticom-vaetab-klubi-kasvab.jpg',
          alt: 'Horticom väetab, klubi kasvab!',
          badge: 'KOGUKOND & KLUBI',
          badgeClass: 'bg-crimson',
          date: '20. JUUNI 2026 • 3 MIN',
          title: 'Horticom väetab, klubi kasvab!',
          excerpt: 'Paide Linnameeskonna treening- ja mängukeskkonna üheks oluliseks osaks on kvaliteetne muruväljak ning koostöö ettevõttega Horticom.'
        },
        {
          href: 'artikkel-sinilillejooks-2026.html',
          img: 'assets/img/news/sinilillejooks-2026.png',
          alt: 'Sinilillejooks 2026',
          badge: 'HEATEGEVUS',
          badgeClass: 'bg-navy',
          date: '29. MÄRTS 2026 • 4 MIN',
          title: 'Sinilillejooks 2026',
          excerpt: 'Paide Linnameeskond ja Naiskodukaitse kutsuvad Järvamaa koole osalema Sinilille teatejooksul veteranide toetuseks.'
        }
      ]
    }
  };

  function renderSecondaryCards(items) {
    secondaryList.innerHTML = items.map(item => `
      <a href="${item.href}" class="bg-white rounded-2xl hairline-border shadow-card hover:shadow-elevated hover:border-slate-300 transition-all duration-300 overflow-hidden editorial-card p-5 sm:p-6 group flex flex-col sm:flex-row gap-5 block cursor-pointer">
        <div class="sm:w-44 h-40 sm:h-auto rounded-xl overflow-hidden bg-slate-900 flex-shrink-0 relative">
          <img src="${item.img}" 
               alt="${item.alt}" 
               class="w-full h-full object-cover object-top transform group-hover:scale-105 transition-transform duration-500">
          <span class="absolute top-2 left-2 px-2 py-0.5 rounded ${item.badgeClass} text-white text-[9px] font-extrabold uppercase font-sans">
            ${item.badge}
          </span>
        </div>
        <div class="flex-1 flex flex-col justify-between">
          <div>
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1 font-sans">
              ${item.date}
            </div>
            <h4 class="font-display font-black text-xl text-navy uppercase leading-tight group-hover:text-crimson transition-colors">
              ${item.title}
            </h4>
            <p class="text-slate-500 text-xs mt-2 line-clamp-2 leading-relaxed font-sans">
              ${item.excerpt}
            </p>
          </div>
          <div class="mt-3 text-right">
            <span class="text-xs font-bold text-crimson group-hover:underline font-sans inline-flex items-center gap-1">
              <span>Loe edasi</span>
              <span class="transform group-hover:translate-x-1 transition-transform">→</span>
            </span>
          </div>
        </div>
      </a>
    `).join('');
  }

  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const category = btn.getAttribute('data-news-filter') || 'all';
      const data = newsByCategory[category] || newsByCategory.all;

      // Update active button state
      filterBtns.forEach(b => {
        b.classList.remove('bg-navy', 'text-white', 'shadow-sm');
        b.classList.add('bg-white', 'hairline-border', 'text-slate-600', 'hover:text-crimson', 'hover:border-crimson');
      });
      btn.classList.remove('bg-white', 'hairline-border', 'text-slate-600', 'hover:text-crimson', 'hover:border-crimson');
      btn.classList.add('bg-navy', 'text-white', 'shadow-sm');

      // Smooth fade transition on grid
      if (grid) grid.style.opacity = '0.35';

      setTimeout(() => {
        if (leadImg) {
          leadImg.src = data.lead.img;
          leadImg.alt = data.lead.alt;
        }
        if (leadBadge1) leadBadge1.textContent = data.lead.badge1;
        if (leadBadge2) leadBadge2.textContent = data.lead.badge2;
        if (leadMetaLeft) leadMetaLeft.textContent = data.lead.metaLeft;
        if (leadMetaRight) leadMetaRight.textContent = data.lead.metaRight;
        if (leadKicker) leadKicker.textContent = data.lead.kicker;
        if (leadTitleLink) {
          leadTitleLink.textContent = data.lead.title;
          leadTitleLink.href = data.lead.href;
        }
        if (leadExcerpt) leadExcerpt.textContent = data.lead.excerpt;
        if (leadAuthor) leadAuthor.textContent = data.lead.author;
        if (leadAvatar && data.lead.avatar) leadAvatar.src = data.lead.avatar;
        if (leadCta) leadCta.href = data.lead.href;
        if (leadCtaText) leadCtaText.textContent = data.lead.ctaText;

        renderSecondaryCards(data.secondary);

        if (grid) grid.style.opacity = '1';
      }, 130);
    });
  });
}

/* -------------------------------------------------------------------------- */
/* 10. FOOTER NEWSLETTER SIGNUP (SMAILY NOTIFICATION)                         */
/* -------------------------------------------------------------------------- */
function initNewsletterForms() {
  const forms = document.querySelectorAll('.newsletter-signup-form');
  if (!forms.length) return;

  forms.forEach((form) => {
    if (form.dataset.newsletterBound === 'true') return;
    form.dataset.newsletterBound = 'true';

    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      const emailInput = form.querySelector('input[name="subscriber_email"], input[type="email"]');
      const honeypotInput = form.querySelector('input[name="website_url"]');
      const submitBtn = form.querySelector('button[type="submit"]');
      const feedbackEl = form.querySelector('[data-newsletter-feedback]');

      const emailVal = emailInput ? emailInput.value.trim() : '';
      if (!emailVal) return;

      const originalBtnText = submitBtn ? submitBtn.textContent.trim() : 'Liitu';
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saadan...';
      }
      if (feedbackEl) {
        feedbackEl.className = 'hidden mt-2.5 text-xs font-medium rounded-lg px-3 py-2';
        feedbackEl.textContent = '';
      }

      const payload = {
        website_url: honeypotInput ? honeypotInput.value : '',
        subscriber_email: emailVal,
        source_page: window.location.pathname || '/'
      };

      const formBody = new URLSearchParams(payload).toString();
      const endpoints = ['newsletter.php', 'api/newsletter.php'];

      try {
        let succeeded = false;
        let lastError = null;

        for (const endpoint of endpoints) {
          try {
            const response = await fetch(endpoint, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'Accept': 'application/json'
              },
              body: formBody
            });

            const rawText = (await response.text()).trim();
            let result = null;
            try {
              result = JSON.parse(rawText);
            } catch (_) {
              result = null;
            }

            if (result && typeof result === 'object') {
              if (response.ok && result.ok) {
                succeeded = true;
                break;
              }
              lastError = new Error(result.error || 'Liitumine ebaõnnestus.');
              if (response.status === 400 || response.status === 429) {
                break;
              }
            } else {
              lastError = new Error('Serveri ühenduse viga (staatus ' + response.status + ').');
            }
          } catch (networkErr) {
            lastError = networkErr;
          }
        }

        if (!succeeded) {
          throw lastError || new Error('Liitumine ebaõnnestus.');
        }

        if (feedbackEl) {
          feedbackEl.className = 'mt-2.5 text-xs font-medium rounded-lg px-3 py-2 bg-emerald-500/15 border border-emerald-500/30 text-emerald-300';
          feedbackEl.textContent = 'Aitäh! Oled edukalt uudiskirjaga liitunud.';
        }
        form.reset();
      } catch (err) {
        const isLocalPreview =
          window.location.protocol === 'file:' ||
          window.location.hostname === 'localhost' ||
          window.location.hostname === '127.0.0.1';

        if (isLocalPreview) {
          if (feedbackEl) {
            feedbackEl.className = 'mt-2.5 text-xs font-medium rounded-lg px-3 py-2 bg-emerald-500/15 border border-emerald-500/30 text-emerald-300';
            feedbackEl.textContent = 'Aitäh! Oled edukalt uudiskirjaga liitunud.';
          }
          form.reset();
        } else if (feedbackEl) {
          feedbackEl.className = 'mt-2.5 text-xs font-medium rounded-lg px-3 py-2 bg-rose-500/15 border border-rose-500/30 text-rose-300';
          feedbackEl.textContent = err.message || 'Liitumise saatmisel tekkis tõrge.';
        }
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = originalBtnText;
        }
      }
    });
  });
}


