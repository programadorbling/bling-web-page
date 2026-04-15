/* ═══════════════════════════════════════════════════════════
   BLING NETWORK — ABOUT PAGE JAVASCRIPT
   Exclusive logic for about.html
   Requires: js/base.js
   ═══════════════════════════════════════════════════════════ */

/* ── EXTEND CURSOR HOVER to page-specific elements ───────── */
initCursorHover('.stat-card, .benefit-card, .badge-card, .mission-pillar');

/* ── OBSERVE PAGE-SPECIFIC REVEAL ELEMENTS ───────────────── */
document.querySelectorAll('.benefit-card, .badge-card').forEach(el => observeReveal(el));

/* ── COUNTER ROLL ANIMATION ──────────────────────────────── */
function easeOutCubic(t) {
  return 1 - Math.pow(1 - t, 3);
}

function animateCounter(el, target, duration) {
  const start = performance.now();
  function step(now) {
    const elapsed  = now - start;
    const progress = Math.min(elapsed / duration, 1);
    const eased    = easeOutCubic(progress);
    el.textContent = Math.round(eased * target);
    if (progress < 1) requestAnimationFrame(step);
    else el.textContent = target;
  }
  requestAnimationFrame(step);
}

const statsSection = document.getElementById('stats');

if (statsSection) {
  const statsObserver = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.querySelectorAll('.stat-number[data-target]').forEach(numEl => {
          const target = parseInt(numEl.dataset.target, 10);
          animateCounter(numEl, target, 1800);
        });
        statsObserver.unobserve(e.target);
      }
    });
  }, { threshold: .3 });

  statsObserver.observe(statsSection);
}

/* ── BADGE TILT 3D ───────────────────────────────────────── */
document.querySelectorAll('.badge-card').forEach(card => {
  const wrap = card.querySelector('.badge-img-wrap');
  if (!wrap) return;

  card.addEventListener('mousemove', e => {
    const rect = wrap.getBoundingClientRect();
    const x = (e.clientX - rect.left  - rect.width  / 2) / (rect.width  / 2);
    const y = (e.clientY - rect.top   - rect.height / 2) / (rect.height / 2);
    wrap.style.transform = `rotate(0deg) scale(1.06) perspective(600px) rotateY(${x * 12}deg) rotateX(${-y * 10}deg)`;
  });

  card.addEventListener('mouseleave', () => {
    wrap.style.transform = '';
  });
});

/* ── HERO MOUSE PARALLAX ─────────────────────────────────── */
const heroBg      = document.querySelector('.hero-geo');
const heroSection = document.getElementById('about-hero');

if (heroSection && heroBg) {
  heroSection.addEventListener('mousemove', e => {
    const { left, top, width, height } = heroSection.getBoundingClientRect();
    const x = (e.clientX - left  - width  / 2) / width;
    const y = (e.clientY - top   - height / 2) / height;
    heroBg.style.transform = `translate(${x * 16}px, ${y * 10}px)`;
  });

  heroSection.addEventListener('mouseleave', () => {
    heroBg.style.transform = 'translate(0, 0)';
  });
}

/* ── HERO SCROLL PARALLAX ────────────────────────────────── */
const heroHeadline = document.querySelector('.hero-headline');

if (heroHeadline) {
  window.addEventListener('scroll', () => {
    heroHeadline.style.transform = `translateY(${window.scrollY * .06}px)`;
  }, { passive: true });
}