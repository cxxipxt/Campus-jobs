<?php
require dirname(__DIR__, 2) . '/app/bootstrap.php';

function google_login_failed(string $message): never {
    $_SESSION['employer_google_error'] = $message;
    go('employer/login.php');
}

function google_json(string $endpoint, array $options): array {
    $curl = curl_init($endpoint);
    curl_setopt_array($curl, $options + [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $data = is_string($body) ? json_decode($body, true) : null;
    if ($status !== 200 || !is_array($data)) {
        google_login_failed('เชื่อมต่อ Google ไม่สำเร็จ กรุณาลองอีกครั้ง');
    }
    return $data;
}

$clientId = getenv('EMPLOYER_GOOGLE_CLIENT_ID');
$secret = getenv('EMPLOYER_GOOGLE_CLIENT_SECRET');
$redirect = getenv('EMPLOYER_GOOGLE_REDIRECT_URI');
if (!$clientId || !$secret || !$redirect || !function_exists('curl_init')) {
    google_login_failed('ระบบเข้าสู่ระบบด้วย Google ยังไม่ได้ตั้งค่า');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $state = bin2hex(random_bytes(32));
    $verifier = bin2hex(random_bytes(32));
    $_SESSION['employer_google_flow'] = ['state' => $state, 'verifier' => $verifier, 'expires' => time() + 600];
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirect,
        'response_type' => 'code',
        'scope' => 'openid email',
        'state' => $state,
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
        'prompt' => 'select_account',
    ]), true, 303);
    exit;
}

$flow = $_SESSION['employer_google_flow'] ?? null;
unset($_SESSION['employer_google_flow']);
$state = input('state', $_GET);
$code = input('code', $_GET);
if (!$flow || $flow['expires'] < time() || !hash_equals($flow['state'], $state) || $code === '') {
    google_login_failed('เข้าสู่ระบบด้วย Google ไม่สำเร็จ กรุณาลองอีกครั้ง');
}

$tokens = google_json('https://oauth2.googleapis.com/token', [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'code' => $code,
        'client_id' => $clientId,
        'client_secret' => $secret,
        'redirect_uri' => $redirect,
        'grant_type' => 'authorization_code',
        'code_verifier' => $flow['verifier'],
    ]),
]);
$token = $tokens['access_token'] ?? null;
if (!is_string($token) || !preg_match('~^[A-Za-z0-9._+/=-]+$~D', $token)) {
    google_login_failed('เข้าสู่ระบบด้วย Google ไม่สำเร็จ กรุณาลองอีกครั้ง');
}
$user = google_json('https://openidconnect.googleapis.com/v1/userinfo', [
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
]);
$subject = $user['sub'] ?? null;
$email = $user['email'] ?? null;
if (!is_string($subject) || $subject === '' || strlen($subject) > 255 ||
    !is_string($email) || strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    ($user['email_verified'] ?? false) !== true) {
    google_login_failed('กรุณาใช้บัญชี Google ที่ยืนยันอีเมลแล้ว');
}

$sql = 'SELECT employer_id, company_name FROM EMPLOYER WHERE google_subject = ?';
$account = query($sql, [$subject])->fetch();
if (!$account) {
    try {
        query('INSERT INTO EMPLOYER (email, google_subject) VALUES (?, ?)', [$email, $subject]);
    } catch (PDOException $error) {
        if ((int) ($error->errorInfo[1] ?? 0) !== 1062) throw $error;
    }
    $account = query($sql, [$subject])->fetch();
    if (!$account) {
        google_login_failed('อีเมลนี้มีบัญชีนายจ้างอยู่แล้ว กรุณาเข้าสู่ระบบด้วยวิธีเดิม');
    }
}

session_regenerate_id(true);
$_SESSION = ['auth' => ['role' => 'employer', 'id' => (int) $account['employer_id']]];
go($account['company_name'] === null ? 'employer/profile.php' : 'employer/jobs.php');
