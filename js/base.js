/* ═══════════════════════════════════════════════════════════
   BLING NETWORK — BASE JAVASCRIPT
   Shared across all pages: cursor, nav scroll, scroll reveal
   ═══════════════════════════════════════════════════════════ */

/* ── CURSOR ──────────────────────────────────────────────── */
const dot = document.getElementById('cursor-dot');

if (dot) {
  document.addEventListener('mousemove', e => {
    dot.style.left = e.clientX + 'px';
    dot.style.top  = e.clientY + 'px';
  });

  /* Elements that trigger the hover state on the cursor.
     Each page can call addCursorHover() to extend this list
     with page-specific selectors after the DOM is ready.    */
  function initCursorHover(selector) {
    document.querySelectorAll(selector).forEach(el => {
      el.addEventListener('mouseenter', () => document.body.classList.add('cursor-hover'));
      el.addEventListener('mouseleave', () => document.body.classList.remove('cursor-hover'));
    });
  }

  /* Base selectors present on every page */
  initCursorHover('a, button');
}

/* ── NAV SCROLL ──────────────────────────────────────────── */
const mainNav = document.getElementById('main-nav');

if (mainNav) {
  window.addEventListener('scroll', () => {
    mainNav.classList.toggle('scrolled', window.scrollY > 40);
  }, { passive: true });
}

/* ── SCROLL REVEAL ───────────────────────────────────────── */
const revealObserver = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.classList.add('visible');
      revealObserver.unobserve(e.target);
    }
  });
}, { threshold: .12 });

/* Observe all base reveal elements.
   Page scripts can call observeReveal(el) to add extra elements. */
function observeReveal(el) {
  revealObserver.observe(el);
}

document.querySelectorAll('.reveal').forEach(el => observeReveal(el));