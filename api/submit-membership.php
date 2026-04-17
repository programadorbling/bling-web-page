<?php

declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// 1. BOOTSTRAP ────────────────────────────────────────────────────────────────
define('BLING_APP', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

// Same-origin CORS: only allow requests from APP_URL
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === APP_URL) {
    header('Access-Control-Allow-Origin: ' . APP_URL);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['status' => 'error', 'message' => 'Method not allowed.']));
}

// 2. PARSE INPUT ──────────────────────────────────────────────────────────────
$rawInput = file_get_contents('php://input');
$data = json_decode((string)$rawInput, true);

if (!is_array($data)) {
    http_response_code(400);
    exit(json_encode(['status' => 'error', 'message' => 'Invalid JSON.']));
}

// 3. SAME-ORIGIN CHECK ────────────────────────────────────────────────────────
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
    http_response_code(403);
    exit(json_encode(['status' => 'error', 'message' => 'Forbidden.']));
}

// 4. RATE LIMITING ────────────────────────────────────────────────────────────
$rawIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
// X-Forwarded-For may be a comma-separated list; take the first (original client)
$clientIp = substr(trim(explode(',', $rawIp)[0]), 0, 45);

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET),
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    error_log('[membership] DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['status' => 'error', 'message' => 'Server error.']));
}

try {
    $rlStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM rate_limit_log
          WHERE ip_address = :ip
            AND endpoint   = :ep
            AND attempted_at > NOW() - INTERVAL :win MINUTE'
    );
    $rlStmt->execute([':ip' => $clientIp, ':ep' => 'membership', ':win' => RATE_LIMIT_WINDOW]);
    $rlCount = (int) $rlStmt->fetchColumn();

    if ($rlCount >= RATE_LIMIT_MAX) {
        http_response_code(429);
        exit(json_encode(['status' => 'error', 'message' => 'Too many requests.']));
    }

    $pdo->prepare('INSERT INTO rate_limit_log (ip_address, endpoint) VALUES (:ip, :ep)')
        ->execute([':ip' => $clientIp, ':ep' => 'membership']);
} catch (PDOException $e) {
    // Rate-limit table unavailable — log and allow request rather than blocking users
    error_log('[membership] Rate limit check failed: ' . $e->getMessage());
}

// 5. RECAPTCHA VERIFICATION ───────────────────────────────────────────────────
$recaptchaToken = trim((string)($data['recaptcha_token'] ?? ''));
$recaptchaScore = null;

if ($recaptchaToken !== '') {
    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'secret'   => RECAPTCHA_SECRET,
            'response' => $recaptchaToken,
            'remoteip' => $clientIp,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $recaptchaRaw    = (string) curl_exec($ch);
    $recaptchaCurlOk = curl_errno($ch) === 0;
    curl_close($ch);

    if (!$recaptchaCurlOk) {
        error_log('[membership] reCAPTCHA curl error');
        http_response_code(500);
        exit(json_encode(['status' => 'error', 'message' => 'Server error.']));
    }

    $recaptchaResult  = json_decode($recaptchaRaw, true);
    $recaptchaSuccess = ($recaptchaResult['success'] ?? false) === true;
    $recaptchaScore   = isset($recaptchaResult['score']) ? (float)$recaptchaResult['score'] : null;

    if (!$recaptchaSuccess) {
        http_response_code(422);
        exit(json_encode(['status' => 'error', 'message' => 'reCAPTCHA verification failed.']));
    }

    if ($recaptchaScore !== null && $recaptchaScore < RECAPTCHA_MIN_SCORE) {
        // Score too low — silent discard, fake success so bots learn nothing
        http_response_code(200);
        exit(json_encode(['status' => 'success', 'message' => 'Application received.']));
    }
}

// 6. HONEYPOT CHECK ───────────────────────────────────────────────────────────
if (!empty($data['website_url'])) {
    // Bot filled the invisible field — silent discard
    http_response_code(200);
    exit(json_encode(['status' => 'success', 'message' => 'Application received.']));
}

// 7. INPUT VALIDATION ─────────────────────────────────────────────────────────
// All errors are collected before returning so the client receives a full picture.

$errors = [];

// ── Helpers ───────────────────────────────────────────────────────────────────

function sv(mixed $v): string
{
    return trim((string)($v ?? ''));
}

function has_value(mixed $v): bool
{
    return sv($v) !== '';
}

function valid_phone(string $s): bool
{
    return strlen($s) >= 7 && preg_match('/^[\d\s+\-()\/.]+$/', $s) === 1;
}

function valid_email(string $s): bool
{
    return filter_var($s, FILTER_VALIDATE_EMAIL) !== false;
}

// ── Company Information ───────────────────────────────────────────────────────

$companyName = sv($data['company_name'] ?? null);
if ($companyName === '') {
    $errors['company_name'] = 'Company name is required.';
} elseif (strlen($companyName) > 255) {
    $errors['company_name'] = 'Company name must not exceed 255 characters.';
}

$country = sv($data['country'] ?? null);
if ($country === '') {
    $errors['country'] = 'Country is required.';
} elseif (strlen($country) > 100) {
    $errors['country'] = 'Country must not exceed 100 characters.';
}

$city = sv($data['city'] ?? null);
if ($city === '') {
    $errors['city'] = 'City is required.';
} elseif (strlen($city) > 100) {
    $errors['city'] = 'City must not exceed 100 characters.';
}

$address = sv($data['address'] ?? null);
if ($address !== '' && strlen($address) > 500) {
    $errors['address'] = 'Address must not exceed 500 characters.';
}

$companyPhone = sv($data['company_phone'] ?? null);
if ($companyPhone !== '' && !valid_phone($companyPhone)) {
    $errors['company_phone'] = 'Company phone must be at least 7 characters and contain only digits, spaces, +, -, (, ).';
}

$zipCode = sv($data['zip_code'] ?? null);
if ($zipCode !== '') {
    if (strlen($zipCode) > 20 || !preg_match('/^[A-Za-z0-9\s\-]+$/', $zipCode)) {
        $errors['zip_code'] = 'ZIP code must be alphanumeric and not exceed 20 characters.';
    }
}

$website = sv($data['website'] ?? null);
if ($website !== '') {
    if (
        strlen($website) > 255
        || !filter_var($website, FILTER_VALIDATE_URL)
        || (!str_starts_with($website, 'http://') && !str_starts_with($website, 'https://'))
    ) {
        $errors['website'] = 'Website must be a valid URL starting with http:// or https://.';
    }
}

$numberOfEmployees = sv($data['number_of_employees'] ?? null);
if ($numberOfEmployees !== '' && strlen($numberOfEmployees) > 50) {
    $errors['number_of_employees'] = 'Number of employees value is too long.';
}

$establishedDate = sv($data['established_date'] ?? null);
if ($establishedDate !== '' && !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $establishedDate)) {
    $errors['established_date'] = 'Established date must be in YYYY-MM format.';
}

$otherNetworks = sv($data['other_networks'] ?? null);
if ($otherNetworks !== '' && strlen($otherNetworks) > 1000) {
    $errors['other_networks'] = 'Other networks must not exceed 1000 characters.';
}

// ── Services & Market ─────────────────────────────────────────────────────────

const VALID_SERVICES = [
    'svc_air_freight', 'svc_ocean_freight', 'svc_road_freight', 'svc_rail_freight',
    'svc_customs_clearance', 'svc_warehousing', 'svc_project_cargo', 'svc_multimodal',
    'svc_courier_express', 'svc_dangerous_goods',
];

$rawServices = $data['services'] ?? [];
if (!is_array($rawServices)) {
    $errors['services'] = 'Services must be an array.';
    $rawServices = [];
} else {
    foreach ($rawServices as $svc) {
        if (!in_array((string)$svc, VALID_SERVICES, true)) {
            $errors['services'] = 'One or more selected services are not recognised.';
            break;
        }
    }
}

$iataMember = filter_var($data['iata_member'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
if ($iataMember === null) {
    $errors['iata_member'] = 'IATA member must be a boolean value.';
}

$principalMarket = sv($data['principal_market'] ?? null);
if ($principalMarket !== '' && strlen($principalMarket) > 100) {
    $errors['principal_market'] = 'Principal market must not exceed 100 characters.';
}

// ── Key Contact 1 (required) ──────────────────────────────────────────────────

$kc1Prefix   = sv($data['kc1_prefix']    ?? null);
$kc1FullName = sv($data['kc1_full_name'] ?? null);
$kc1Role     = sv($data['kc1_role']      ?? null);
$kc1Email    = sv($data['kc1_email']     ?? null);
$kc1Phone    = sv($data['kc1_phone']     ?? null);

if ($kc1Prefix !== '' && !in_array($kc1Prefix, ['Mr', 'Ms', 'Mrs'], true)) {
    $errors['kc1_prefix'] = 'Prefix must be Mr, Ms, or Mrs.';
}
if ($kc1FullName === '') {
    $errors['kc1_full_name'] = 'Key Contact 1 full name is required.';
} elseif (strlen($kc1FullName) > 200) {
    $errors['kc1_full_name'] = 'Key Contact 1 full name must not exceed 200 characters.';
}
if ($kc1Role !== '' && strlen($kc1Role) > 100) {
    $errors['kc1_role'] = 'Key Contact 1 role must not exceed 100 characters.';
}
if ($kc1Email === '') {
    $errors['kc1_email'] = 'Key Contact 1 email is required.';
} elseif (!valid_email($kc1Email)) {
    $errors['kc1_email'] = 'Key Contact 1 email must be a valid email address.';
}
if ($kc1Phone === '') {
    $errors['kc1_phone'] = 'Key Contact 1 phone is required.';
} elseif (!valid_phone($kc1Phone)) {
    $errors['kc1_phone'] = 'Key Contact 1 phone must be at least 7 characters and contain only digits, spaces, +, -, (, ).';
}

// ── Key Contact 2 (conditional — required only if any kc2_* field has a value) ─

$kc2Prefix   = sv($data['kc2_prefix']    ?? null);
$kc2FullName = sv($data['kc2_full_name'] ?? null);
$kc2Role     = sv($data['kc2_role']      ?? null);
$kc2Email    = sv($data['kc2_email']     ?? null);
$kc2Phone    = sv($data['kc2_phone']     ?? null);

$kc2HasAny = $kc2Prefix !== '' || $kc2FullName !== '' || $kc2Role !== ''
           || $kc2Email  !== '' || $kc2Phone   !== '';

if ($kc2HasAny) {
    if ($kc2Prefix !== '' && !in_array($kc2Prefix, ['Mr', 'Ms', 'Mrs'], true)) {
        $errors['kc2_prefix'] = 'Prefix must be Mr, Ms, or Mrs.';
    }
    if ($kc2FullName === '') {
        $errors['kc2_full_name'] = 'Key Contact 2 full name is required when any KC2 field is provided.';
    } elseif (strlen($kc2FullName) > 200) {
        $errors['kc2_full_name'] = 'Key Contact 2 full name must not exceed 200 characters.';
    }
    if ($kc2Role !== '' && strlen($kc2Role) > 100) {
        $errors['kc2_role'] = 'Key Contact 2 role must not exceed 100 characters.';
    }
    if ($kc2Email === '') {
        $errors['kc2_email'] = 'Key Contact 2 email is required when any KC2 field is provided.';
    } elseif (!valid_email($kc2Email)) {
        $errors['kc2_email'] = 'Key Contact 2 email must be a valid email address.';
    }
    if ($kc2Phone === '') {
        $errors['kc2_phone'] = 'Key Contact 2 phone is required when any KC2 field is provided.';
    } elseif (!valid_phone($kc2Phone)) {
        $errors['kc2_phone'] = 'Key Contact 2 phone must be at least 7 characters and contain only digits, spaces, +, -, (, ).';
    }
}

// ── Ownership (required) ──────────────────────────────────────────────────────

$ownerFullName = sv($data['owner_full_name'] ?? null);
$ownerRole     = sv($data['owner_role']      ?? null);
$ownerMobile   = sv($data['owner_mobile']    ?? null);

if ($ownerFullName === '') {
    $errors['owner_full_name'] = 'Owner full name is required.';
} elseif (strlen($ownerFullName) > 200) {
    $errors['owner_full_name'] = 'Owner full name must not exceed 200 characters.';
}
if ($ownerRole !== '' && strlen($ownerRole) > 100) {
    $errors['owner_role'] = 'Owner role must not exceed 100 characters.';
}
if ($ownerMobile === '') {
    $errors['owner_mobile'] = 'Owner mobile phone is required.';
} elseif (!valid_phone($ownerMobile)) {
    $errors['owner_mobile'] = 'Owner mobile must be at least 7 characters and contain only digits, spaces, +, -, (, ).';
}

// ── Reference 1 (conditional — required fields apply if any ref1_* has a value) ─

$ref1CompanyName = sv($data['ref1_company_name'] ?? null);
$ref1ContactName = sv($data['ref1_contact_name'] ?? null);
$ref1Role        = sv($data['ref1_role']         ?? null);
$ref1Phone       = sv($data['ref1_phone']        ?? null);
$ref1Email       = sv($data['ref1_email']        ?? null);

$ref1HasAny = $ref1CompanyName !== '' || $ref1ContactName !== '' || $ref1Role    !== ''
           || $ref1Phone       !== '' || $ref1Email       !== '';

if ($ref1HasAny) {
    if ($ref1CompanyName === '') {
        $errors['ref1_company_name'] = 'Reference 1 company name is required when any Ref 1 field is provided.';
    } elseif (strlen($ref1CompanyName) > 255) {
        $errors['ref1_company_name'] = 'Reference 1 company name must not exceed 255 characters.';
    }
    if ($ref1ContactName === '') {
        $errors['ref1_contact_name'] = 'Reference 1 contact name is required when any Ref 1 field is provided.';
    } elseif (strlen($ref1ContactName) > 200) {
        $errors['ref1_contact_name'] = 'Reference 1 contact name must not exceed 200 characters.';
    }
    if ($ref1Role !== '' && strlen($ref1Role) > 100) {
        $errors['ref1_role'] = 'Reference 1 role must not exceed 100 characters.';
    }
    if ($ref1Phone === '') {
        $errors['ref1_phone'] = 'Reference 1 phone is required when any Ref 1 field is provided.';
    } elseif (!valid_phone($ref1Phone)) {
        $errors['ref1_phone'] = 'Reference 1 phone must be at least 7 characters and contain only digits, spaces, +, -, (, ).';
    }
    if ($ref1Email === '') {
        $errors['ref1_email'] = 'Reference 1 email is required when any Ref 1 field is provided.';
    } elseif (!valid_email($ref1Email)) {
        $errors['ref1_email'] = 'Reference 1 email must be a valid email address.';
    }
}

// ── Reference 2 (conditional — independent of Reference 1) ───────────────────

$ref2CompanyName = sv($data['ref2_company_name'] ?? null);
$ref2ContactName = sv($data['ref2_contact_name'] ?? null);
$ref2Role        = sv($data['ref2_role']         ?? null);
$ref2Phone       = sv($data['ref2_phone']        ?? null);
$ref2Email       = sv($data['ref2_email']        ?? null);

$ref2HasAny = $ref2CompanyName !== '' || $ref2ContactName !== '' || $ref2Role    !== ''
           || $ref2Phone       !== '' || $ref2Email       !== '';

if ($ref2HasAny) {
    if ($ref2CompanyName === '') {
        $errors['ref2_company_name'] = 'Reference 2 company name is required when any Ref 2 field is provided.';
    } elseif (strlen($ref2CompanyName) > 255) {
        $errors['ref2_company_name'] = 'Reference 2 company name must not exceed 255 characters.';
    }
    if ($ref2ContactName === '') {
        $errors['ref2_contact_name'] = 'Reference 2 contact name is required when any Ref 2 field is provided.';
    } elseif (strlen($ref2ContactName) > 200) {
        $errors['ref2_contact_name'] = 'Reference 2 contact name must not exceed 200 characters.';
    }
    if ($ref2Role !== '' && strlen($ref2Role) > 100) {
        $errors['ref2_role'] = 'Reference 2 role must not exceed 100 characters.';
    }
    if ($ref2Phone === '') {
        $errors['ref2_phone'] = 'Reference 2 phone is required when any Ref 2 field is provided.';
    } elseif (!valid_phone($ref2Phone)) {
        $errors['ref2_phone'] = 'Reference 2 phone must be at least 7 characters and contain only digits, spaces, +, -, (, ).';
    }
    if ($ref2Email === '') {
        $errors['ref2_email'] = 'Reference 2 email is required when any Ref 2 field is provided.';
    } elseif (!valid_email($ref2Email)) {
        $errors['ref2_email'] = 'Reference 2 email must be a valid email address.';
    }
}

// ── Privacy ───────────────────────────────────────────────────────────────────

$privacyAccepted = filter_var(
    $data['privacy_accepted'] ?? false,
    FILTER_VALIDATE_BOOLEAN,
    FILTER_NULL_ON_FAILURE
);
if (!$privacyAccepted) {
    $errors['privacy_accepted'] = 'You must accept the privacy policy.';
}

// ── Return 422 if any validation failed ───────────────────────────────────────

if (!empty($errors)) {
    http_response_code(422);
    exit(json_encode([
        'status'  => 'error',
        'message' => 'Validation failed.',
        'errors'  => $errors,
    ]));
}

// 8. SANITIZE ─────────────────────────────────────────────────────────────────
// htmlspecialchars all string values before storing. DB-nullable fields that
// arrived as empty strings are converted to null so the column stores NULL.

function san(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function san_or_null(string $s): ?string
{
    return $s !== '' ? htmlspecialchars($s, ENT_QUOTES, 'UTF-8') : null;
}

// Company
$s_companyName       = san($companyName);
$s_country           = san($country);
$s_city              = san($city);
$s_address           = san_or_null($address);
$s_companyPhone      = san_or_null($companyPhone);
$s_zipCode           = san_or_null($zipCode);
$s_website           = san_or_null($website);
$s_numberOfEmployees = san_or_null($numberOfEmployees);   // VARCHAR(20) in DB
$s_establishedDate   = $establishedDate !== '' ? $establishedDate . '-01' : null; // YYYY-MM → DATE
$s_otherNetworks     = san_or_null($otherNetworks);

// Services & market
$s_principalMarket = san_or_null($principalMarket);

// Build services boolean map: all columns default 0, checked ones set to 1
$servicesMap = array_fill_keys(VALID_SERVICES, 0);
foreach ($rawServices as $svc) {
    $servicesMap[(string)$svc] = 1;
}

// Key Contact 1
$s_kc1Prefix   = san_or_null($kc1Prefix);
$s_kc1FullName = san($kc1FullName);
$s_kc1Role     = san_or_null($kc1Role);
$s_kc1Email    = san($kc1Email);
$s_kc1Phone    = san($kc1Phone);

// Key Contact 2 (sanitized only when present; stays null otherwise)
$s_kc2Prefix   = san_or_null($kc2Prefix);
$s_kc2FullName = san_or_null($kc2FullName);
$s_kc2Role     = san_or_null($kc2Role);
$s_kc2Email    = san_or_null($kc2Email);
$s_kc2Phone    = san_or_null($kc2Phone);

// Ownership
$s_ownerFullName = san($ownerFullName);
$s_ownerRole     = san_or_null($ownerRole);
$s_ownerMobile   = san($ownerMobile);

// References
$s_ref1CompanyName = san_or_null($ref1CompanyName);
$s_ref1ContactName = san_or_null($ref1ContactName);
$s_ref1Role        = san_or_null($ref1Role);
$s_ref1Phone       = san_or_null($ref1Phone);
$s_ref1Email       = san_or_null($ref1Email);

$s_ref2CompanyName = san_or_null($ref2CompanyName);
$s_ref2ContactName = san_or_null($ref2ContactName);
$s_ref2Role        = san_or_null($ref2Role);
$s_ref2Phone       = san_or_null($ref2Phone);
$s_ref2Email       = san_or_null($ref2Email);

$s_userAgent = san_or_null(substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500));

// 9. DATABASE INSERT ──────────────────────────────────────────────────────────
// Single transaction: parent row first, then all child rows.
// On any PDOException the whole transaction is rolled back.

$requestId = null;

try {
    $pdo->beginTransaction();

    // ── membership_requests (parent) ──────────────────────────────────────────
    $pdo->prepare(
        'INSERT INTO membership_requests (privacy_accepted, ip_address, user_agent, recaptcha_score)
         VALUES (:privacy, :ip, :ua, :score)'
    )->execute([
        ':privacy' => 1,
        ':ip'      => $clientIp,
        ':ua'      => $s_userAgent,
        ':score'   => $recaptchaScore,
    ]);
    $requestId = (int) $pdo->lastInsertId();

    // ── membership_company ────────────────────────────────────────────────────
    $pdo->prepare(
        'INSERT INTO membership_company
           (request_id, company_name, country, city, address, company_phone,
            zip_code, website, number_of_employees, established_date, other_networks)
         VALUES
           (:rid, :cn, :co, :ci, :ad, :cp, :zc, :wb, :ne, :ed, :on)'
    )->execute([
        ':rid' => $requestId,
        ':cn'  => $s_companyName,
        ':co'  => $s_country,
        ':ci'  => $s_city,
        ':ad'  => $s_address,
        ':cp'  => $s_companyPhone,
        ':zc'  => $s_zipCode,
        ':wb'  => $s_website,
        ':ne'  => $s_numberOfEmployees,
        ':ed'  => $s_establishedDate,
        ':on'  => $s_otherNetworks,
    ]);

    // ── membership_services ───────────────────────────────────────────────────
    $pdo->prepare(
        'INSERT INTO membership_services
           (request_id,
            svc_air_freight, svc_ocean_freight, svc_road_freight, svc_rail_freight,
            svc_customs_clearance, svc_warehousing, svc_project_cargo, svc_multimodal,
            svc_courier_express, svc_dangerous_goods,
            iata_member, principal_market)
         VALUES
           (:rid,
            :air, :ocean, :road, :rail,
            :customs, :ware, :proj, :multi,
            :courier, :dg,
            :iata, :pm)'
    )->execute([
        ':rid'     => $requestId,
        ':air'     => $servicesMap['svc_air_freight'],
        ':ocean'   => $servicesMap['svc_ocean_freight'],
        ':road'    => $servicesMap['svc_road_freight'],
        ':rail'    => $servicesMap['svc_rail_freight'],
        ':customs' => $servicesMap['svc_customs_clearance'],
        ':ware'    => $servicesMap['svc_warehousing'],
        ':proj'    => $servicesMap['svc_project_cargo'],
        ':multi'   => $servicesMap['svc_multimodal'],
        ':courier' => $servicesMap['svc_courier_express'],
        ':dg'      => $servicesMap['svc_dangerous_goods'],
        ':iata'    => ($iataMember === true) ? 1 : 0,
        ':pm'      => $s_principalMarket,
    ]);

    // ── membership_contacts — Key Contact 1 (always inserted) ─────────────────
    $pdo->prepare(
        'INSERT INTO membership_contacts
           (request_id, contact_order, prefix, full_name, role, email, phone)
         VALUES (:rid, 1, :pre, :fn, :ro, :em, :ph)'
    )->execute([
        ':rid' => $requestId,
        ':pre' => $s_kc1Prefix,
        ':fn'  => $s_kc1FullName,
        ':ro'  => $s_kc1Role,
        ':em'  => $s_kc1Email,
        ':ph'  => $s_kc1Phone,
    ]);

    // ── membership_contacts — Key Contact 2 (only if kc2_full_name is present) ─
    if ($kc2FullName !== '') {
        $pdo->prepare(
            'INSERT INTO membership_contacts
               (request_id, contact_order, prefix, full_name, role, email, phone)
             VALUES (:rid, 2, :pre, :fn, :ro, :em, :ph)'
        )->execute([
            ':rid' => $requestId,
            ':pre' => $s_kc2Prefix,
            ':fn'  => $s_kc2FullName,
            ':ro'  => $s_kc2Role,
            ':em'  => $s_kc2Email,
            ':ph'  => $s_kc2Phone,
        ]);
    }

    // ── membership_ownership ──────────────────────────────────────────────────
    $pdo->prepare(
        'INSERT INTO membership_ownership (request_id, full_name, role, mobile_phone)
         VALUES (:rid, :fn, :ro, :mp)'
    )->execute([
        ':rid' => $requestId,
        ':fn'  => $s_ownerFullName,
        ':ro'  => $s_ownerRole,
        ':mp'  => $s_ownerMobile,
    ]);

    // ── membership_references — Reference 1 (only if ref1_company_name present) ─
    if ($ref1CompanyName !== '') {
        $pdo->prepare(
            'INSERT INTO membership_references
               (request_id, reference_order, company_name, contact_name, contact_role, phone, email)
             VALUES (:rid, 1, :cn, :ctn, :ro, :ph, :em)'
        )->execute([
            ':rid' => $requestId,
            ':cn'  => $s_ref1CompanyName,
            ':ctn' => $s_ref1ContactName,
            ':ro'  => $s_ref1Role,
            ':ph'  => $s_ref1Phone,
            ':em'  => $s_ref1Email,
        ]);
    }

    // ── membership_references — Reference 2 (only if ref2_company_name present) ─
    if ($ref2CompanyName !== '') {
        $pdo->prepare(
            'INSERT INTO membership_references
               (request_id, reference_order, company_name, contact_name, contact_role, phone, email)
             VALUES (:rid, 2, :cn, :ctn, :ro, :ph, :em)'
        )->execute([
            ':rid' => $requestId,
            ':cn'  => $s_ref2CompanyName,
            ':ctn' => $s_ref2ContactName,
            ':ro'  => $s_ref2Role,
            ':ph'  => $s_ref2Phone,
            ':em'  => $s_ref2Email,
        ]);
    }

    $pdo->commit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[membership] DB insert failed (request_id=' . ($requestId ?? 'none') . '): ' . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['status' => 'error', 'message' => 'Server error.']));
}

// 10. INTERNAL NOTIFICATION EMAIL ────────────────────────────────────────────

function buildServicesLabel(array $servicesMap): string
{
    $labels = [
        'svc_air_freight'     => 'Air Freight',
        'svc_ocean_freight'   => 'Ocean Freight',
        'svc_road_freight'    => 'Road Freight',
        'svc_rail_freight'    => 'Rail Freight',
        'svc_customs_clearance' => 'Customs Clearance',
        'svc_warehousing'     => 'Warehousing',
        'svc_project_cargo'   => 'Project Cargo',
        'svc_multimodal'      => 'Multimodal',
        'svc_courier_express' => 'Courier & Express',
        'svc_dangerous_goods' => 'Dangerous Goods',
    ];
    $checked = [];
    foreach ($labels as $key => $label) {
        if (!empty($servicesMap[$key])) {
            $checked[] = $label;
        }
    }
    return $checked ? implode(', ', $checked) : '—';
}

function emailRow(string $label, ?string $value): string
{
    $v = ($value !== null && $value !== '') ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : '—';
    return '<tr>'
        . '<td style="font-family:Arial,sans-serif;font-weight:bold;color:#333333;width:40%;padding:6px 8px;vertical-align:top;">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td style="font-family:Arial,sans-serif;color:#555555;padding:6px 8px;">' . $v . '</td>'
        . '</tr>';
}

function emailSection(string $heading, string $rows): string
{
    return '<h3 style="font-family:Arial,sans-serif;color:#030081;font-weight:bold;border-bottom:2px solid #d1d70d;padding-bottom:6px;margin-bottom:12px;margin-top:24px;">'
        . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') . '</h3>'
        . '<table width="100%" cellpadding="0" cellspacing="0" border="0">' . $rows . '</table>';
}

$emailHeader = '<div style="background:#030081;padding:32px;text-align:center;">'
    . '<img src="https://bling-network.com/uploads/Logo-de-Bling-2026_Transparencia.png" alt="Bling Network" style="max-width:180px;height:auto;">'
    . '</div>';

$emailFooter = '<div style="background:#030081;color:rgba(255,255,255,0.6);font-size:12px;font-family:Arial,sans-serif;text-align:center;padding:20px;">'
    . '&copy; 2026 Bling Network. All rights reserved.'
    . '</div>';

$submittedAt = date('Y-m-d H:i:s');

// Build service list label for display
$servicesLabel = buildServicesLabel($servicesMap);

// ── Internal email body ───────────────────────────────────────────────────────
$companyRows = emailRow('Company Name', $s_companyName)
    . emailRow('Country', $s_country)
    . emailRow('City', $s_city)
    . emailRow('Address', $s_address)
    . emailRow('Company Phone', $s_companyPhone)
    . emailRow('ZIP Code', $s_zipCode)
    . emailRow('Website', $s_website)
    . emailRow('Employees', $s_numberOfEmployees)
    . emailRow('Established', $s_establishedDate)
    . emailRow('Other Networks', $s_otherNetworks);

$servicesRows = emailRow('Services Offered', $servicesLabel)
    . emailRow('IATA Member', ($iataMember === true) ? 'Yes' : 'No')
    . emailRow('Principal Market', $s_principalMarket);

$kc1Rows = emailRow('Name', trim(($s_kc1Prefix !== null ? $s_kc1Prefix . ' ' : '') . $s_kc1FullName))
    . emailRow('Role', $s_kc1Role)
    . emailRow('Email', $s_kc1Email)
    . emailRow('Phone', $s_kc1Phone);

$ownerRows = emailRow('Name', $s_ownerFullName)
    . emailRow('Role', $s_ownerRole)
    . emailRow('Mobile', $s_ownerMobile);

$internalBody = '<div style="background:#f5f5f5;padding:24px 0;">'
    . '<div style="max-width:600px;margin:0 auto;background:#ffffff;">'
    . $emailHeader
    . '<div style="padding:24px 32px;">'
    . '<h1 style="font-family:Arial,sans-serif;color:#030081;margin-top:0;">New Membership Application</h1>'
    . '<p style="font-family:Arial,sans-serif;color:#555555;font-size:13px;">Submitted on ' . $submittedAt . ' &nbsp;·&nbsp; IP: ' . htmlspecialchars($clientIp, ENT_QUOTES, 'UTF-8') . ' &nbsp;·&nbsp; reCAPTCHA score: ' . ($recaptchaScore !== null ? $recaptchaScore : 'n/a') . '</p>'
    . emailSection('Company Information', $companyRows)
    . emailSection('Services &amp; Market', $servicesRows)
    . emailSection('Key Contact 1', $kc1Rows);

if ($kc2FullName !== '') {
    $kc2Rows = emailRow('Name', trim(($s_kc2Prefix !== null ? $s_kc2Prefix . ' ' : '') . ($s_kc2FullName ?? '')))
        . emailRow('Role', $s_kc2Role)
        . emailRow('Email', $s_kc2Email)
        . emailRow('Phone', $s_kc2Phone);
    $internalBody .= emailSection('Key Contact 2', $kc2Rows);
}

$internalBody .= emailSection('Ownership', $ownerRows);

if ($ref1CompanyName !== '') {
    $ref1Rows = emailRow('Company', $s_ref1CompanyName)
        . emailRow('Contact', $s_ref1ContactName)
        . emailRow('Role', $s_ref1Role)
        . emailRow('Phone', $s_ref1Phone)
        . emailRow('Email', $s_ref1Email);
    $internalBody .= emailSection('Reference 1', $ref1Rows);
}

if ($ref2CompanyName !== '') {
    $ref2Rows = emailRow('Company', $s_ref2CompanyName)
        . emailRow('Contact', $s_ref2ContactName)
        . emailRow('Role', $s_ref2Role)
        . emailRow('Phone', $s_ref2Phone)
        . emailRow('Email', $s_ref2Email);
    $internalBody .= emailSection('Reference 2', $ref2Rows);
}

$internalBody .= '<p style="font-family:Arial,sans-serif;color:#888888;font-size:12px;margin-top:24px;">Reply directly to this email to contact the applicant (Reply-To: ' . htmlspecialchars($s_kc1Email, ENT_QUOTES, 'UTF-8') . ').</p>'
    . '</div>'
    . $emailFooter
    . '</div>'
    . '</div>';

try {
    $mailerInternal = new PHPMailer(true);
    $mailerInternal->isSMTP();
    $mailerInternal->SMTPDebug  = 0;
    $mailerInternal->Host       = SMTP_HOST;
    $mailerInternal->Port       = SMTP_PORT;
    $mailerInternal->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mailerInternal->SMTPAuth   = true;
    $mailerInternal->Username   = SMTP_USER;
    $mailerInternal->Password   = SMTP_PASS;
    $mailerInternal->setFrom(SMTP_FROM, SMTP_FROM_NAME);
    $mailerInternal->addReplyTo($s_kc1Email, $s_kc1FullName);
    foreach (NOTIFY_EMAILS as $notifyAddr) {
        $mailerInternal->addAddress($notifyAddr);
    }
    $mailerInternal->isHTML(true);
    $mailerInternal->CharSet = 'UTF-8';
    $mailerInternal->Subject = '[New Membership Application] ' . $s_companyName . ' — ' . $s_country;
    $mailerInternal->Body    = $internalBody;
    $mailerInternal->AltBody = 'New membership application from ' . $s_companyName . ' (' . $s_country . '). Contact: ' . $s_kc1FullName . ' <' . $s_kc1Email . '>.';
    $mailerInternal->send();
} catch (PHPMailerException $e) {
    error_log('[membership] Email failed (internal notification, request_id=' . $requestId . '): ' . $e->getMessage());
}

// 11. APPLICANT CONFIRMATION EMAIL ────────────────────────────────────────────
$ctaButton = '<p style="text-align:center;margin:28px 0;">'
    . '<a href="https://bling-network.com/benefits.html" style="background:#d1d70d;color:#030081;font-family:Arial,sans-serif;font-weight:bold;padding:14px 28px;border-radius:6px;text-decoration:none;display:inline-block;">Explore Benefits &rarr;</a>'
    . '</p>';

$confirmationBody = '<div style="background:#f5f5f5;padding:24px 0;">'
    . '<div style="max-width:600px;margin:0 auto;background:#ffffff;">'
    . $emailHeader
    . '<div style="padding:24px 32px;">'
    . '<h1 style="font-family:Arial,sans-serif;color:#030081;margin-top:0;">Thank you, ' . htmlspecialchars($s_kc1FullName, ENT_QUOTES, 'UTF-8') . '!</h1>'
    . '<p style="font-family:Arial,sans-serif;color:#555555;">We have received your membership application for <strong>' . htmlspecialchars($s_companyName, ENT_QUOTES, 'UTF-8') . '</strong>. Our team will review your request and contact you at ' . htmlspecialchars($s_kc1Email, ENT_QUOTES, 'UTF-8') . ' within 2–3 business days.</p>'
    . '<div style="background:#f0f0f0;border-radius:8px;padding:16px 20px;margin:20px 0;">'
    . '<table width="100%" cellpadding="0" cellspacing="0" border="0">'
    . emailRow('Application Reference', '#' . $requestId)
    . emailRow('Company', $s_companyName)
    . emailRow('Primary Contact', $s_kc1FullName)
    . emailRow('Submitted', $submittedAt)
    . '</table>'
    . '</div>'
    . '<p style="font-family:Arial,sans-serif;color:#555555;">In the meantime, feel free to explore our upcoming events and learn more about the benefits of being a Bling Network member.</p>'
    . $ctaButton
    . '</div>'
    . '<div style="background:#030081;color:rgba(255,255,255,0.6);font-size:12px;font-family:Arial,sans-serif;text-align:center;padding:20px;">'
    . '<p style="margin:0 0 8px;">Questions? Contact us at <a href="mailto:membership@bling-network.com" style="color:#d1d70d;">membership@bling-network.com</a></p>'
    . '<p style="margin:0;">&copy; 2026 Bling Network. All rights reserved.</p>'
    . '</div>'
    . '</div>'
    . '</div>';

try {
    $mailerConfirm = new PHPMailer(true);
    $mailerConfirm->isSMTP();
    $mailerConfirm->SMTPDebug  = 0;
    $mailerConfirm->Host       = SMTP_HOST;
    $mailerConfirm->Port       = SMTP_PORT;
    $mailerConfirm->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mailerConfirm->SMTPAuth   = true;
    $mailerConfirm->Username   = SMTP_USER;
    $mailerConfirm->Password   = SMTP_PASS;
    $mailerConfirm->setFrom(SMTP_FROM, SMTP_FROM_NAME);
    $mailerConfirm->addAddress($s_kc1Email, $s_kc1FullName);
    $mailerConfirm->isHTML(true);
    $mailerConfirm->CharSet = 'UTF-8';
    $mailerConfirm->Subject = 'We received your application — Bling Network';
    $mailerConfirm->Body    = $confirmationBody;
    $mailerConfirm->AltBody = 'Thank you, ' . $s_kc1FullName . '. We have received your membership application for ' . $s_companyName . '. Our team will review your request and contact you within 2-3 business days. Application Reference: #' . $requestId . '.';
    $mailerConfirm->send();
} catch (PHPMailerException $e) {
    error_log('[membership] Email failed (applicant confirmation, request_id=' . $requestId . '): ' . $e->getMessage());
}

http_response_code(200);
exit(json_encode(['status' => 'success', 'message' => 'Application received.']));
