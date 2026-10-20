/**
 * Paide Linnameeskond - Official Cookie Consent Module
 * Compliant with GDPR / ePrivacy directives
 * Features persistent consent recognition via localStorage & cookies,
 * multi-tab detailed disclosure, customizable categories, and re-open capability.
 */

(function() {
  'use strict';

  const STORAGE_KEY = 'plm_cookie_consent';

  const TRANSLATIONS = {
    et: {
      title: 'See veebileht kasutab küpsiseid',
      updated: 'Küpsiste deklaratsiooni viimane uuendus: 2026',
      tabs: {
        policy: 'Küpsiste poliitika',
        declaration: 'Küpsiste deklaratsioon',
        about: 'Teave küpsiste kohta'
      },
      content: {
        policy: 'Kasutame küpsiseid sisu ja reklaamide isikupärastamiseks, sotsiaalmeedia funktsioonide pakkumiseks ning veebiliikluse analüüsimiseks. Samuti jagame teavet teie meie saidi kasutamise kohta oma sotsiaalmeedia-, reklaami- ja analüüsipartneritega, kes võivad seda kombineerida muu teabega, mida olete neile andnud või mida nad on kogunud teie teenuste kasutamisest. Meie veebilehe kasutamist jätkates nõustute küpsiste kasutamisega.',
        declaration: 'Vajalikud küpsised aitavad muuta veebilehe kasutatavaks, võimaldades põhifunktsioone nagu lehel navigeerimine ja turvalistele aladele juurdepääs. Eelistuste küpsised jätavad meelde teie keele- ja kuvaseaded. Statistika küpsised aitavad meil anonüümselt mõista, kuidas külastajad veebilehega suhtlevad. Turundusküpsiseid kasutatakse asjakohaste teadaannete ja partnerpakkumiste kuvamiseks.',
        about: 'Küpsised on väikesed tekstifailid, mida veebisaidid kasutavad kasutajakogemuse tõhustamiseks. Seaduse kohaselt võime teie seadmesse salvestada küpsiseid, kui need on selle saidi toimimiseks hädavajalikud. Kõigi teiste küpsisetüüpide jaoks vajame teie luba. Saate oma nõusolekut igal ajal muuta või selle tagasi võtta meie veebilehel oleva lingi "Küpsiste seaded" kaudu.'
      },
      settingsTitle: 'Muuda oma küpsiste seadeid',
      categories: {
        necessary: 'Vajalikud',
        preferences: 'Eelistused',
        statistics: 'Statistika',
        marketing: 'Turundus'
      },
      buttons: {
        acceptAll: 'NÕUSTU KÕIKIDEGA',
        customize: 'SALVESTA VALIKUD',
        onlyNecessary: 'Ainult vajalikud küpsised'
      }
    },
    en: {
      title: 'This website uses cookies',
      updated: 'Cookie declaration last updated on 2026',
      tabs: {
        policy: 'Cookie Policy',
        declaration: 'Cookie Declaration',
        about: 'About Cookies'
      },
      content: {
        policy: "We use cookies to personalise content and ads, to provide social media features and to analyse our traffic. We also share information about your use of our site with our social media, advertising and analytics partners who may combine it with other information that you've provided to them or that they've collected from your use of their services. You consent to our cookies if you continue to use our website.",
        declaration: 'Necessary cookies help make a website usable by enabling basic functions like page navigation and access to secure areas. Preference cookies enable a website to remember information that changes the way the website behaves or looks. Statistics cookies help website owners understand how visitors interact with websites. Marketing cookies are used to track visitors across websites to display relevant ads.',
        about: 'Cookies are small text files that can be used by websites to make a user\'s experience more efficient. The law states that we can store cookies on your device if they are strictly necessary for the operation of this site. For all other types of cookies we need your permission. You can at any time change or withdraw your consent from the Cookie Declaration on our website.'
      },
      settingsTitle: 'Change your cookie settings',
      categories: {
        necessary: 'Necessary',
        preferences: 'Preferences',
        statistics: 'Statistics',
        marketing: 'Marketing'
      },
      buttons: {
        acceptAll: 'ACCEPT COOKIES',
        customize: 'CUSTOMIZE COOKIES',
        onlyNecessary: 'Accept only necessary'
      }
    }
  };

  function getCurrentLang() {
    const langEl = document.getElementById('lang-current');
    if (langEl && langEl.textContent.trim().toUpperCase() === 'EN') {
      return 'en';
    }
    const htmlLang = document.documentElement.lang;
    return htmlLang === 'en' ? 'en' : 'et';
  }

  function getConsent() {
    try {
      const stored = localStorage.getItem(STORAGE_KEY);
      if (stored) return JSON.parse(stored);
    } catch (e) {
      // localStorage disabled or private browsing restriction
    }

    const match = document.cookie.match(/(^|;\s*)plm_cookie_consent=([^;]*)/);
    if (match) {
      try {
        return JSON.parse(decodeURIComponent(match[2]));
      } catch (e) {}
    }
    return null;
  }

  function saveConsent(data) {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
    } catch (e) {}

    const expires = new Date();
    expires.setFullYear(expires.getFullYear() + 1);
    document.cookie = `plm_cookie_consent=${encodeURIComponent(JSON.stringify(data))}; expires=${expires.toUTCString()}; path=/; SameSite=Lax`;

    window.dispatchEvent(new CustomEvent('plm_cookie_consent_saved', { detail: data }));
  }

  function createBannerHTML() {
    return `
      <div id="cookie-consent-banner" 
           role="dialog" 
           aria-live="polite" 
           aria-label="Küpsiste nõusolek"
           class="fixed bottom-0 left-0 right-0 z-[9999] bg-[#f4f5f7] text-slate-800 border-t border-slate-300 shadow-[0_-12px_36px_rgba(0,0,0,0.14)] transform translate-y-full transition-transform duration-500 ease-out font-sans">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-6">
          <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            
            <!-- Left Column: Title, Declaration Date, Tabs & Policy Description -->
            <div class="lg:col-span-7 xl:col-span-8 flex flex-col justify-between">
              <div>
                <h3 id="cookie-title" class="font-display font-bold text-xl sm:text-2xl text-slate-900 tracking-tight">
                  See veebileht kasutab küpsiseid
                </h3>
                <p id="cookie-updated" class="text-xs text-slate-500 mt-0.5 mb-3">
                  Küpsiste deklaratsiooni viimane uuendus: 2026
                </p>

                <!-- Navigation Tabs -->
                <div class="flex items-center gap-6 border-b border-slate-200 pb-2 mb-3 text-xs sm:text-sm font-semibold">
                  <button type="button" 
                          class="cookie-tab-btn active pb-1 border-b-2 border-slate-900 text-slate-900 font-bold transition-all cursor-pointer focus:outline-none" 
                          data-cookie-tab="policy" 
                          id="cookie-tab-policy">
                    Küpsiste poliitika
                  </button>
                  <button type="button" 
                          class="cookie-tab-btn pb-1 border-b-2 border-transparent text-slate-500 hover:text-slate-900 transition-all cursor-pointer focus:outline-none" 
                          data-cookie-tab="declaration" 
                          id="cookie-tab-declaration">
                    Küpsiste deklaratsioon
                  </button>
                  <button type="button" 
                          class="cookie-tab-btn pb-1 border-b-2 border-transparent text-slate-500 hover:text-slate-900 transition-all cursor-pointer focus:outline-none" 
                          data-cookie-tab="about" 
                          id="cookie-tab-about">
                    Teave küpsiste kohta
                  </button>
                </div>

                <!-- Active Tab Text Description -->
                <div id="cookie-tab-text" class="text-xs sm:text-sm text-slate-600 leading-relaxed min-h-[56px]">
                  Kasutame küpsiseid sisu ja reklaamide isikupärastamiseks, sotsiaalmeedia funktsioonide pakkumiseks ning veebiliikluse analüüsimiseks. Samuti jagame teavet teie meie saidi kasutamise kohta oma sotsiaalmeedia-, reklaami- ja analüüsipartneritega, kes võivad seda kombineerida muu teabega, mida olete neile andnud või mida nad on kogunud teie teenuste kasutamisest. Meie veebilehe kasutamist jätkates nõustute küpsiste kasutamisega.
                </div>
              </div>
            </div>

            <!-- Right Column: Accept Button, Settings Heading & 2x2 Categories Checkboxes -->
            <div class="lg:col-span-5 xl:col-span-4 bg-white/60 sm:bg-transparent p-4 sm:p-0 rounded-2xl sm:rounded-none border sm:border-0 border-slate-200">
              
              <!-- Action Buttons Row -->
              <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 mb-4">
                <button type="button" 
                        id="cookie-btn-accept" 
                        class="flex-1 py-3 px-6 bg-slate-950 hover:bg-navy-900 text-white font-display font-bold text-xs uppercase tracking-wider transition-all shadow-md hover:shadow-lg text-center cursor-pointer rounded-lg">
                  NÕUSTU KÕIKIDEGA
                </button>
                <button type="button" 
                        id="cookie-btn-customize" 
                        class="py-3 px-4 bg-white hover:bg-slate-50 text-slate-800 border border-slate-300 font-display font-bold text-xs uppercase tracking-wider transition-all text-center cursor-pointer rounded-lg">
                  SALVESTA VALIKUD
                </button>
              </div>

              <!-- Settings Subheading -->
              <div id="cookie-settings-title" class="text-xs font-bold text-slate-900 uppercase tracking-wide mb-2.5">
                Muuda oma küpsiste seadeid
              </div>

              <!-- 2x2 Category Checkbox Matrix -->
              <div class="grid grid-cols-2 gap-x-4 gap-y-2.5 text-xs text-slate-700">
                
                <!-- Necessary (Mandatory) -->
                <label class="flex items-center gap-2 font-semibold cursor-not-allowed select-none" title="Vajalikud küpsised tagavad veebilehe toimimise">
                  <input type="checkbox" checked disabled class="w-4 h-4 rounded text-slate-950 accent-slate-950 cursor-not-allowed">
                  <span id="cookie-cat-nec" class="flex items-center gap-1">
                    Vajalikud
                    <svg class="w-3 h-3 text-slate-400 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                  </span>
                </label>

                <!-- Preferences -->
                <label class="flex items-center gap-2 font-medium cursor-pointer select-none">
                  <input type="checkbox" id="cookie-cb-pref" class="w-4 h-4 rounded text-slate-950 accent-slate-950 cursor-pointer">
                  <span id="cookie-cat-pref">Eelistused</span>
                </label>

                <!-- Statistics -->
                <label class="flex items-center gap-2 font-medium cursor-pointer select-none">
                  <input type="checkbox" id="cookie-cb-stat" class="w-4 h-4 rounded text-slate-950 accent-slate-950 cursor-pointer">
                  <span id="cookie-cat-stat">Statistika</span>
                </label>

                <!-- Marketing -->
                <label class="flex items-center gap-2 font-medium cursor-pointer select-none">
                  <input type="checkbox" id="cookie-cb-mark" class="w-4 h-4 rounded text-slate-950 accent-slate-950 cursor-pointer">
                  <span id="cookie-cat-mark">Turundus</span>
                </label>
              </div>

              <!-- Reject / Only Necessary Link -->
              <div class="mt-3.5 pt-2 border-t border-slate-200/70 flex items-center justify-between text-[11px] text-slate-500">
                <button type="button" id="cookie-btn-reject" class="hover:text-slate-900 underline transition-colors cursor-pointer focus:outline-none">
                  Ainult vajalikud küpsised
                </button>
                <span class="text-[10px] text-slate-400 font-mono">GDPR / ePrivacy</span>
              </div>

            </div>

          </div>
        </div>
      </div>
    `;
  }

  function initCookieConsent() {
    // If banner element does not already exist in DOM, create and inject it
    let banner = document.getElementById('cookie-consent-banner');
    if (!banner) {
      document.body.insertAdjacentHTML('beforeend', createBannerHTML());
      banner = document.getElementById('cookie-consent-banner');
    }

    if (!banner) return;

    let activeTab = 'policy';
    const lang = getCurrentLang();
    updateText(lang, activeTab);

    // Check if the user has already consented
    const existingConsent = getConsent();

    if (existingConsent) {
      // User has already made their decision: DO NOT show banner again!
      banner.classList.add('hidden');
      banner.classList.remove('translate-y-0');
      banner.classList.add('translate-y-full');
      // Apply saved checkbox states
      applyCheckboxStates(existingConsent);
    } else {
      // First visit: smoothly slide the banner up
      banner.classList.remove('hidden');
      setTimeout(() => {
        banner.classList.remove('translate-y-full');
        banner.classList.add('translate-y-0');
      }, 350);
    }

    // Attach Tab Navigation Listeners
    const tabButtons = banner.querySelectorAll('.cookie-tab-btn');
    tabButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        const tab = btn.getAttribute('data-cookie-tab');
        activeTab = tab;

        tabButtons.forEach(b => {
          b.classList.remove('active', 'border-slate-900', 'text-slate-900', 'font-bold');
          b.classList.add('border-transparent', 'text-slate-500');
        });

        btn.classList.add('active', 'border-slate-900', 'text-slate-900', 'font-bold');
        btn.classList.remove('border-transparent', 'text-slate-500');

        updateTabText(getCurrentLang(), activeTab);
      });
    });

    // Accept All Button
    const acceptBtn = document.getElementById('cookie-btn-accept');
    if (acceptBtn) {
      acceptBtn.addEventListener('click', () => {
        const consent = {
          necessary: true,
          preferences: true,
          statistics: true,
          marketing: true,
          timestamp: new Date().toISOString(),
          decision: 'all'
        };
        saveConsent(consent);
        closeBanner(banner);
        if (window.showToast) {
          window.showToast(getCurrentLang() === 'en' ? 'Cookies accepted' : 'Küpsised edukalt kinnitatud');
        }
      });
    }

    // Customize / Save Choices Button
    const customizeBtn = document.getElementById('cookie-btn-customize');
    if (customizeBtn) {
      customizeBtn.addEventListener('click', () => {
        const pref = document.getElementById('cookie-cb-pref');
        const stat = document.getElementById('cookie-cb-stat');
        const mark = document.getElementById('cookie-cb-mark');

        const consent = {
          necessary: true,
          preferences: pref ? pref.checked : false,
          statistics: stat ? stat.checked : false,
          marketing: mark ? mark.checked : false,
          timestamp: new Date().toISOString(),
          decision: 'custom'
        };
        saveConsent(consent);
        closeBanner(banner);
        if (window.showToast) {
          window.showToast(getCurrentLang() === 'en' ? 'Cookie preferences saved' : 'Küpsiste eelistused salvestatud');
        }
      });
    }

    // Reject All / Necessary Only Button
    const rejectBtn = document.getElementById('cookie-btn-reject');
    if (rejectBtn) {
      rejectBtn.addEventListener('click', () => {
        const consent = {
          necessary: true,
          preferences: false,
          statistics: false,
          marketing: false,
          timestamp: new Date().toISOString(),
          decision: 'necessary_only'
        };
        saveConsent(consent);
        closeBanner(banner);
        if (window.showToast) {
          window.showToast(getCurrentLang() === 'en' ? 'Only necessary cookies enabled' : 'Lubatud vaid vajalikud küpsised');
        }
      });
    }

    // Bind footer "Küpsiste seaded" links to re-open the banner anytime
    bindReopenTriggers(banner);

    // Listen for language switch if available
    const langToggle = document.getElementById('lang-switch-btn');
    if (langToggle) {
      langToggle.addEventListener('click', () => {
        setTimeout(() => {
          updateText(getCurrentLang(), activeTab);
        }, 50);
      });
    }
  }

  function applyCheckboxStates(consent) {
    const pref = document.getElementById('cookie-cb-pref');
    const stat = document.getElementById('cookie-cb-stat');
    const mark = document.getElementById('cookie-cb-mark');
    if (pref) pref.checked = !!consent.preferences;
    if (stat) stat.checked = !!consent.statistics;
    if (mark) mark.checked = !!consent.marketing;
  }

  function closeBanner(banner) {
    if (!banner) return;
    banner.classList.remove('translate-y-0');
    banner.classList.add('translate-y-full');
    setTimeout(() => {
      banner.classList.add('hidden');
    }, 500);
  }

  function openBanner(banner) {
    if (!banner) return;
    const current = getConsent();
    if (current) applyCheckboxStates(current);
    banner.classList.remove('hidden');
    setTimeout(() => {
      banner.classList.remove('translate-y-full');
      banner.classList.add('translate-y-0');
    }, 50);
  }

  function bindReopenTriggers(banner) {
    const triggers = document.querySelectorAll('[data-cookie-settings-trigger], #cookie-settings-trigger, a[href="#cookie-settings"], a[href="#kupsised"]');
    triggers.forEach(trigger => {
      trigger.addEventListener('click', (e) => {
        e.preventDefault();
        openBanner(banner);
      });
    });

    // Also attach to footer link containing text "Küpsiste seaded"
    document.querySelectorAll('footer a').forEach(a => {
      if (a.textContent.includes('Küpsiste seaded') || a.textContent.includes('Cookie Settings')) {
        a.setAttribute('data-cookie-settings-trigger', 'true');
        a.style.cursor = 'pointer';
        a.addEventListener('click', (e) => {
          e.preventDefault();
          openBanner(banner);
        });
      }
    });
  }

  function updateTabText(lang, tab) {
    const textEl = document.getElementById('cookie-tab-text');
    const t = TRANSLATIONS[lang] || TRANSLATIONS.et;
    if (textEl && t.content[tab]) {
      textEl.textContent = t.content[tab];
    }
  }

  function updateText(lang, activeTab) {
    const t = TRANSLATIONS[lang] || TRANSLATIONS.et;

    const titleEl = document.getElementById('cookie-title');
    const updatedEl = document.getElementById('cookie-updated');
    const tabPolicy = document.getElementById('cookie-tab-policy');
    const tabDecl = document.getElementById('cookie-tab-declaration');
    const tabAbout = document.getElementById('cookie-tab-about');
    const settingsTitle = document.getElementById('cookie-settings-title');
    const btnAccept = document.getElementById('cookie-btn-accept');
    const btnCustomize = document.getElementById('cookie-btn-customize');
    const btnReject = document.getElementById('cookie-btn-reject');
    const catPref = document.getElementById('cookie-cat-pref');
    const catStat = document.getElementById('cookie-cat-stat');
    const catMark = document.getElementById('cookie-cat-mark');

    if (titleEl) titleEl.textContent = t.title;
    if (updatedEl) updatedEl.textContent = t.updated;
    if (tabPolicy) tabPolicy.textContent = t.tabs.policy;
    if (tabDecl) tabDecl.textContent = t.tabs.declaration;
    if (tabAbout) tabAbout.textContent = t.tabs.about;
    if (settingsTitle) settingsTitle.textContent = t.settingsTitle;
    if (btnAccept) btnAccept.textContent = t.buttons.acceptAll;
    if (btnCustomize) btnCustomize.textContent = t.buttons.customize;
    if (btnReject) btnReject.textContent = t.buttons.onlyNecessary;
    if (catPref) catPref.textContent = t.categories.preferences;
    if (catStat) catStat.textContent = t.categories.statistics;
    if (catMark) catMark.textContent = t.categories.marketing;

    updateTabText(lang, activeTab);
  }

  // Export globally for programmatic control
  window.PaideCookies = {
    getConsent: getConsent,
    openSettings: function() {
      const banner = document.getElementById('cookie-consent-banner');
      if (banner) openBanner(banner);
    },
    resetConsent: function() {
      try {
        localStorage.removeItem(STORAGE_KEY);
      } catch (e) {}
      document.cookie = 'plm_cookie_consent=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
      const banner = document.getElementById('cookie-consent-banner');
      if (banner) openBanner(banner);
    }
  };

  function initMaintenanceBanner() {
    if (document.getElementById('maintenance-banner') || !document.body) return;
    const bannerHtml = `
      <aside id="maintenance-banner" class="bg-crimson text-white text-xs sm:text-sm font-sans py-2.5 px-4 relative z-50 border-b border-black/15 shadow-sm" aria-label="Hooldusteade">
        <div class="max-w-7xl mx-auto flex items-center justify-between gap-3">
          <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white/20 text-white flex-shrink-0" aria-hidden="true">
              <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
              </svg>
            </span>
            <div class="text-xs sm:text-[13px] leading-snug">
              <span class="font-black uppercase tracking-wider bg-white/20 px-1.5 py-0.5 rounded text-[10px] mr-1.5">HOOLDUS</span>
              <span class="font-bold">Veebileht on hetkel hoolduses ja uuendamisel.</span>
              <span class="text-white/95">Kuvatav informatsioon võib ajutiselt sisaldada ebatäpsusi.</span>
              <span class="hidden lg:inline text-white/75 ml-1.5">/ Website is under maintenance — displayed information may be inaccurate.</span>
            </div>
          </div>
          <div class="flex items-center gap-2 flex-shrink-0">
            <span class="text-[10px] font-mono font-bold uppercase tracking-widest text-white/85 hidden sm:inline px-2 py-0.5 rounded bg-black/15">ARENDUSJÄRGUS</span>
          </div>
        </div>
      </aside>
    `;
    document.body.insertAdjacentHTML('afterbegin', bannerHtml);
  }

  // Run on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      initMaintenanceBanner();
      initCookieConsent();
    });
  } else {
    initMaintenanceBanner();
    initCookieConsent();
  }

})();
