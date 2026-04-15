/* ═══════════════════════════════════════════════════════════
   BLING NETWORK — BENEFITS PAGE JAVASCRIPT
   Exclusive logic for benefits.html
   Requires: js/base.js
   ═══════════════════════════════════════════════════════════ */

/* ── EXTEND CURSOR HOVER ─────────────────────────────────── */
initCursorHover('.bene-card, .ben-pill, .profile-btn');

/* ── OBSERVE BENEFIT CARDS ───────────────────────────────── */
document.querySelectorAll('.bene-card').forEach(el => observeReveal(el));

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   PROFILE SELECTOR TOGGLE
   Highlights relevant cards based on member vs prospect view
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */

/* Cards to feature per profile. Values match data-benefit attrs */
const PROFILE_FEATURES = {
  member:   ['tools', 'payment', 'fidelity', 'marketing'],
  prospect: ['leads', 'summits', 'exchange', 'payment']
};

const profileBtns  = document.querySelectorAll('.profile-btn');
const beneCards    = document.querySelectorAll('.bene-card[data-benefit]');
const indicator    = document.querySelector('.profile-toggle-indicator');
let   activeProfile = null;

function setIndicator(btn) {
  if (!indicator || !btn) return;
  const parent = btn.closest('.profile-toggle');
  const pRect  = parent.getBoundingClientRect();
  const bRect  = btn.getBoundingClientRect();
  indicator.style.width  = bRect.width  + 'px';
  indicator.style.transform = `translateX(${bRect.left - pRect.left - 5}px)`;
}

function applyProfile(profile) {
  if (activeProfile === profile) {
    /* Clicking the same profile resets to neutral */
    activeProfile = null;
    beneCards.forEach(card => {
      card.classList.remove('dimmed', 'featured');
    });
    profileBtns.forEach(b => b.classList.remove('active'));
    if (indicator) { indicator.style.width = '0'; indicator.style.transform = 'translateX(0)'; }
    return;
  }

  activeProfile = profile;
  const featured = PROFILE_FEATURES[profile] || [];

  beneCards.forEach(card => {
    const key = card.dataset.benefit;
    if (featured.includes(key)) {
      card.classList.add('featured');
      card.classList.remove('dimmed');
    } else {
      card.classList.add('dimmed');
      card.classList.remove('featured');
    }
  });

  profileBtns.forEach(b => {
    b.classList.toggle('active', b.dataset.profile === profile);
  });

  /* Move indicator pill */
  const activeBtn = document.querySelector(`.profile-btn[data-profile="${profile}"]`);
  setIndicator(activeBtn);
}

profileBtns.forEach(btn => {
  btn.addEventListener('click', () => applyProfile(btn.dataset.profile));
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
