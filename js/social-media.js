/* ═══════════════════════════════════════════════════════════
   BLING NETWORK — SOCIAL MEDIA PAGE JAVASCRIPT
   Exclusive logic for social-media.html
   Requires: js/base.js
   ═══════════════════════════════════════════════════════════ */

/* ── CURSOR RING WITH INERTIA ────────────────────────────── */
/* This page uses a lagging ring cursor on top of the base dot */
const ring = document.getElementById('cursor-ring');

if (ring) {
  let mx = 0, my = 0; /* mouse target position  */
  let rx = 0, ry = 0; /* ring current position  */

  /* Update mouse target on move (dot is handled by base.js) */
  document.addEventListener('mousemove', e => {
    mx = e.clientX;
    my = e.clientY;
  });

  /* Animate ring with lerp (linear interpolation) for lag effect */
  (function animateRing() {
    rx += (mx - rx) * .12;
    ry += (my - ry) * .12;
    ring.style.left = rx + 'px';
    ring.style.top  = ry + 'px';
    requestAnimationFrame(animateRing);
  })();
}

/* ── EXTEND CURSOR HOVER to page-specific elements ───────── */
initCursorHover('.hub-logo, .soc-icon, .link-btn');

/* ── STAGGER LINK BUTTONS ON SCROLL ENTER ────────────────── */
const linkBtns = document.querySelectorAll('.link-btn');

if (linkBtns.length) {
  const btnObserver = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.classList.add('visible');
        btnObserver.unobserve(e.target);
      }
    });
  }, { threshold: .1 });

  linkBtns.forEach((btn, i) => {
    btn.style.animationDelay = (1.1 + i * .12) + 's';
    btnObserver.observe(btn);
  });
}

/* ── BACKGROUND RINGS MOUSE PARALLAX ─────────────────────── */
const bgRing1 = document.querySelector('.bg-ring-1');
const bgRing2 = document.querySelector('.bg-ring-2');

if (bgRing1 || bgRing2) {
  document.addEventListener('mousemove', e => {
    const x = (e.clientX / window.innerWidth  - .5) * 20;
    const y = (e.clientY / window.innerHeight - .5) * 14;

    if (bgRing1) bgRing1.style.transform = `translate(calc(-50% + ${x}px),  calc(-50% + ${y}px))`;
    if (bgRing2) bgRing2.style.transform = `translate(calc(-50% + ${-x * .7}px), calc(-50% + ${-y * .7}px))`;
  });
}