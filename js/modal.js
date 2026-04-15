/* ═══════════════════════════════════════════════════════════
   BLING NETWORK — EVENT MODAL JAVASCRIPT
   Handles event badge click → modal open/close
   Requires: js/base.js
   ═══════════════════════════════════════════════════════════ */

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   EVENT DATA STORE
   Each event has: title, edition, location, flag (SVG string),
   metrics (array of {value, label}), images (array of URLs or
   null for placeholder), and a closing message.

   TO ADD REAL IMAGES: Replace null with the image URL string
   in each event's images array. Example:
     images: [
       'https://your-cdn.com/event-las-vegas-main.jpg',
       'https://your-cdn.com/event-las-vegas-2.jpg',
       'https://your-cdn.com/event-las-vegas-3.jpg'
     ]
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
const EVENT_DATA = {

  'las-vegas-2019': {
    title: 'Las Vegas 2019',
    edition: '1st Edition',
    location: 'Las Vegas, Nevada, USA',
    flag: `<svg viewBox="0 0 30 20" xmlns="http://www.w3.org/2000/svg" width="36" height="24" style="border-radius:3px">
      <rect width="30" height="20" fill="#B22234"/>
      <rect y="1.54" width="30" height="1.54" fill="white"/>
      <rect y="4.62" width="30" height="1.54" fill="white"/>
      <rect y="7.69" width="30" height="1.54" fill="white"/>
      <rect y="10.77" width="30" height="1.54" fill="white"/>
      <rect y="13.85" width="30" height="1.54" fill="white"/>
      <rect y="16.92" width="30" height="1.54" fill="white"/>
      <rect width="12" height="10.77" fill="#3C3B6E"/>
    </svg>`,
    metrics: [
      { value: '120+', label: 'Attendees' },
      { value: '18',   label: 'Countries' },
      { value: '3',    label: 'Days' },
      { value: '24',   label: 'Speakers' },
      { value: '95%',  label: 'Satisfaction' }
    ],
    /* ── IMAGE URLS ──────────────────────────────────────────
       Replace null with actual image URL strings.
       images[0] = main/hero image (full width)
       images[1] = secondary image (left column)
       images[2] = secondary image (right column)
    ─────────────────────────────────────────────────────── */
    images: [ null, null, null ],
    message: 'Our inaugural summit brought together <strong>independent freight forwarders from 18 countries</strong> for the first time under the Bling banner. Las Vegas set the tone for what this network would become — a community built on trust, real conversations, and business that actually moves.'
  },

  'digital-2020': {
    title: 'Digital Conference 2020',
    edition: 'Virtual Edition',
    location: 'Online — Global',
    flag: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="36" height="36" style="color:#d1d70d">
      <circle cx="12" cy="12" r="10"/>
      <path d="M2 12h20M12 2a15.3 15.3 0 010 20M12 2a15.3 15.3 0 000 20"/>
      <circle cx="12" cy="12" r="2.5" fill="#d1d70d" stroke="none"/>
    </svg>`,
    metrics: [
      { value: '200+', label: 'Attendees' },
      { value: '30',   label: 'Countries' },
      { value: '2',    label: 'Days' },
      { value: '18',   label: 'Sessions' },
      { value: '100%', label: 'Digital' }
    ],
    /* ── IMAGE URLS ── Replace null with actual image URLs ── */
    images: [ null, null, null ],
    message: 'When the world stopped, Bling adapted. Our first digital conference broke every attendance record, connecting <strong>over 200 freight forwarders across 30 countries</strong> in real time. It proved that distance is no barrier to building real business relationships.'
  },

  'digital-2021': {
    title: '2nd Digital Conference 2021',
    edition: '2nd Virtual Edition',
    location: 'Online — Global',
    flag: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="36" height="36" style="color:#d1d70d">
      <rect x="2" y="3" width="20" height="14" rx="2"/>
      <path d="M8 21h8M12 17v4"/>
      <path d="M9 9.5a3 3 0 016 0" stroke="#d1d70d" stroke-opacity=".6"/>
      <circle cx="12" cy="12" r="1.5" fill="#d1d70d" stroke="none"/>
    </svg>`,
    metrics: [
      { value: '250+', label: 'Attendees' },
      { value: '35',   label: 'Countries' },
      { value: '2',    label: 'Days' },
      { value: '22',   label: 'Sessions' },
      { value: '4.8',  label: 'Avg Rating' }
    ],
    /* ── IMAGE URLS ── Replace null with actual image URLs ── */
    images: [ null, null, null ],
    message: 'Building on the momentum of 2020, our second virtual edition <strong>grew the network by 40%</strong>. New breakout formats, more targeted matchmaking sessions, and the foundation of what would become our annual in-person summits.'
  },

  'cancun-2022': {
    title: 'Cancun 2022',
    edition: '1st In-Person Return',
    location: 'Cancún, Quintana Roo, México',
    flag: `<svg viewBox="0 0 30 20" xmlns="http://www.w3.org/2000/svg" width="36" height="24" style="border-radius:3px">
      <rect width="10" height="20" fill="#006847"/>
      <rect x="10" width="10" height="20" fill="white"/>
      <rect x="20" width="10" height="20" fill="#CE1126"/>
      <ellipse cx="15" cy="10" rx="2.5" ry="3.5" fill="none" stroke="#5C4033" stroke-width="1.2"/>
    </svg>`,
    metrics: [
      { value: '180+', label: 'Attendees' },
      { value: '28',   label: 'Countries' },
      { value: '3',    label: 'Days' },
      { value: '40+',  label: 'Companies' },
      { value: '300+', label: 'Meetings' }
    ],
    /* ── IMAGE URLS ── Replace null with actual image URLs ── */
    images: [ null, null, null ],
    message: 'The return to in-person was electric. Cancún 2022 marked a pivotal moment — <strong>the largest Bling gathering to date</strong>, with structured one-on-one meeting formats that generated over 300 documented business connections across three days.'
  },

  'punta-cana-2023': {
    title: 'Punta Cana 2023',
    edition: '5th Edition',
    location: 'Punta Cana, Dominican Republic',
    flag: `<svg viewBox="0 0 30 20" xmlns="http://www.w3.org/2000/svg" width="36" height="24" style="border-radius:3px">
      <rect width="30" height="20" fill="white"/>
      <rect width="11" height="20" fill="#002D62"/>
      <rect x="19" width="11" height="20" fill="#002D62"/>
      <rect y="7" width="30" height="6" fill="#CF142B"/>
      <rect x="11" y="0" width="8" height="20" fill="white"/>
      <rect x="11" y="7" width="8" height="6" fill="#CF142B"/>
      <circle cx="15" cy="10" r="2" fill="#009A44"/>
    </svg>`,
    metrics: [
      { value: '220+', label: 'Attendees' },
      { value: '38',   label: 'Countries' },
      { value: '4',    label: 'Days' },
      { value: '55+',  label: 'Companies' },
      { value: '500+', label: 'Meetings' }
    ],
    /* ── IMAGE URLS ── Replace null with actual image URLs ── */
    images: [ null, null, null ],
    message: 'Punta Cana 2023 raised the bar on every metric. With <strong>500+ scheduled one-on-one meetings</strong> and 38 countries represented, this edition solidified Bling as the premier networking event for independent freight forwarders in the Americas and beyond.'
  },

  'panama-2024': {
    title: 'Panama 2024',
    edition: '6th Edition',
    location: 'Panama City, Panama',
    flag: `<svg viewBox="0 0 30 20" xmlns="http://www.w3.org/2000/svg" width="36" height="24" style="border-radius:3px">
      <rect width="30" height="20" fill="white"/>
      <rect x="15" width="15" height="10" fill="#DA121A"/>
      <rect y="10" width="15" height="10" fill="#003087"/>
      <polygon points="7.5,2 8.8,6 13,6 9.6,8.5 10.9,12.5 7.5,10 4.1,12.5 5.4,8.5 2,6 6.2,6" fill="#003087"/>
      <polygon points="22.5,8 23.8,12 28,12 24.6,14.5 25.9,18.5 22.5,16 19.1,18.5 20.4,14.5 17,12 21.2,12" fill="#DA121A"/>
    </svg>`,
    metrics: [
      { value: '260+', label: 'Attendees' },
      { value: '42',   label: 'Countries' },
      { value: '4',    label: 'Days' },
      { value: '70+',  label: 'Companies' },
      { value: '600+', label: 'Meetings' }
    ],
    /* ── IMAGE URLS ── Replace null with actual image URLs ── */
    images: [ null, null, null ],
    message: 'Panama — the crossroads of global logistics — was the perfect host for our most international edition yet. <strong>42 countries, 70+ companies, 600+ meetings.</strong> The Bling network was no longer a regional story. It had become a global one.'
  },

  'punta-cana-2025': {
    title: 'Punta Cana 2025',
    edition: '7th Edition',
    location: 'Punta Cana, Dominican Republic',
    flag: `<svg viewBox="0 0 30 20" xmlns="http://www.w3.org/2000/svg" width="36" height="24" style="border-radius:3px">
      <rect width="30" height="20" fill="white"/>
      <rect width="11" height="20" fill="#002D62"/>
      <rect x="19" width="11" height="20" fill="#002D62"/>
      <rect y="7" width="30" height="6" fill="#CF142B"/>
      <rect x="11" y="0" width="8" height="20" fill="white"/>
      <rect x="11" y="7" width="8" height="6" fill="#CF142B"/>
      <circle cx="15" cy="10" r="2" fill="#009A44"/>
    </svg>`,
    metrics: [
      { value: '300+', label: 'Attendees' },
      { value: '48',   label: 'Countries' },
      { value: '4',    label: 'Days' },
      { value: '85+',  label: 'Companies' },
      { value: '750+', label: 'Meetings' }
    ],
    /* ── IMAGE URLS ── Replace null with actual image URLs ── */
    images: [ null, null, null ],
    message: 'Our most successful summit to date. Punta Cana 2025 brought <strong>300+ freight professionals from 48 countries</strong> under one roof, generating 750+ one-on-one meetings and marking the start of a new chapter in the Bling story — one defined by global scale and local trust.'
  },

  'costa-rica-2026': {
    title: 'Costa Rica 2026',
    edition: '8th Edition — Upcoming',
    location: 'San José, Costa Rica — Marriott Hacienda Belén',
    flag: `<svg viewBox="0 0 30 20" xmlns="http://www.w3.org/2000/svg" width="36" height="24" style="border-radius:3px">
      <rect width="30" height="20" fill="white"/>
      <rect y="3" width="30" height="3.5" fill="#002B7F"/>
      <rect y="6.5" width="30" height="3" fill="white"/>
      <rect y="9.5" width="30" height="5" fill="#CE1126"/>
      <rect y="14.5" width="30" height="2" fill="white"/>
      <rect y="16.5" width="30" height="3.5" fill="#002B7F"/>
    </svg>`,
    metrics: [
      { value: 'May',  label: 'Month' },
      { value: '2026', label: 'Year' },
      { value: '4',    label: 'Days' },
      { value: '50+',  label: 'Countries' },
      { value: 'Open', label: 'Registration' }
    ],
    /* ── IMAGE URLS ── Replace null with actual image URLs ── */
    images: [ null, null, null ],
    isUpcoming: true,
    message: 'The Bling Summit Costa Rica 2026 is our most ambitious event yet. <strong>Join us in May at the Marriott Hacienda Belén</strong> for four days of logistics, connections, and innovation in the heart of Central America. Registration is open — secure your spot now.'
  }

};

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   BUILD MODAL HTML
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
function buildMetricsHTML(metrics) {
  return metrics.map(m => `
    <div class="modal-metric">
      <span class="modal-metric-value">${m.value}</span>
      <span class="modal-metric-label">${m.label}</span>
    </div>
  `).join('');
}

function buildImageHTML(url, year, index) {
  /* If a real URL is provided, show the image.
     Otherwise render a styled placeholder.                  */
  if (url) {
    return `<img src="${url}" alt="Event photo ${index + 1}" loading="lazy">`;
  }

  /* Placeholder visual — replace by providing a URL above   */
  const labels = ['Event Highlight', 'Networking', 'Summit Moments'];
  const label  = labels[index] || 'Event Photo';

  return `
    <div class="modal-img-placeholder">
      <div class="modal-img-placeholder-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
          <rect x="3" y="3" width="18" height="18" rx="2"/>
          <circle cx="8.5" cy="8.5" r="1.5"/>
          <path d="M21 15l-5-5L5 21"/>
        </svg>
      </div>
      <span class="modal-img-placeholder-label">${label}</span>
      <span class="modal-img-placeholder-year">${year}</span>
    </div>
  `;
}

function buildGalleryHTML(images, year) {
  return images.map((url, i) => `
    <div class="modal-gallery-item">
      ${buildImageHTML(url, year, i)}
    </div>
  `).join('');
}

function buildModalHTML(event) {
  const year = event.title.match(/\d{4}/)?.[0] || '';
  const isUpcoming = event.isUpcoming || false;

  const ctaButton = isUpcoming
    ? `<a href="https://blinglogisticsnetwork.com/costa-rica-registration-form/"
          target="_blank" class="btn-green" style="padding:12px 28px;font-size:.82rem;">
         Register Now →
       </a>`
    : '';

  return `
    <button class="modal-close" aria-label="Close modal">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round">
        <path d="M18 6L6 18M6 6l12 12"/>
      </svg>
    </button>

    <div class="modal-header">
      <div class="modal-flag">${event.flag}</div>
      <div class="modal-header-text">
        <span class="modal-event-edition">${event.edition}</span>
        <h2 class="modal-event-title">${event.title}</h2>
        <p class="modal-event-location">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/>
            <circle cx="12" cy="10" r="3"/>
          </svg>
          ${event.location}
        </p>
      </div>
    </div>

    <div class="modal-metrics">
      ${buildMetricsHTML(event.metrics)}
    </div>

    <div class="modal-gallery">
      ${buildGalleryHTML(event.images, year)}
    </div>

    <div class="modal-body">
      <p class="modal-body-text">${event.message}</p>
    </div>

    <div class="modal-footer">
      <span class="modal-footer-note">Bling Network · ${year}</span>
      ${ctaButton}
    </div>
  `;
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   CREATE DOM ELEMENTS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
const overlay   = document.createElement('div');
overlay.id      = 'event-modal-overlay';
overlay.className = 'modal-overlay';
overlay.setAttribute('role', 'dialog');
overlay.setAttribute('aria-modal', 'true');

const container = document.createElement('div');
container.className = 'modal-container';

overlay.appendChild(container);
document.body.appendChild(overlay);

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   OPEN / CLOSE LOGIC
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
let closeTimer = null;

function openModal(eventKey, triggerEl) {
  const event = EVENT_DATA[eventKey];
  if (!event) return;

  /* Calculate scale origin from the badge card position */
  const rect    = triggerEl.getBoundingClientRect();
  const originX = rect.left + rect.width  / 2;
  const originY = rect.top  + rect.height / 2;

  /* Set transform-origin relative to viewport so the animation
     appears to expand from the clicked card                   */
  const vpW = window.innerWidth;
  const vpH = window.innerHeight;
  container.style.transformOrigin = `${(originX / vpW * 100).toFixed(1)}% ${(originY / vpH * 100).toFixed(1)}%`;

  /* Inject content */
  container.innerHTML = buildModalHTML(event);
  container.scrollTop = 0;

  /* Activate overlay */
  overlay.classList.remove('closing');
  overlay.classList.add('active');
  document.body.style.overflow = 'hidden';

  /* Bind close button */
  const closeBtn = container.querySelector('.modal-close');
  if (closeBtn) closeBtn.addEventListener('click', closeModal);

  /* Extend cursor hover to new elements */
  container.querySelectorAll('a, button, .modal-metric').forEach(el => {
    el.addEventListener('mouseenter', () => document.body.classList.add('cursor-hover'));
    el.addEventListener('mouseleave', () => document.body.classList.remove('cursor-hover'));
  });
}

function closeModal() {
  if (closeTimer) return;

  overlay.classList.add('closing');
  overlay.classList.remove('active');

  closeTimer = setTimeout(() => {
    overlay.classList.remove('closing');
    container.innerHTML = '';
    document.body.style.overflow = '';
    closeTimer = null;
  }, 350); /* matches CSS transition duration */
}

/* ── Click outside to close ──────────────────────────────── */
overlay.addEventListener('click', e => {
  if (e.target === overlay) closeModal();
});

/* ── Escape key to close ─────────────────────────────────── */
document.addEventListener('keydown', e => {
  if (e.key === 'Escape' && overlay.classList.contains('active')) closeModal();
});

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   BIND BADGE CARDS
   Reads data-event attribute from each .badge-card
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
document.querySelectorAll('.badge-card[data-event]').forEach(card => {
  card.addEventListener('click', () => {
    const key = card.dataset.event;
    openModal(key, card);
  });
});