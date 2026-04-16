<?php
define('BLING_APP', true);

if (!defined('BLING_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

// ── Database ─────────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'bling_db');
define('DB_USER',    'bling_user');
define('DB_PASS',    'YOUR_DB_PASS');
define('DB_CHARSET', 'utf8mb4');

// ── SMTP (IONOS) ─────────────────────────────────────────
define('SMTP_HOST',      'smtp.ionos.com');
define('SMTP_PORT',      587);
define('SMTP_USER',      'noreply@bling-network.com');
define('SMTP_PASS',      'YOUR_SMTP_PASS');
define('SMTP_FROM',      'noreply@bling-network.com');
define('SMTP_FROM_NAME', 'Bling Network');

// ── Internal notification recipients ─────────────────────
// Add all staff emails that should receive new membership notifications
define('NOTIFY_EMAILS', [
    'admin@bling-network.com',
    'membership@bling-network.com',
]);

// ── reCAPTCHA v3 ─────────────────────────────────────────
define('RECAPTCHA_SECRET',    'YOUR_RECAPTCHA_SECRET_KEY');
define('RECAPTCHA_MIN_SCORE', 0.5);

// ── Rate limiting ─────────────────────────────────────────
define('RATE_LIMIT_MAX',    3);   // max submissions
define('RATE_LIMIT_WINDOW', 10);  // minutes

// ── App ───────────────────────────────────────────────────
define('APP_ENV', 'production');  // 'development' | 'production'
define('APP_URL', 'https://bling-network.com');
