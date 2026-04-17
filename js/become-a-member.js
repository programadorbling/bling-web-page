/* ═══════════════════════════════════════════════════════════
   BLING NETWORK — BECOME A MEMBER
   Depends on: js/base.js (initCursorHover, observeReveal)
   ═══════════════════════════════════════════════════════════ */

const RECAPTCHA_SITE_KEY = '6LehcbwsAAAAAAcyBKJVr0Ud4q4Xd5NI-Tb2_nnl';
const API_ENDPOINT       = 'api/submit-membership.php';

const PHONE_RE = /^[\d\s\+\-\(\)]{7,}$/;
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/* ── Helpers ─────────────────────────────────────────────── */

function fieldVal(name) {
  const el = document.querySelector(`[name="${name}"]`);
  return el ? el.value.trim() : '';
}

function isKc2Active() {
  return ['kc2_full_name', 'kc2_email', 'kc2_phone', 'kc2_role'].some(n => fieldVal(n) !== '');
}

function isRef1Active() {
  return ['ref1_company_name', 'ref1_contact_name', 'ref1_email', 'ref1_phone', 'ref1_role'].some(n => fieldVal(n) !== '');
}

function isRef2Active() {
  return ['ref2_company_name', 'ref2_contact_name', 'ref2_email', 'ref2_phone', 'ref2_role'].some(n => fieldVal(n) !== '');
}

function showFieldError(fieldName, message) {
  const input = document.querySelector(`[name="${fieldName}"]`);
  const errEl = document.getElementById(`${fieldName}-error`);
  if (input)  input.classList.toggle('has-error', !!message);
  if (errEl)  errEl.textContent = message || '';
}

function clearFieldError(fieldName) {
  showFieldError(fieldName, '');
}

/* ── validateField ───────────────────────────────────────── */

function validateField(field) {
  const name  = field.name;
  const value = field.type === 'checkbox' ? field.checked : field.value.trim();

  /* privacy checkbox */
  if (name === 'privacy_accepted') {
    return field.checked
      ? { valid: true,  message: '' }
      : { valid: false, message: 'You must accept the Privacy Policy to continue.' };
  }

  /* always-required fields */
  const alwaysRequired = ['company_name', 'country', 'city', 'kc1_full_name', 'kc1_email', 'kc1_phone', 'owner_full_name', 'owner_mobile'];
  if (alwaysRequired.includes(name) && value === '') {
    return { valid: false, message: 'This field is required.' };
  }

  /* conditional-required: Key Contact 2 */
  if (isKc2Active()) {
    if (name === 'kc2_full_name' && value === '') return { valid: false, message: 'Full name is required when adding Key Contact 2.' };
    if (name === 'kc2_email'     && value === '') return { valid: false, message: 'Email is required when adding Key Contact 2.' };
    if (name === 'kc2_phone'     && value === '') return { valid: false, message: 'Phone is required when adding Key Contact 2.' };
  }

  /* conditional-required: Reference 1 */
  if (isRef1Active()) {
    if (name === 'ref1_company_name'  && value === '') return { valid: false, message: 'Company name is required when adding Reference 1.' };
    if (name === 'ref1_contact_name'  && value === '') return { valid: false, message: 'Contact name is required when adding Reference 1.' };
    if (name === 'ref1_phone'         && value === '') return { valid: false, message: 'Phone is required when adding Reference 1.' };
    if (name === 'ref1_email'         && value === '') return { valid: false, message: 'Email is required when adding Reference 1.' };
  }

  /* conditional-required: Reference 2 */
  if (isRef2Active()) {
    if (name === 'ref2_company_name'  && value === '') return { valid: false, message: 'Company name is required when adding Reference 2.' };
    if (name === 'ref2_contact_name'  && value === '') return { valid: false, message: 'Contact name is required when adding Reference 2.' };
    if (name === 'ref2_phone'         && value === '') return { valid: false, message: 'Phone is required when adding Reference 2.' };
    if (name === 'ref2_email'         && value === '') return { valid: false, message: 'Email is required when adding Reference 2.' };
  }

  /* format rules (only when the field has a value) */
  if (value !== '') {
    const emailFields = ['kc1_email', 'kc2_email', 'ref1_email', 'ref2_email'];
    const phoneFields = ['kc1_phone', 'kc2_phone', 'owner_mobile', 'company_phone', 'ref1_phone', 'ref2_phone'];

    if (emailFields.includes(name) && !EMAIL_RE.test(value)) {
      return { valid: false, message: 'Please enter a valid email address.' };
    }

    if (phoneFields.includes(name) && !PHONE_RE.test(value)) {
      return { valid: false, message: 'Please enter a valid phone number (digits, spaces, +, -, parentheses — min 7 characters).' };
    }

    if (name === 'website' && value !== '' && !value.startsWith('http://') && !value.startsWith('https://')) {
      return { valid: false, message: 'Website must start with http:// or https://' };
    }
  }

  return { valid: true, message: '' };
}

/* ── validateForm ────────────────────────────────────────── */

function validateForm() {
  const form = document.getElementById('membership-form');
  let allValid = true;

  /* Validate all named inputs/selects/textareas (except honeypot and services[]) */
  form.querySelectorAll('[name]').forEach(field => {
    if (field.name === 'website_url' || field.name === 'services[]') return;

    const result = validateField(field);
    showFieldError(field.name, result.message);
    if (!result.valid) allValid = false;
  });

  return allValid;
}

/* ── getRecaptchaToken ───────────────────────────────────── */

function getRecaptchaToken() {
  return new Promise((resolve, reject) => {
    if (typeof grecaptcha === 'undefined') {
      reject(new Error('reCAPTCHA not loaded'));
      return;
    }
    grecaptcha.ready(() => {
      grecaptcha
        .execute(RECAPTCHA_SITE_KEY, { action: 'membership_submit' })
        .then(resolve)
        .catch(reject);
    });
  });
}

/* ── serializeForm ───────────────────────────────────────── */

function serializeForm(form) {
  const data = {};

  /* Scalar fields */
  form.querySelectorAll('input:not([type="checkbox"]), select, textarea').forEach(field => {
    if (!field.name || field.name === 'website_url') return; /* skip honeypot */
    data[field.name] = field.value.trim();
  });

  /* Single checkboxes (iata_member, privacy_accepted) */
  ['iata_member', 'privacy_accepted'].forEach(name => {
    const el = form.querySelector(`[name="${name}"]`);
    if (el) data[name] = el.checked;
  });

  /* Services checkbox array */
  data['services'] = Array.from(
    form.querySelectorAll('[name="services[]"]:checked')
  ).map(cb => cb.value);

  return data;
}

/* ── handleHoneypot ──────────────────────────────────────── */

function handleHoneypot() {
  const hp = document.getElementById('website_url');
  if (hp && hp.value !== '') {
    /* Bot detected — silently fake success */
    const email = fieldVal('kc1_email');
    showSuccess(email);
    return true;
  }
  return false;
}

/* ── submitForm ──────────────────────────────────────────── */

async function submitForm(data, token) {
  const response = await fetch(API_ENDPOINT, {
    method:  'POST',
    headers: {
      'Content-Type':     'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: JSON.stringify({ ...data, recaptcha_token: token }),
  });

  const json = await response.json();
  return json;
}

/* ── showSuccess ─────────────────────────────────────────── */

function showSuccess(email) {
  const form    = document.getElementById('membership-form');
  const success = document.getElementById('success-message');
  const emailEl = document.getElementById('success-email');

  if (form)    form.hidden    = true;
  if (success) success.hidden = false;
  if (emailEl) emailEl.textContent = email || '';

  /* Scroll the success card into view */
  if (success) success.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

/* ── showError ───────────────────────────────────────────── */

function showError(message) {
  const errEl = document.getElementById('form-error');
  const btn   = document.getElementById('submit-btn');

  if (errEl) {
    errEl.hidden = false;
    /* Keep the static support email text; only override if a message is given */
    if (message) errEl.textContent = message;
    errEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  setButtonLoading(false);
}

/* ── Button state helpers ────────────────────────────────── */

function setButtonLoading(loading) {
  const btn     = document.getElementById('submit-btn');
  const label   = btn && btn.querySelector('.btn-label');
  const spinner = btn && btn.querySelector('.btn-loading');

  if (!btn) return;

  btn.disabled = loading;

  if (label)   label.hidden   = loading;
  if (spinner) spinner.hidden = !loading;
}

/* ── handleSubmit ────────────────────────────────────────── */

async function handleSubmit(event) {
  event.preventDefault();

  /* 1. Honeypot check */
  if (handleHoneypot()) return;

  /* 2. Client-side validation */
  if (!validateForm()) {
    /* Scroll to the first error */
    const firstError = document.querySelector('.has-error');
    if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
    return;
  }

  /* 3. Loading state */
  setButtonLoading(true);

  /* Hide any previous form-level error */
  const errEl = document.getElementById('form-error');
  if (errEl) errEl.hidden = true;

  try {
    /* 4. reCAPTCHA token */
    const token = await getRecaptchaToken();

    /* 5. Serialize & submit */
    const form = document.getElementById('membership-form');
    const data = serializeForm(form);
    const result = await submitForm(data, token);

    /* 6. Handle response */
    if (result && result.status === 'success') {
      showSuccess(data.kc1_email || '');
    } else {
      const msg = (result && result.message) ? result.message : null;
      showError(msg);
    }

  } catch (err) {
    showError(null);
  }
}

/* ── Collapsible fieldset toggles ────────────────────────── */

function initToggles() {
  const toggles = [
    { btnId: 'kc2-btn',  bodyId: 'kc2-body'  },
    { btnId: 'ref1-btn', bodyId: 'ref1-body' },
    { btnId: 'ref2-btn', bodyId: 'ref2-body' },
  ];

  toggles.forEach(({ btnId, bodyId }) => {
    const btn  = document.getElementById(btnId);
    const body = document.getElementById(bodyId);
    if (!btn || !body) return;

    btn.addEventListener('click', () => {
      const isOpen = !body.hidden;

      body.hidden = isOpen;
      btn.setAttribute('aria-expanded', String(!isOpen));

      const addLabel    = btn.querySelector('.toggle-label-add');
      const removeLabel = btn.querySelector('.toggle-label-remove');
      if (addLabel)    addLabel.hidden    = !isOpen;
      if (removeLabel) removeLabel.hidden = isOpen;

      /* If closing, clear errors and values in this section */
      if (!isOpen === false) {
        body.querySelectorAll('[name]').forEach(field => {
          clearFieldError(field.name);
          field.classList.remove('has-error');
        });
      }
    });
  });
}

/* ── initForm ────────────────────────────────────────────── */

function initForm() {
  const form = document.getElementById('membership-form');
  if (!form) return;

  /* Submit handler */
  form.addEventListener('submit', handleSubmit);

  /* Real-time blur validation on all named fields (except honeypot and checkboxes) */
  form.querySelectorAll('input:not([type="checkbox"]):not(#website_url), select, textarea').forEach(field => {
    field.addEventListener('blur', () => {
      const result = validateField(field);
      showFieldError(field.name, result.message);
    });

    /* Clear error on re-focus */
    field.addEventListener('focus', () => clearFieldError(field.name));
  });

  /* Privacy checkbox — validate on change */
  const privacyCb = document.getElementById('privacy_accepted');
  if (privacyCb) {
    privacyCb.addEventListener('change', () => {
      const result = validateField(privacyCb);
      showFieldError('privacy_accepted', result.message);
    });
  }

  /* Collapsible section toggles */
  initToggles();

  /* Extend cursor hover to form interactive elements */
  initCursorHover('.field-input, .checkbox-item, .toggle-btn, .btn-submit, .privacy-link');
}

/* ── Bootstrap ───────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', initForm);
