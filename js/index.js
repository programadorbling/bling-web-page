/* ═══════════════════════════════════════════════════════════
   BLING NETWORK — INDEX PAGE JAVASCRIPT
   Exclusive logic for index.html
   Requires: js/base.js
   ═══════════════════════════════════════════════════════════ */

/* ── EXTEND CURSOR HOVER to page-specific elements ───────── */
initCursorHover('.countdown-card, .brand-card');

/* ── OBSERVE PAGE-SPECIFIC REVEAL ELEMENTS ───────────────── */
document.querySelectorAll('.brand-card').forEach(el => observeReveal(el));

/* ── TYPEWRITER ──────────────────────────────────────────── */
const phrases = [
  'A new era of logistics.',
  'Moving freight. Building futures.',
  'Your global network starts here.',
  'Connecting every corner of the world.'
];

let pi = 0, ci = 0, deleting = false;
const tw = document.getElementById('typewriter');

if (tw) {
  function type() {
    const phrase = phrases[pi];
    if (!deleting) {
      tw.textContent = phrase.slice(0, ci + 1);
      ci++;
      if (ci === phrase.length) {
        deleting = true;
        setTimeout(type, 2200);
        return;
      }
    } else {
      tw.textContent = phrase.slice(0, ci - 1);
      ci--;
      if (ci === 0) {
        deleting = false;
        pi = (pi + 1) % phrases.length;
        setTimeout(type, 400);
        return;
      }
    }
    setTimeout(type, deleting ? 45 : 70);
  }
  setTimeout(type, 1800);
}

/* ── COUNTDOWN ───────────────────────────────────────────── */
const countdownTarget = new Date('2026-05-11T08:00:00');
const cdEls = {
  d: document.getElementById('cd-days'),
  h: document.getElementById('cd-hours'),
  m: document.getElementById('cd-mins'),
  s: document.getElementById('cd-secs')
};

function pad(n) {
  return String(n).padStart(2, '0');
}

function flipEl(el, val) {
  if (!el) return;
  const next = pad(val);
  if (el.textContent !== next) {
    el.textContent = next;
    el.classList.remove('countdown-flip');
    void el.offsetWidth; /* reflow to restart animation */
    el.classList.add('countdown-flip');
  }
}

function tick() {
  const diff = countdownTarget - new Date();
  if (diff <= 0) {
    Object.values(cdEls).forEach(e => { if (e) e.textContent = '00'; });
    return;
  }
  flipEl(cdEls.d, Math.floor(diff / 864e5));
  flipEl(cdEls.h, Math.floor((diff % 864e5) / 36e5));
  flipEl(cdEls.m, Math.floor((diff % 36e5)  / 6e4));
  flipEl(cdEls.s, Math.floor((diff % 6e4)   / 1e3));
}

if (cdEls.d || cdEls.h || cdEls.m || cdEls.s) {
  tick();
  setInterval(tick, 1000);
}

/* ── HERO PARALLAX (scroll + mouse) ─────────────────────── */
const heroBg   = document.querySelector('.geo-bg');
const heroEl   = document.getElementById('hero');
const heroLogo = document.querySelector('.hero-logo-wrap');
const heroHead = document.querySelector('.hero-headline');

/* Scroll parallax */
if (heroLogo || heroHead) {
  window.addEventListener('scroll', () => {
    const y = window.scrollY;
    if (heroLogo) heroLogo.style.transform = `translateY(${y * .12}px)`;
    if (heroHead) heroHead.style.transform  = `translateY(${y * .06}px)`;
  }, { passive: true });
}

/* Mouse parallax */
if (heroEl && heroBg) {
  heroEl.addEventListener('mousemove', e => {
    const { left, top, width, height } = heroEl.getBoundingClientRect();
    const x = (e.clientX - left  - width  / 2) / width;
    const y = (e.clientY - top   - height / 2) / height;
    heroBg.style.transform = `translate(${x * 18}px, ${y * 12}px)`;
  });

  heroEl.addEventListener('mouseleave', () => {
    heroBg.style.transform = 'translate(0, 0)';
  });
}

/* ── JOIN SECTION: connected nodes animation ─────────────── */
function runNodesAnimation() {
  const nodes      = ['jnode-1', 'jnode-2', 'jnode-3'].map(id => document.getElementById(id));
  const connectors = ['jconnector-1', 'jconnector-2'].map(id => document.getElementById(id));
  const underline  = document.querySelector('.join-title-underline');

  /* Reset */
  nodes.forEach(n      => n && n.classList.remove('active'));
  connectors.forEach(c => c && c.classList.remove('active'));

  /* Sequence: node1 → line1 → node2 → line2 → node3 */
  setTimeout(() => nodes[0]      && nodes[0].classList.add('active'),      300);
  setTimeout(() => connectors[0] && connectors[0].classList.add('active'), 900);
  setTimeout(() => nodes[1]      && nodes[1].classList.add('active'),      1400);
  setTimeout(() => connectors[1] && connectors[1].classList.add('active'), 2000);
  setTimeout(() => nodes[2]      && nodes[2].classList.add('active'),      2500);
  if (underline) setTimeout(() => underline.classList.add('visible'),      400);
}

const joinSection = document.getElementById('join');

if (joinSection) {
  const joinObserver = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        runNodesAnimation();

        /* Loop every 4.5s while section is in view */
        const loop = setInterval(() => {
          if (!document.getElementById('join')) return clearInterval(loop);
          runNodesAnimation();
        }, 4500);

        joinObserver.unobserve(e.target);
      }
    });
  }, { threshold: .3 });

  joinObserver.observe(joinSection);
}