<?php
if (!defined('BLING_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

// ── Database ─────────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'bling_db');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

// ── SMTP (IONOS) ─────────────────────────────────────────
define('SMTP_HOST',      'smtp.ionos.com');
define('SMTP_PORT',      587);
define('SMTP_USER',      'programador@blinglogisticsnetwork.com');
define('SMTP_PASS',      '09pr0gr@MAd0r#2025');
define('SMTP_FROM',      'programador@blinglogisticsnetwork.com');
define('SMTP_FROM_NAME', 'Bling Network Developer Enviroment');

// ── Internal notification recipients ─────────────────────
// Add all staff emails that should receive new membership notifications
define('NOTIFY_EMAILS', [
    'programador@blinglogisticsnetwork.com',
    'prodigitalwebs@gmail.com'
]);

// ── reCAPTCHA v3 ─────────────────────────────────────────
define('RECAPTCHA_SECRET',    '6LehcbwsAAAAANbXH-ssn1EaIV6xfeHBElVYGdSt');
define('RECAPTCHA_MIN_SCORE', 0.5);

// ── Rate limiting ─────────────────────────────────────────
define('RATE_LIMIT_MAX',    50);   // max submissions
define('RATE_LIMIT_WINDOW', 10);  // minutes

// ── App ───────────────────────────────────────────────────
define('APP_ENV', 'production');  // 'development' | 'production'
define('APP_URL', 'https://bling-network.com');
