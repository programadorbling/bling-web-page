<?php
declare(strict_types=1);

// 1. BOOTSTRAP ────────────────────────────────────────────────────────────────
define('BLING_APP', true);
require_once __DIR__ . '/config.php';

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
if ($numberOfEmployees !== '') {
    if (!ctype_digit($numberOfEmployees) || (int)$numberOfEmployees < 1) {
        $errors['number_of_employees'] = 'Number of employees must be a positive integer.';
    }
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

// Steps 10–11 (email notifications) will be implemented in Milestone 4.
http_response_code(200);
exit(json_encode(['status' => 'success', 'message' => 'Application received.']));
