/**
 * Paide Linnameeskond - Official E-Pood Interactive Engine
 * Handles catalog rendering, category filtering, cart management,
 * size selection, quick view modal, and checkout simulation.
 */

// 12 Official Products with exact pricing from the club price list
const SHOP_PRODUCTS = [
  {
    id: 'uus-plm-hall-pusa',
    name: 'UUS PLM Hall Pusa',
    category: 'riided',
    subcat: 'uus',
    price: 50,
    badge: 'UUS 2026',
    badgeColor: 'bg-crimson text-white',
    rating: 5.0,
    reviews: 18,
    primaryImg: 'assets/img/merch/uus/3I2A0301.jpg',
    secondaryImg: 'assets/img/merch/uus/3I2A0267.jpg',
    gallery: [
      'assets/img/merch/uus/3I2A0301.jpg',
      'assets/img/merch/uus/3I2A0267.jpg',
      'assets/img/merch/uus/3I2A0285.jpg',
      'assets/img/merch/uus/3I2A0309.jpg'
    ],
    sizes: ['S', 'M', 'L', 'XL', 'XXL'],
    colors: ['Hall'],
    description: 'Halli värvi orgaanilisest puuvillast (85% puuvill, 15% polüester) väga pehme ja mugav fännipusa Paide Linnameeskonna 2026. aasta disainis ruudukujulise 3D-embleemiga. Kombineeri tõeline fännikomplekt koos samas stiilis särgi ja nokatsiga!',
    details: [
      '85% orgaaniline kammitud puuvill, 15% taaskasutatud polüester (350 g/m²)',
      'Reljeefne 3D kummist tikitud PLM rinnapatch',
      'Pehme harjatud fliisisisu tagab maksimaalse soojuse ja mugavuse',
      'Kängurutasku ja reguleeritav topeltkangaga kapuuts metallotstega paeltega'
    ]
  },
  {
    id: 'uus-plm-hall-t-sark',
    name: 'UUS PLM Hall T-Särk',
    category: 'riided',
    subcat: 'uus',
    price: 25,
    badge: 'UUS 2026',
    badgeColor: 'bg-crimson text-white',
    rating: 4.9,
    reviews: 14,
    primaryImg: 'assets/img/merch/uus/3I2A0348.jpg',
    secondaryImg: 'assets/img/merch/uus/3I2A0360.jpg',
    gallery: [
      'assets/img/merch/uus/3I2A0348.jpg',
      'assets/img/merch/uus/3I2A0360.jpg',
      'assets/img/merch/uus/3I2A0341.jpg'
    ],
    sizes: ['XS', 'S', 'M', 'L', 'XL', 'XXL'],
    colors: ['Hall'],
    description: 'Sajaprotsendiliselt orgaanilisest puuvillast valmistatud pehme, mõnus ja kauakestev fännisärk Paide Linnameeskonna 2026. aasta disainis ruudukujulise 3D-embleemiga.',
    details: [
      '100% sertifitseeritud orgaaniline rõngaskedratud puuvill (180 g/m²)',
      'Vastupidav kahekordne kaelus soonikkoes',
      '3D-kummine PLM embleem vasakul rinnal',
      'Hingav ja eelkahandatud kangas, masinpestav 30°C'
    ]
  },
  {
    id: 'plm-must-t-sark',
    name: 'PLM Must T-Särk',
    category: 'riided',
    subcat: 'klassika',
    price: 25,
    badge: 'KLASSIKA',
    badgeColor: 'bg-navy-900 text-white',
    rating: 4.9,
    reviews: 22,
    primaryImg: 'assets/img/merch/export/Black_tshirt/1.jpg',
    secondaryImg: 'assets/img/merch/export/Black_tshirt/3.jpg',
    gallery: [
      'assets/img/merch/export/Black_tshirt/1.jpg',
      'assets/img/merch/export/Black_tshirt/2.jpg',
      'assets/img/merch/export/Black_tshirt/3.jpg',
      'assets/img/merch/export/Black_tshirt/4.jpg'
    ],
    sizes: ['S', 'M', 'L', 'XL', 'XXL'],
    colors: ['Must'],
    description: 'Must puuvillane esmaklassiline fännisärk 3D-trükis sinipuna triibulise PLM embleemiga. Vastupidav ja stiilne igapäevaseks kandmiseks.',
    details: [
      '100% orgaaniline puuvill (190 g/m²)',
      'Klubi ikooniline sinipuna triibuline PLM rinnambon',
      'Moodne regulaarne istuvus (regular fit)',
      'Säilitab värvi ja kuju ka pärast korduvat pesu'
    ]
  },
  {
    id: 'plm-valge-t-sark',
    name: 'PLM Valge T-Särk',
    category: 'riided',
    subcat: 'klassika',
    price: 25,
    badge: 'KLASSIKA',
    badgeColor: 'bg-navy-900 text-white',
    rating: 4.8,
    reviews: 19,
    primaryImg: 'assets/img/merch/export/White_tshirt/1.jpg',
    secondaryImg: 'assets/img/merch/export/White_tshirt/2.jpg',
    gallery: [
      'assets/img/merch/export/White_tshirt/1.jpg',
      'assets/img/merch/export/White_tshirt/2.jpg',
      'assets/img/merch/export/White_tshirt/6.jpg',
      'assets/img/merch/export/White_tshirt/3.jpg'
    ],
    sizes: ['S', 'M', 'L', 'XL', 'XXL'],
    colors: ['Valge'],
    description: 'Valge orgaaniline puuvillane särk 3D-trükis sinipuna triibulise PLM fännilogoga. Helge, puhas ja minimalistlik klubistiil.',
    details: [
      '100% kõrgekvaliteediline kammitud puuvill',
      'Kontrastne tikitud PLM 3D rinnamärk',
      'Hingav ja eriti pehme naha vastas',
      'Krae sees tugevdatud õmbluslint kuju hoidmiseks'
    ]
  },
  {
    id: 'nokamuts-vorguga',
    name: 'Nokamüts Võrguga',
    category: 'mutsid',
    subcat: 'uus',
    price: 25,
    badge: 'TRUCKER',
    badgeColor: 'bg-crimson text-white',
    rating: 4.9,
    reviews: 27,
    primaryImg: 'assets/img/merch/uus/3I2A0366.jpg',
    secondaryImg: 'assets/img/merch/export/Hat/11.jpg',
    gallery: [
      'assets/img/merch/uus/3I2A0366.jpg',
      'assets/img/merch/export/Hat/11.jpg',
      'assets/img/merch/export/Hat/12.jpg',
      'assets/img/merch/export/Hat/13.jpg'
    ],
    sizes: ['Üks suurus (reguleeritav)'],
    colors: ['Must'],
    description: 'Stiilne musta värvi võrguga nokamüts Paide Linnameeskonna ruudukujulise reljeefse 3D-embleemiga. Tagab mõnusa ventilatsiooni ja mugavuse igas olukorras.',
    details: [
      'Hingav tugevdatud polüester-võrkpaneel tagaküljel',
      'Tugev struktureeritud esiosa 3D PLM embleemiga',
      'Klassikaline 7-auguline snapback reguleerimispannal',
      'Kumer nokk stiilse viimistlusega'
    ]
  },
  {
    id: 'nokamuts-klassikaline',
    name: 'Nokamüts Klassikaline',
    category: 'mutsid',
    subcat: 'klassika',
    price: 25,
    badge: 'STRAPBACK',
    badgeColor: 'bg-navy-900 text-white',
    rating: 4.8,
    reviews: 21,
    primaryImg: 'assets/img/merch/export/Hat/6.jpg',
    secondaryImg: 'assets/img/merch/export/Hat/1.jpg',
    gallery: [
      'assets/img/merch/export/Hat/6.jpg',
      'assets/img/merch/export/Hat/1.jpg',
      'assets/img/merch/export/Hat/8.jpg',
      'assets/img/merch/export/Hat/10.jpg'
    ],
    sizes: ['Üks suurus (reguleeritav)'],
    colors: ['Must'],
    description: 'Klassikaline täispuuvillane madala profiiliga nokamüts metallist reguleerimispandlaga ja esiküljel PLM triibulise vapimärgiga.',
    details: [
      '100% vastupidav tihe puuvillane toimsits',
      'Metallist kroomitud klamber täpseks suuruse reguleerimiseks',
      'Tikitud õhutusavad pea jahutamiseks',
      'Universaalne sobivus nii meestele kui naistele'
    ]
  },
  {
    id: 'soojemat-sorti-muts',
    name: 'Soojemat Sorti Müts',
    category: 'mutsid',
    subcat: 'talv',
    price: 20,
    badge: 'TALVEHOOAEG',
    badgeColor: 'bg-blue-600 text-white',
    rating: 5.0,
    reviews: 31,
    primaryImg: 'assets/img/merch/export/Black_beanie/1.jpg',
    secondaryImg: 'assets/img/merch/export/Grey_beanie/1.jpg',
    gallery: [
      'assets/img/merch/export/Black_beanie/1.jpg',
      'assets/img/merch/export/Grey_beanie/1.jpg',
      'assets/img/merch/export/Blue_beanie/1.jpg',
      'assets/img/merch/export/Black_big_beanie/1.jpg'
    ],
    sizes: ['Universaalne suurus'],
    colors: ['Must', 'Hall', 'Tumesinine'],
    description: 'Väga soe soonikkoes kootud fännimüts tikitud PLM logoga. Hoiab pea soojas nii talvisel staadionitribüünil kui igapäevaselt linnas.',
    details: [
      'Koostis: 52% polüester, 47% akrüül, 1% elastaan',
      'Tikitud lumivalge minimalistlik PLM logo serval',
      'Kahekordne tagasipööratud serv kõrvade lisakaitseks tuule eest',
      'Saadaval 3 värvitoonis: klassikaline must, stiilne hall ja klubisinine'
    ]
  },
  {
    id: 'fannisall',
    name: 'Fännisall "Paide Way"',
    category: 'mutsid',
    subcat: 'staadion',
    price: 20,
    badge: 'STAADIONILE',
    badgeColor: 'bg-crimson text-white',
    rating: 5.0,
    reviews: 44,
    primaryImg: 'assets/img/merch/export/Scarf_with_black_tshirt/1.jpg',
    secondaryImg: 'assets/img/merch/export/Scarf_with_black_tshirt/2.jpg',
    gallery: [
      'assets/img/merch/export/Scarf_with_black_tshirt/1.jpg',
      'assets/img/merch/export/Scarf_with_black_tshirt/2.jpg',
      'assets/img/merch/export/Scarf_with_black_tshirt/4.jpg'
    ],
    sizes: ['145 x 18 cm'],
    colors: ['Sinipunane triip'],
    description: 'Ametlik Paide Linnameeskonna traditsiooniline kootud fännisall klubi vapiga, triibulises mustris ja pehmete valgete narmastega.',
    details: [
      '100% kvaliteetne pehme akrüülkude',
      'Mõlemapoolne kootud disain: PAIDE LINNAMEESKOND ja klubivapp',
      'Mõõdud: 145 cm pikkus + 7 cm narmad, laius 18 cm',
      'Iga tõelise fänni kohustuslik atribuut kodumängudel'
    ]
  },
  {
    id: '2025-hooaja-raamat',
    name: '2025. Hooaja Raamat',
    category: 'sides',
    subcat: 'kollektsioon',
    price: 30,
    badge: 'KOLLEKTSIONÄÄRILE',
    badgeColor: 'bg-amber-600 text-white',
    rating: 5.0,
    reviews: 12,
    primaryImg: 'assets/img/merch/export/raamat_2025.jpg',
    secondaryImg: 'assets/img/merch/export/raamat_2025.jpg',
    gallery: [
      'assets/img/merch/export/raamat_2025.jpg'
    ],
    sizes: ['Kõvaköide (240 lk)'],
    colors: ['Tumesinine / Kuldne foolium'],
    description: 'Eksklusiivne kõvakaaneline Paide Linnameeskonna 2025. aasta juubelihooaja raamat. Täispikk emotsionaalne tagasivaade, Euroopa mängude kronoloogia, eksklusiivintervjuud ja fotogaleriid.',
    details: [
      'Formaat: Suur kõvaköiteline kunstipaberil album (240 lk)',
      'Kaanel reljeefne kuldfooliumtrükk ja klubivapp',
      'Rikkalik fotomaterjal mängijatest, treeneritest ja fännidest',
      'Piiratud tiraažiga mälestusteos'
    ]
  },
  {
    id: 'korvarongad',
    name: 'PLM Kõrvarõngad',
    category: 'sides',
    subcat: 'ehted',
    price: 20,
    badge: 'EHE & EMAIL',
    badgeColor: 'bg-purple-700 text-white',
    rating: 4.9,
    reviews: 16,
    primaryImg: 'assets/img/merch/export/korvarongad.jpg',
    secondaryImg: 'assets/img/merch/export/korvarongad.jpg',
    gallery: [
      'assets/img/merch/export/korvarongad.jpg'
    ],
    sizes: ['Universaalne'],
    colors: ['Hõbe / Sinipunane email'],
    description: 'Hõbedase viimistlusega ja peene sinipunase emailiga Paide Linnameeskonna vappi kujutavad rippkõrvarõngad kindla prantsuse lukuga.',
    details: [
      'Kvaliteetne niklivaba hõbetatud sulam ja vastupidav kuumemail',
      'Detailne Paide vapitorn ja kolm Eesti lõvi',
      'Turvaline ja mugav hooblukk (leverback)',
      'Tarnitakse sametises kinkekotis'
    ]
  },
  {
    id: 'kohvitermos',
    name: 'PLM Kohvitermos',
    category: 'sides',
    subcat: 'aksessuaar',
    price: 15,
    badge: 'TERMOS',
    badgeColor: 'bg-slate-800 text-white',
    rating: 4.9,
    reviews: 25,
    primaryImg: 'assets/img/merch/export/termos.jpg',
    secondaryImg: 'assets/img/merch/export/termos.jpg',
    gallery: [
      'assets/img/merch/export/termos.jpg'
    ],
    sizes: ['450 ml'],
    colors: ['Mattmust'],
    description: 'Stiilne mattmust topeltseinaga roostevabast terasest vaakumtermos Paide Linnameeskonna vapiga. Hoiab kohvi kuumana kuni 8 tundi ja vee külmana 16 tundi.',
    details: [
      'Mahutavus: 450 ml, sobib ideaalselt auto topsihoidjasse',
      'Topeltseinaga vaakumisolatsiooniga roostevaba teras 18/8',
      '100% lekkekindel ühe käega avatav vajutusnupp ja lukusti',
      'BPA-vaba, vastupidav ja maitsetu materjal'
    ]
  },
  {
    id: 'sinine-pudel-karabiiniga',
    name: 'Sinine Pudel Karabiiniga',
    category: 'sides',
    subcat: 'sport',
    price: 15,
    badge: 'SPORT & JOOK',
    badgeColor: 'bg-blue-700 text-white',
    rating: 4.8,
    reviews: 19,
    primaryImg: 'assets/img/merch/export/pudel.jpg',
    secondaryImg: 'assets/img/merch/export/pudel.jpg',
    gallery: [
      'assets/img/merch/export/pudel.jpg'
    ],
    sizes: ['600 ml'],
    colors: ['Kuninglik sinine'],
    description: 'Vastupidav kuninglikku sinist värvi alumiiniumist joogipudel metallist karabiinkinnitusega mugavaks koti külge haakimiseks trennis või matkal.',
    details: [
      'Mahutavus: 600 ml, sulgkerge ja tugev alumiiniumkorpus',
      'Keeratav hermeetiline kork silikoontihendiga ja karabiinaasaga',
      'Vastupidav kriimustuskindel pulbervärvkate Paide Linnameeskonna vapiga',
      'Toiduohutu sisekiht, ei anna joogile kõrvalmaitset'
    ]
  }
];

// Cart State (Persisted in localStorage)
let CART_STATE = [];

function loadCartFromStorage() {
  try {
    const saved = localStorage.getItem('plm_cart');
    if (saved) {
      CART_STATE = JSON.parse(saved);
    }
  } catch (e) {
    CART_STATE = [];
  }
  updateCartBadge();
}

function saveCartToStorage() {
  try {
    localStorage.setItem('plm_cart', JSON.stringify(CART_STATE));
  } catch (e) {
    console.error('Failed to save cart to localStorage', e);
  }
  updateCartBadge();
}

// Add item to cart
function addToCart(productId, size = null, color = null, quantity = 1) {
  const product = SHOP_PRODUCTS.find(p => p.id === productId);
  if (!product) return;

  const chosenSize = size || (product.sizes.length > 0 ? product.sizes[0] : 'Universaalne');
  const chosenColor = color || (product.colors.length > 0 ? product.colors[0] : 'Põhitoon');
  const itemKey = `${product.id}_${chosenSize}_${chosenColor}`;

  const existing = CART_STATE.find(item => item.key === itemKey);
  if (existing) {
    existing.quantity += quantity;
  } else {
    CART_STATE.push({
      key: itemKey,
      id: product.id,
      name: product.name,
      price: product.price,
      size: chosenSize,
      color: chosenColor,
      image: product.primaryImg,
      quantity: quantity
    });
  }

  saveCartToStorage();
  renderCartDrawer();
  
  // Show toast notification
  if (window.showToast) {
    window.showToast(`"${product.name}" (${chosenSize}) lisatud ostukorvi!`);
  }
  
  // Open cart drawer for instant feedback
  openCartDrawer();
}

// Remove from cart
function removeFromCart(itemKey) {
  CART_STATE = CART_STATE.filter(item => item.key !== itemKey);
  saveCartToStorage();
  renderCartDrawer();
}

// Adjust quantity
function adjustQuantity(itemKey, delta) {
  const item = CART_STATE.find(i => i.key === itemKey);
  if (!item) return;

  item.quantity += delta;
  if (item.quantity <= 0) {
    removeFromCart(itemKey);
  } else {
    saveCartToStorage();
    renderCartDrawer();
  }
}

// Update cart counter in header badge
function updateCartBadge() {
  const badges = document.querySelectorAll('.cart-count-badge');
  const totalCount = CART_STATE.reduce((sum, item) => sum + item.quantity, 0);

  badges.forEach(badge => {
    badge.textContent = totalCount;
    if (totalCount > 0) {
      badge.classList.remove('hidden');
    } else {
      badge.classList.add('hidden');
    }
  });
}

// Render Products Grid
function renderCatalog(filter = 'all', sort = 'default') {
  const grid = document.getElementById('shop-products-grid');
  if (!grid) return;

  let filtered = [...SHOP_PRODUCTS];

  if (filter === 'uus') {
    filtered = filtered.filter(p => p.subcat === 'uus' || p.badge?.includes('UUS'));
  } else if (filter === 'riided') {
    filtered = filtered.filter(p => p.category === 'riided');
  } else if (filter === 'mutsid') {
    filtered = filtered.filter(p => p.category === 'mutsid');
  } else if (filter === 'sides') {
    filtered = filtered.filter(p => p.category === 'sides');
  }

  if (sort === 'price-asc') {
    filtered.sort((a, b) => a.price - b.price);
  } else if (sort === 'price-desc') {
    filtered.sort((a, b) => b.price - a.price);
  } else if (sort === 'popular') {
    filtered.sort((a, b) => b.reviews - a.reviews);
  }

  const countEl = document.getElementById('shop-results-count');
  if (countEl) {
    countEl.textContent = `Kuvatakse ${filtered.length} toodet`;
  }

  grid.innerHTML = filtered.map(product => {
    const hasMultipleSizes = product.sizes.length > 1;
    const defaultSize = product.sizes[0];

    return `
      <article class="product-card group relative bg-white rounded-2xl border border-slate-200/80 overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between">
        
        <!-- Top Image & Badges Container -->
        <div class="relative w-full aspect-square bg-[#f1f5f9] overflow-hidden cursor-pointer" onclick="openQuickView('${product.id}')">
          <!-- Primary Image -->
          <img src="${product.primaryImg}" 
               alt="${product.name}" 
               loading="lazy" 
               class="product-primary-img w-full h-full object-cover object-center transition-transform duration-500 group-hover:scale-105">
          
          <!-- Secondary hover image if available -->
          ${product.secondaryImg && product.secondaryImg !== product.primaryImg ? `
            <img src="${product.secondaryImg}" 
                 alt="${product.name}" 
                 loading="lazy" 
                 class="product-secondary-img absolute inset-0 w-full h-full object-cover object-center opacity-0 group-hover:opacity-100 transition-opacity duration-500">
          ` : ''}

          <!-- Floating Badges -->
          <div class="absolute top-3 left-3 flex flex-col gap-1.5 z-10">
            ${product.badge ? `
              <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-bold tracking-wider uppercase shadow-sm ${product.badgeColor}">
                ${product.badge}
              </span>
            ` : ''}
          </div>

          <!-- Quick View Hover Button -->
          <button type="button" 
                  onclick="event.stopPropagation(); openQuickView('${product.id}')"
                  class="absolute bottom-3 left-1/2 -translate-x-1/2 translate-y-4 opacity-0 group-hover:translate-y-0 group-hover:opacity-100 transition-all duration-300 px-4 py-2 bg-navy-950/90 hover:bg-crimson text-white text-xs font-bold rounded-lg shadow-lg backdrop-blur-sm flex items-center gap-1.5 whitespace-nowrap z-20">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            <span>Kiirvaade</span>
          </button>
        </div>

        <!-- Product Content Body -->
        <div class="p-5 flex flex-col flex-grow justify-between">
          <div>
            <!-- Category and Rating -->
            <div class="flex items-center justify-between text-xs text-slate-500 mb-1.5">
              <span class="uppercase tracking-wider font-semibold text-slate-400">
                ${product.category === 'riided' ? 'Rõivad' : product.category === 'mutsid' ? 'Peakatted' : 'Aksessuaar'}
              </span>
              <div class="flex items-center gap-1 text-amber-500">
                <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                <span class="font-bold text-slate-700 text-xs">${product.rating}</span>
                <span class="text-slate-400 text-[11px]">(${product.reviews})</span>
              </div>
            </div>

            <!-- Title -->
            <h3 class="font-display font-bold text-base sm:text-lg text-navy-950 mb-2 group-hover:text-crimson transition-colors line-clamp-1 cursor-pointer" onclick="openQuickView('${product.id}')">
              ${product.name}
            </h3>

            <!-- Short description -->
            <p class="text-xs text-slate-600 line-clamp-2 leading-relaxed mb-4">
              ${product.description}
            </p>
          </div>

          <!-- Price & Action Row -->
          <div>
            ${hasMultipleSizes ? `
              <!-- Size quick selector -->
              <div class="flex items-center gap-1.5 mb-3.5 flex-wrap">
                <span class="text-[11px] font-bold text-slate-400 mr-1 uppercase">Suurus:</span>
                ${product.sizes.map((s, idx) => `
                  <button type="button" 
                          data-product="${product.id}"
                          data-size="${s}"
                          onclick="selectCardSize('${product.id}', '${s}', this)"
                          class="size-pill-${product.id} text-[11px] font-semibold px-2 py-0.5 rounded border transition-all ${idx === 0 ? 'bg-navy-900 text-white border-navy-900' : 'bg-slate-50 text-slate-700 border-slate-200 hover:border-slate-400'}">
                    ${s}
                  </button>
                `).join('')}
              </div>
            ` : `
              <div class="text-[11px] font-medium text-slate-500 mb-3.5 flex items-center gap-1">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>${product.sizes[0]}</span>
              </div>
            `}

            <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
              <div>
                <span class="text-xl sm:text-2xl font-display font-black text-navy-950">
                  ${product.price} €
                </span>
              </div>

              <button type="button" 
                      id="btn-add-${product.id}"
                      onclick="addToCart('${product.id}', getSelectedCardSize('${product.id}', '${defaultSize}'))"
                      class="px-4 py-2.5 bg-crimson hover:bg-crimson-hover text-white text-xs font-bold tracking-wider uppercase rounded-xl transition-all shadow-sm hover:shadow flex items-center gap-1.5 group-hover:scale-105 active:scale-95">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                <span>Lisa korvi</span>
              </button>
            </div>
          </div>

        </div>
      </article>
    `;
  }).join('');
}

// Track chosen size on card
const cardSizeSelection = {};
function selectCardSize(productId, size, btn) {
  cardSizeSelection[productId] = size;
  const pills = document.querySelectorAll(`.size-pill-${productId}`);
  pills.forEach(p => {
    p.classList.remove('bg-navy-900', 'text-white', 'border-navy-900');
    p.classList.add('bg-slate-50', 'text-slate-700', 'border-slate-200');
  });
  btn.classList.add('bg-navy-900', 'text-white', 'border-navy-900');
  btn.classList.remove('bg-slate-50', 'text-slate-700', 'border-slate-200');
}

function getSelectedCardSize(productId, fallback) {
  return cardSizeSelection[productId] || fallback;
}

// Cart Drawer Open / Close
function openCartDrawer() {
  const drawer = document.getElementById('cart-drawer');
  const backdrop = document.getElementById('cart-backdrop');
  const panel = document.getElementById('cart-panel');
  if (!drawer || !panel) return;

  drawer.classList.remove('hidden');
  void drawer.offsetWidth;
  backdrop.classList.remove('opacity-0');
  backdrop.classList.add('opacity-100');
  panel.classList.remove('translate-x-full');
  panel.classList.add('translate-x-0');
  document.body.classList.add('overflow-hidden');
}

function closeCartDrawer() {
  const drawer = document.getElementById('cart-drawer');
  const backdrop = document.getElementById('cart-backdrop');
  const panel = document.getElementById('cart-panel');
  if (!drawer || !panel) return;

  backdrop.classList.remove('opacity-100');
  backdrop.classList.add('opacity-0');
  panel.classList.remove('translate-x-0');
  panel.classList.add('translate-x-full');
  document.body.classList.remove('overflow-hidden');

  setTimeout(() => {
    drawer.classList.add('hidden');
  }, 300);
}

// Render Cart Drawer
function renderCartDrawer() {
  const listEl = document.getElementById('cart-items-list');
  const subtotalEl = document.getElementById('cart-subtotal-price');
  const totalEl = document.getElementById('cart-total-price');
  const emptyState = document.getElementById('cart-empty-state');
  const footerEl = document.getElementById('cart-drawer-footer');

  if (!listEl) return;

  if (CART_STATE.length === 0) {
    listEl.innerHTML = '';
    if (emptyState) emptyState.classList.remove('hidden');
    if (footerEl) footerEl.classList.add('hidden');
    return;
  }

  if (emptyState) emptyState.classList.add('hidden');
  if (footerEl) footerEl.classList.remove('hidden');

  const subtotal = CART_STATE.reduce((sum, item) => sum + (item.price * item.quantity), 0);
  const delivery = subtotal >= 60 ? 0 : 3.50;
  const total = subtotal + (delivery === 0 ? 0 : delivery);

  if (subtotalEl) subtotalEl.textContent = `${subtotal.toFixed(2)} €`;
  if (totalEl) totalEl.textContent = `${total.toFixed(2)} €`;

  const shippingNoteEl = document.getElementById('cart-shipping-note');
  if (shippingNoteEl) {
    if (subtotal >= 60) {
      shippingNoteEl.innerHTML = '<span class="text-emerald-600 font-bold">Tasuta tarne rakendatud!</span>';
    } else {
      const diff = (60 - subtotal).toFixed(2);
      shippingNoteEl.innerHTML = `Osta veel <strong class="text-crimson">${diff} €</strong> eest tasuta tarneks!`;
    }
  }

  listEl.innerHTML = CART_STATE.map(item => `
    <div class="flex items-center gap-4 py-4 border-b border-slate-100 last:border-0">
      <div class="w-16 h-16 rounded-xl bg-slate-100 overflow-hidden flex-shrink-0 border border-slate-200">
        <img src="${item.image}" alt="${item.name}" class="w-full h-full object-cover">
      </div>
      <div class="flex-grow min-w-0">
        <h4 class="font-bold text-sm text-navy-950 truncate">${item.name}</h4>
        <div class="text-xs text-slate-500 mt-0.5">
          Suurus: <span class="font-medium text-slate-700">${item.size}</span>
        </div>
        <div class="flex items-center justify-between mt-2">
          <!-- Quantity Controls -->
          <div class="flex items-center border border-slate-200 rounded-lg bg-slate-50">
            <button type="button" 
                    onclick="adjustQuantity('${item.key}', -1)"
                    class="w-7 h-7 flex items-center justify-center text-slate-500 hover:text-navy-950 transition-colors font-bold text-sm">
              -
            </button>
            <span class="w-7 text-center text-xs font-bold text-slate-800">${item.quantity}</span>
            <button type="button" 
                    onclick="adjustQuantity('${item.key}', 1)"
                    class="w-7 h-7 flex items-center justify-center text-slate-500 hover:text-navy-950 transition-colors font-bold text-sm">
              +
            </button>
          </div>
          
          <!-- Price & Remove -->
          <div class="flex items-center gap-3">
            <span class="font-display font-bold text-sm text-navy-950">
              ${(item.price * item.quantity).toFixed(2)} €
            </span>
            <button type="button" 
                    onclick="removeFromCart('${item.key}')" 
                    class="text-slate-400 hover:text-crimson transition-colors p-1"
                    title="Eemalda toode">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
          </div>
        </div>
      </div>
    </div>
  `).join('');
}

// Quick View Modal
let currentQuickViewProduct = null;
let currentQuickViewSize = null;

function openQuickView(productId) {
  const product = SHOP_PRODUCTS.find(p => p.id === productId);
  if (!product) return;

  currentQuickViewProduct = product;
  currentQuickViewSize = product.sizes[0];

  const modal = document.getElementById('quick-view-modal');
  if (!modal) return;

  // Fill modal content
  document.getElementById('qv-title').textContent = product.name;
  document.getElementById('qv-price').textContent = `${product.price} €`;
  document.getElementById('qv-description').textContent = product.description;
  document.getElementById('qv-category').textContent = product.category === 'riided' ? 'Rõivad' : product.category === 'mutsid' ? 'Peakatted' : 'Aksessuaar';
  
  const mainImg = document.getElementById('qv-main-img');
  mainImg.src = product.primaryImg;
  mainImg.alt = product.name;

  // Thumbnails
  const thumbsContainer = document.getElementById('qv-thumbs');
  if (thumbsContainer) {
    thumbsContainer.innerHTML = product.gallery.map((img, idx) => `
      <button type="button" 
              onclick="selectQuickViewThumb('${img}', this)"
              class="qv-thumb-btn w-14 h-14 sm:w-16 sm:h-16 rounded-xl border-2 ${idx === 0 ? 'border-crimson' : 'border-slate-200'} bg-white overflow-hidden hover:border-crimson focus:outline-none transition-all flex-shrink-0 p-1 flex items-center justify-center cursor-pointer"
              title="Vaata pilti">
        <img src="${img}" alt="" class="max-w-full max-h-full object-contain">
      </button>
    `).join('');
  }

  // Specs
  const specsList = document.getElementById('qv-specs-list');
  if (specsList) {
    specsList.innerHTML = product.details.map(d => `
      <li class="flex items-start gap-2 text-xs text-slate-600">
        <svg class="w-4 h-4 text-emerald-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <span>${d}</span>
      </li>
    `).join('');
  }

  // Sizes
  const sizesContainer = document.getElementById('qv-sizes');
  if (sizesContainer) {
    sizesContainer.innerHTML = product.sizes.map((s, idx) => `
      <button type="button" 
              onclick="setQuickViewSize('${s}', this)"
              class="qv-size-btn px-3 py-1.5 rounded-lg border text-xs font-bold transition-all ${idx === 0 ? 'bg-navy-900 text-white border-navy-900' : 'bg-slate-50 text-slate-700 border-slate-200 hover:border-slate-400'}">
        ${s}
      </button>
    `).join('');
  }

  modal.classList.remove('hidden');
  document.body.classList.add('overflow-hidden');
}

function setQuickViewSize(size, btn) {
  currentQuickViewSize = size;
  const allBtns = document.querySelectorAll('.qv-size-btn');
  allBtns.forEach(b => {
    b.classList.remove('bg-navy-900', 'text-white', 'border-navy-900');
    b.classList.add('bg-slate-50', 'text-slate-700', 'border-slate-200');
  });
  btn.classList.add('bg-navy-900', 'text-white', 'border-navy-900');
  btn.classList.remove('bg-slate-50', 'text-slate-700', 'border-slate-200');
}

function selectQuickViewThumb(imgSrc, btn) {
  const mainImg = document.getElementById('qv-main-img');
  if (mainImg) {
    mainImg.src = imgSrc;
  }
  const allThumbs = document.querySelectorAll('.qv-thumb-btn');
  allThumbs.forEach(t => {
    t.classList.remove('border-crimson');
    t.classList.add('border-slate-200');
  });
  if (btn) {
    btn.classList.remove('border-slate-200');
    btn.classList.add('border-crimson');
  }
}

function addQuickViewToCart() {
  if (!currentQuickViewProduct) return;
  const qtyInput = document.getElementById('qv-quantity');
  const qty = qtyInput ? parseInt(qtyInput.value) || 1 : 1;

  addToCart(currentQuickViewProduct.id, currentQuickViewSize, null, qty);
  closeQuickView();
}

function closeQuickView() {
  const modal = document.getElementById('quick-view-modal');
  if (modal) {
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
  }
}

// Checkout simulation modal
function openCheckoutModal() {
  if (CART_STATE.length === 0) {
    if (window.showToast) window.showToast('Sinu ostukorv on tühi!');
    return;
  }
  closeCartDrawer();
  
  const modal = document.getElementById('checkout-modal');
  if (!modal) return;

  const subtotal = CART_STATE.reduce((sum, item) => sum + (item.price * item.quantity), 0);
  const delivery = subtotal >= 60 ? 0 : 3.50;
  const total = subtotal + delivery;

  const totalEl = document.getElementById('checkout-modal-total');
  if (totalEl) totalEl.textContent = `${total.toFixed(2)} €`;

  modal.classList.remove('hidden');
  document.body.classList.add('overflow-hidden');
}

function closeCheckoutModal() {
  const modal = document.getElementById('checkout-modal');
  if (modal) {
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
  }
}

function submitCheckoutOrder(e) {
  e.preventDefault();
  const form = e.target;
  const nameInput = form.querySelector('[name="customer_name"]')?.value || 'Klient';
  
  // Clear cart
  CART_STATE = [];
  saveCartToStorage();
  renderCartDrawer();
  closeCheckoutModal();

  // Show success modal or toast
  const successModal = document.getElementById('order-success-modal');
  if (successModal) {
    document.getElementById('success-customer-name').textContent = nameInput;
    successModal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
  } else if (window.showToast) {
    window.showToast(`Suur aitäh, ${nameInput}! Tellimus on edukalt vastu võetud.`);
  }
}

// Initializer
document.addEventListener('DOMContentLoaded', () => {
  loadCartFromStorage();
  renderCatalog('all', 'default');

  // Filter Buttons
  const filterBtns = document.querySelectorAll('.shop-filter-btn');
  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      filterBtns.forEach(b => {
        b.classList.remove('bg-navy-950', 'text-white', 'shadow-sm');
        b.classList.add('bg-white', 'text-slate-600', 'border-slate-200');
      });
      btn.classList.add('bg-navy-950', 'text-white', 'shadow-sm');
      btn.classList.remove('bg-white', 'text-slate-600', 'border-slate-200');

      const filterType = btn.getAttribute('data-filter') || 'all';
      const sortSelect = document.getElementById('shop-sort-select');
      renderCatalog(filterType, sortSelect ? sortSelect.value : 'default');
    });
  });

  // Sort Dropdown
  const sortSelect = document.getElementById('shop-sort-select');
  if (sortSelect) {
    sortSelect.addEventListener('change', () => {
      const activeFilterBtn = document.querySelector('.shop-filter-btn.bg-navy-950');
      const filterType = activeFilterBtn ? activeFilterBtn.getAttribute('data-filter') : 'all';
      renderCatalog(filterType, sortSelect.value);
    });
  }

  // Cart Button triggers
  const cartTriggers = document.querySelectorAll('.cart-open-trigger');
  cartTriggers.forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      openCartDrawer();
    });
  });

  const cartCloseBtn = document.getElementById('cart-close-btn');
  if (cartCloseBtn) cartCloseBtn.addEventListener('click', closeCartDrawer);

  const cartBackdrop = document.getElementById('cart-backdrop');
  if (cartBackdrop) cartBackdrop.addEventListener('click', closeCartDrawer);

  // Quick view triggers
  const qvCloseBtn = document.getElementById('qv-close-btn');
  if (qvCloseBtn) qvCloseBtn.addEventListener('click', closeQuickView);

  // Close modals on escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeCartDrawer();
      closeQuickView();
      closeCheckoutModal();
    }
  });
});

// Window globals for inline onclicks
window.addToCart = addToCart;
window.removeFromCart = removeFromCart;
window.adjustQuantity = adjustQuantity;
window.openCartDrawer = openCartDrawer;
window.closeCartDrawer = closeCartDrawer;
window.openQuickView = openQuickView;
window.closeQuickView = closeQuickView;
window.selectQuickViewThumb = selectQuickViewThumb;
window.setQuickViewSize = setQuickViewSize;
window.addQuickViewToCart = addQuickViewToCart;
window.selectCardSize = selectCardSize;
window.getSelectedCardSize = getSelectedCardSize;
window.openCheckoutModal = openCheckoutModal;
window.closeCheckoutModal = closeCheckoutModal;
window.submitCheckoutOrder = submitCheckoutOrder;
