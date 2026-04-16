/* ═══════════════════════════════════════════════════════════
   BLING NETWORK — BENEFITS PAGE JAVASCRIPT
   Exclusive logic for benefits.html
   Requires: js/base.js
   ═══════════════════════════════════════════════════════════ */

/* ── EXTEND CURSOR HOVER ─────────────────────────────────── */
initCursorHover('.bene-card, .ben-pill, .bene-nav-btn');

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   BENEFITS CAROUSEL
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */

/* ── Data: panel content per benefit key ─────────────────── */
const BENEFIT_PANELS = {
  leads: {
    kicker:  'Sales Leads',
    title:   'Turn connections into contracts.',
    body:    'Our intuitive tools empower members to <strong>collaborate and generate sales</strong>, driving business growth. Find the right partner for every shipment, in every corridor — before your competitors do.',
    tag:     '✓ Included with membership',
    link:    null,
    linkTxt: null,
    /* ── IMAGE URL ──────────────────────────────────────────
       Replace null with the image URL for this benefit.
       Recommended: horizontal image (4:3 ratio)
    ─────────────────────────────────────────────────────── */
    image:   null,
    imgAlt:  'Sales Leads'
  },
  tools: {
    kicker:  'Platform & App',
    title:   'Your network, in your pocket.',
    body:    'Experience secure, reliable logistics through our <strong>advanced platform and mobile app</strong>. Built for freight forwarders — designed to make every interaction faster, clearer, and more connected.',
    tag:     '✓ App & Web Platform',
    link:    null,
    linkTxt: null,
    /* ── IMAGE URL ── Replace null with actual image URL ── */
    image:   null,
    imgAlt:  'Bling Platform & App'
  },
  payment: {
    kicker:  'Payment Protection',
    title:   '$10,000 protection on every deal.',
    body:    'Our Payment Protection Plan covers up to <strong>$10,000 if a member can\'t pay</strong>, so you stay protected. Do business with confidence knowing every transaction has a safety net.',
    tag:     '✓ Financial Protection',
    link:    'https://blinglogisticsnetwork.com/payment-protection/',
    linkTxt: 'See Full Plan Details',
    /* ── IMAGE URL ── Replace null with actual image URL ── */
    image:   null,
    imgAlt:  'Payment Protection Plan'
  },
  summits: {
    kicker:  'Bling Summits',
    title:   'Where real business gets done.',
    body:    'The Bling Summits are a <strong>valuable opportunity for members to connect and build lasting partnerships</strong>. Annual events designed to generate real business — not just networking.',
    tag:     '✓ 7 editions · 48+ countries',
    link:    null,
    linkTxt: null,
    /* ── IMAGE URL ── Replace null with actual image URL ── */
    image:   null,
    imgAlt:  'Bling Summit'
  },
  exchange: {
    kicker:  'Business Exchange',
    title:   'Live deals across the network.',
    body:    '<strong>Real-time deals, global growth, trusted partnerships</strong>, and smarter decision making. Access live business opportunities across the entire Bling network as they happen.',
    tag:     '✓ Live opportunities',
    link:    null,
    linkTxt: null,
    /* ── IMAGE URL ── Replace null with actual image URL ── */
    image:   null,
    imgAlt:  'Business Exchange'
  },
  marketing: {
    kicker:  'Marketing & PR',
    title:   'Your brand, on the global stage.',
    body:    'Bling elevates your brand visibility through <strong>global campaigns, media features, and partner promotions</strong> — all included with your membership. Get seen by the right people, in the right markets.',
    tag:     '✓ Global exposure',
    link:    null,
    linkTxt: null,
    /* ── IMAGE URL ── Replace null with actual image URL ── */
    image:   null,
    imgAlt:  'Marketing & PR'
  },
  fidelity: {
    kicker:  'Fidelity Program',
    title:   'Refer agents. Earn USD bonuses.',
    body:    'Earn USD bonuses for <strong>every agent you refer who joins our network</strong>. Grow our community, grow your rewards. There\'s no cap — the more you share Bling, the more you earn.',
    tag:     '✓ Referral bonuses',
    link:    'https://wa.link/qsa8vq',
    linkTxt: 'Ask Us for Details',
    /* ── IMAGE URL ── Replace null with actual image URL ── */
    image:   null,
    imgAlt:  'Fidelity Program'
  }
};

/* ── DOM references ──────────────────────────────────────── */
const track    = document.querySelector('.bene-carousel-track');
const panel    = document.querySelector('.bene-panel');
const cards    = document.querySelectorAll('.bene-card[data-benefit]');
const btnPrev  = document.getElementById('bene-prev');
const btnNext  = document.getElementById('bene-next');
const dotsWrap = document.querySelector('.bene-nav-dots');

/* ── Observe cards for entrance animation ────────────────── */
cards.forEach((card, i) => {
  card.style.animationDelay = (i * .08) + 's';
  observeReveal(card);
});

/* ── Build panel HTML ────────────────────────────────────── */
function buildPanel(key) {
  const d = BENEFIT_PANELS[key];
  if (!d) return '';

  const isPayment = key === 'payment';

  const imageHTML = d.image
    ? `<img src="${d.image}" alt="${d.imgAlt}" loading="lazy">`
    : `<div class="bene-panel-img-placeholder">
         <div class="panel-placeholder-icon">
           <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
             <rect x="3" y="3" width="18" height="18" rx="2"/>
             <circle cx="8.5" cy="8.5" r="1.5"/>
             <path d="M21 15l-5-5L5 21"/>
           </svg>
         </div>
         <span class="panel-placeholder-label">${d.imgAlt}</span>
       </div>`;

  const paymentExtra = isPayment
    ? `<span class="panel-payment-amount">$10,000</span>
       <span class="panel-payment-label">Maximum coverage per claim</span>`
    : '';

  const linkHTML = d.link
    ? `<a href="${d.link}" target="_blank" class="bene-panel-link">
         ${d.linkTxt}
         <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
           <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
         </svg>
       </a>`
    : '';

  return `
    <div class="bene-panel-inner">
      <div class="bene-panel-text">
        <span class="bene-panel-kicker">${d.kicker}</span>
        <h3 class="bene-panel-title">${d.title}</h3>
        ${paymentExtra}
        <p class="bene-panel-body">${d.body}</p>
        <span class="bene-panel-tag">${d.tag}</span>
        ${linkHTML}
      </div>
      <div class="bene-panel-image">${imageHTML}</div>
    </div>`;
}

/* ── Open / close panel ──────────────────────────────────── */
let activeKey = null;

function openPanel(key, card) {
  if (activeKey === key) {
    closePanel();
    return;
  }

  cards.forEach(c => c.classList.remove('active'));
  card.classList.add('active');
  activeKey = key;

  panel.className = `bene-panel${key === 'payment' ? ' payment-panel' : ''}`;
  panel.innerHTML = buildPanel(key);

  panel.querySelectorAll('a').forEach(el => {
    el.addEventListener('mouseenter', () => document.body.classList.add('cursor-hover'));
    el.addEventListener('mouseleave', () => document.body.classList.remove('cursor-hover'));
  });

  requestAnimationFrame(() => {
    requestAnimationFrame(() => panel.classList.add('open'));
  });

  card.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
}

function closePanel() {
  panel.classList.remove('open');
  cards.forEach(c => c.classList.remove('active'));
  activeKey = null;
  setTimeout(() => { if (!panel.classList.contains('open')) panel.innerHTML = ''; }, 560);
}

/* ── Card click delegation ───────────────────────────────── */
if (track) {
  track.addEventListener('click', e => {
    if (track.classList.contains('is-dragging')) return;
    const card = e.target.closest('.bene-card[data-benefit]');
    if (card) openPanel(card.dataset.benefit, card);
  });
}

/* ── Arrow navigation ────────────────────────────────────── */
function scrollCarousel(dir) {
  if (!track) return;
  const cardW = track.querySelector('.bene-card')?.offsetWidth || 200;
  track.scrollBy({ left: dir * (cardW + 16) * 2, behavior: 'smooth' });
}

if (btnPrev) btnPrev.addEventListener('click', () => scrollCarousel(-1));
if (btnNext) btnNext.addEventListener('click', () => scrollCarousel(1));

/* ── Progress dots ───────────────────────────────────────── */
function updateDots() {
  if (!track || !dotsWrap) return;
  const ratio   = track.scrollLeft / (track.scrollWidth - track.clientWidth);
  const total   = dotsWrap.querySelectorAll('.bene-nav-dot').length;
  const activeI = Math.round(ratio * (total - 1));
  dotsWrap.querySelectorAll('.bene-nav-dot').forEach((dot, i) => {
    dot.classList.toggle('active', i === activeI);
  });
}

if (track) track.addEventListener('scroll', updateDots, { passive: true });

if (dotsWrap) {
  dotsWrap.querySelectorAll('.bene-nav-dot').forEach((dot, i, all) => {
    dot.addEventListener('click', () => {
      const ratio = i / (all.length - 1);
      track.scrollTo({ left: ratio * (track.scrollWidth - track.clientWidth), behavior: 'smooth' });
    });
  });
}

/* ── Drag to scroll ──────────────────────────────────────── */
if (track) {
  let isDragging = false, startX = 0, scrollStart = 0;

  track.addEventListener('mousedown', e => {
    isDragging  = true;
    startX      = e.clientX;
    scrollStart = track.scrollLeft;
    track.classList.add('is-dragging');
  });

  document.addEventListener('mousemove', e => {
    if (!isDragging) return;
    track.scrollLeft = scrollStart - (e.clientX - startX);
  });

  document.addEventListener('mouseup', () => {
    if (!isDragging) return;
    isDragging = false;
    setTimeout(() => track.classList.remove('is-dragging'), 50);
  });
}

/* ── Close panel on outside click ───────────────────────── */
document.addEventListener('click', e => {
  if (!activeKey) return;
  if (!e.target.closest('.bene-carousel-outer') && !e.target.closest('.bene-panel')) {
    closePanel();
  }
});

/* ── Close panel on Escape ───────────────────────────────── */
document.addEventListener('keydown', e => {
  if (e.key === 'Escape' && activeKey) closePanel();
});

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   FIDELITY STEPS ANIMATION
   Runs when #fidelity-highlight enters viewport
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
function runFidelityAnimation() {
  const steps      = document.querySelectorAll('.fidelity-step');
  const connectors = document.querySelectorAll('.fstep-connector');

  steps.forEach(s      => s.classList.remove('active'));
  connectors.forEach(c => c.classList.remove('active'));

  setTimeout(() => steps[0]      && steps[0].classList.add('active'),      300);
  setTimeout(() => connectors[0] && connectors[0].classList.add('active'), 900);
  setTimeout(() => steps[1]      && steps[1].classList.add('active'),      1400);
  setTimeout(() => connectors[1] && connectors[1].classList.add('active'), 2000);
  setTimeout(() => steps[2]      && steps[2].classList.add('active'),      2500);
}

const fidelitySection = document.getElementById('fidelity-highlight');

if (fidelitySection) {
  const fidelityObserver = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        runFidelityAnimation();
        const loop = setInterval(() => {
          if (!document.getElementById('fidelity-highlight')) return clearInterval(loop);
          runFidelityAnimation();
        }, 4500);
        fidelityObserver.unobserve(e.target);
      }
    });
  }, { threshold: .3 });

  fidelityObserver.observe(fidelitySection);
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   HERO PARALLAX
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
const benGeo  = document.querySelector('.benefits-geo');
const benHero = document.getElementById('benefits-hero');

if (benHero && benGeo) {
  benHero.addEventListener('mousemove', e => {
    const { left, top, width, height } = benHero.getBoundingClientRect();
    const x = (e.clientX - left  - width  / 2) / width;
    const y = (e.clientY - top   - height / 2) / height;
    benGeo.style.transform = `translate(${x * 16}px, ${y * 10}px)`;
  });

  benHero.addEventListener('mouseleave', () => {
    benGeo.style.transform = 'translate(0, 0)';
  });
}

/* Hero headline scroll parallax */
const benHeadline = document.querySelector('.ben-hero-headline');
if (benHeadline) {
  window.addEventListener('scroll', () => {
    benHeadline.style.transform = `translateY(${window.scrollY * .05}px)`;
  }, { passive: true });
}