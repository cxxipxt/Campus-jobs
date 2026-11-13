<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/student-auth.php';

$method = $_SERVER['REQUEST_METHOD'] ?? '';
$action = $method === 'POST' ? input('action') : 'login';
$provider = strtolower(input('provider', $method === 'POST' ? $_POST : $_GET));

if (!in_array($provider, ['microsoft', 'google'], true)) {
    http_response_code(400);
    exit('Invalid login provider');
}

$intent = [
    'type' => $action,
    'provider' => $provider,
    'created_at' => time(),
];

if ($action === 'link') {
    // Linking must be initiated by an authenticated student using POST.
    if ($method !== 'POST') {
        http_response_code(405);
        exit('Method not allowed');
    }

    check_csrf();

    $studentId = identity('student');

    if ($studentId === null) {
        http_response_code(403);
        exit('กรุณาเข้าสู่ระบบนักศึกษาก่อนเชื่อมบัญชี');
    }

    // A student can link only one account per provider.
    $existingSubject = query(
        'SELECT provider_subject
         FROM STUDENT_OAUTH
         WHERE student_id = ? AND provider = ?',
        [$studentId, $provider]
    )->fetchColumn();

    if ($existingSubject !== false) {
        $_SESSION['flash'] = 'บัญชีผู้ให้บริการนี้เชื่อมกับบัญชีของคุณแล้ว';
        go('student/profile.php');
    }

    $intent['student_id'] = $studentId;

} elseif ($action === 'login') {
    // Login must use GET, as in the existing login page.
    if ($method !== 'GET') {
        http_response_code(405);
        exit('Method not allowed');
    }

    // Do not accidentally replace an existing account session.
    if (identity('student') !== null) {
        go('dashboard.php');
    }

    if (isset($_SESSION['auth']) && is_array($_SESSION['auth'])) {
        http_response_code(403);
        exit('กรุณาออกจากระบบปัจจุบันก่อนเข้าสู่ระบบนักศึกษา');
    }

} else {
    http_response_code(400);
    exit('Invalid OAuth action');
}

try {
    $config = student_auth_config($provider);

    $oidc = new CampusJobsOpenIDConnectClient(
        $config['provider_url'],
        $config['client_id'],
        $config['client_secret'],
        $config['issuer']
    );

    $oidc->setRedirectURL($config['redirect_uri']);
    $oidc->addScope(['openid', 'profile', 'email']);
    $oidc->setCodeChallengeMethod('S256');

    // Store the purpose of this OAuth attempt in the server-side session.
    unset(
        $_SESSION['student_oauth_pending'],
        $_SESSION['student_oauth_provider'],
        $_SESSION['student_oauth_intent']
    );

    $_SESSION['student_oauth_intent'] = $intent;

    // Keep this temporarily for compatibility with the current callback.
    $_SESSION['student_oauth_provider'] = $provider;

    $oidc->authenticate();

} catch (Throwable $error) {
    if (isset($oidc) && $oidc instanceof CampusJobsOpenIDConnectClient) {
        $oidc->clearOAuthAttempt();
    }

    unset(
        $_SESSION['student_oauth_intent'],
        $_SESSION['student_oauth_provider']
    );

    error_log(
        'Student ' . $provider . ' OAuth start failed: ' . get_class($error)
    );

    if ($action === 'link' && identity('student') !== null) {
        $_SESSION['flash'] = 'ไม่สามารถเริ่มเชื่อมบัญชีได้ กรุณาลองใหม่';
        go('student/profile.php');
    }

    http_response_code(503);
    exit('ไม่สามารถเริ่มต้นการเข้าสู่ระบบได้ กรุณาลองใหม่');
}
