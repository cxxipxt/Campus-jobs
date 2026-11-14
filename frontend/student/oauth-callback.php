<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/student-auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    exit('Method not allowed');
}

// Read the purpose of this OAuth attempt from the server-side session.
$intent = $_SESSION['student_oauth_intent'] ?? null;

if (!is_array($intent)) {
    http_response_code(400);
    exit('ไม่พบรายการเข้าสู่ระบบ กรุณาเริ่มใหม่');
}

$flowType = $intent['type'] ?? '';
$provider = $intent['provider'] ?? '';
$createdAt = $intent['created_at'] ?? null;

if (
    !in_array($flowType, ['login', 'link'], true) ||
    !is_string($provider) ||
    !in_array($provider, ['microsoft', 'google'], true) ||
    !is_int($createdAt) ||
    $createdAt > time() ||
    time() - $createdAt > 600 ||
    ($_SESSION['student_oauth_provider'] ?? null) !== $provider
) {
    unset(
        $_SESSION['student_oauth_intent'],
        $_SESSION['student_oauth_provider']
    );

    http_response_code(400);
    exit('รายการเข้าสู่ระบบหมดอายุ กรุณาเริ่มใหม่');
}

// Linking must remain attached to the same authenticated student.
if ($flowType === 'link') {
    $intentStudentId = $intent['student_id'] ?? null;

    if (
        !is_int($intentStudentId) ||
        $intentStudentId < 1 ||
        identity('student') !== $intentStudentId
    ) {
        unset(
            $_SESSION['student_oauth_intent'],
            $_SESSION['student_oauth_provider']
        );

        http_response_code(403);
        exit('สถานะเข้าสู่ระบบเปลี่ยนแปลง กรุณาเริ่มเชื่อมบัญชีใหม่');
    }
} elseif (identity('student') !== null) {
    // Do not let a login callback replace an existing student session.
    unset(
        $_SESSION['student_oauth_intent'],
        $_SESSION['student_oauth_provider']
    );

    http_response_code(409);
    exit('สถานะเข้าสู่ระบบเปลี่ยนแปลง กรุณาเริ่มยืนยันบัญชีใหม่');
}

$oidc = null;

try {
    // Load the selected provider configuration.
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

    // Get the original state and nonce from the PHP session.
    $expectedState = $oidc->expectedState();
    $expectedNonce = $oidc->expectedNonce();

    $receivedState = $_GET['state'] ?? '';

    // Validate state before exchanging the authorization code.
    if (
        !is_string($expectedState) ||
        $expectedState === '' ||
        !is_string($receivedState) ||
        $receivedState === '' ||
        !hash_equals($expectedState, $receivedState)
    ) {
        throw new RuntimeException('Invalid OAuth state');
    }

    if (isset($_GET['error'])) {
        throw new RuntimeException('Provider authorization failed');
    }

    if (
        !is_string($expectedNonce) ||
        $expectedNonce === '' ||
        !isset($_GET['code']) ||
        !is_string($_GET['code']) ||
        $_GET['code'] === ''
    ) {
        throw new RuntimeException('Invalid OAuth callback parameters');
    }

    // Exchange the authorization code and validate the ID token.
    $oidc->authenticate();

    $claims = $oidc->getVerifiedClaims();

    if (!is_object($claims)) {
        throw new RuntimeException('Missing verified claims');
    }

    // Validate issuer.
    if (
        !isset($claims->iss) ||
        !is_string($claims->iss) ||
        !hash_equals($config['issuer'], $claims->iss)
    ) {
        throw new RuntimeException('Invalid issuer');
    }

    // Validate audience.
    $audiences = $claims->aud ?? null;

    if (is_string($audiences)) {
        $audiences = [$audiences];
    }

    if (
        !is_array($audiences) ||
        !in_array($config['client_id'], $audiences, true)
    ) {
        throw new RuntimeException('Invalid audience');
    }

    // Validate authorized party when present or when multiple audiences exist.
    if (
        (count($audiences) > 1 || isset($claims->azp)) &&
        (
            !isset($claims->azp) ||
            !is_string($claims->azp) ||
            !hash_equals($config['client_id'], $claims->azp)
        )
    ) {
        throw new RuntimeException('Invalid authorized party');
    }

    // Validate nonce.
    if (
        !isset($claims->nonce) ||
        !is_string($claims->nonce) ||
        !hash_equals($expectedNonce, $claims->nonce)
    ) {
        throw new RuntimeException('Invalid nonce');
    }

    // Validate token expiration and issue time.
    $now = time();

    if (
        !isset($claims->exp) ||
        !is_int($claims->exp) ||
        $claims->exp <= $now
    ) {
        throw new RuntimeException('Invalid token expiration');
    }

    if (
        !isset($claims->iat) ||
        !is_int($claims->iat) ||
        $claims->iat > $now + 300
    ) {
        throw new RuntimeException('Invalid token issue time');
    }

    // The subject is the stable provider identity used for account lookup.
    $subject = $claims->sub ?? null;

    if (
        !is_string($subject) ||
        $subject === '' ||
        strlen($subject) > 255
    ) {
        throw new RuntimeException('Invalid provider subject');
    }

    // Validate provider-specific claims and email domain.
    if ($provider === 'microsoft') {
        $tenantId = strtolower(
            student_auth_env('MICROSOFT_TENANT_ID')
        );

        if (
            !isset($claims->tid) ||
            !is_string($claims->tid) ||
            !hash_equals($tenantId, strtolower($claims->tid))
        ) {
            throw new RuntimeException('Invalid Microsoft tenant');
        }

        $allowedDomain = 'kmutt.ac.th';
    } else {
        // Google must confirm that the account email is verified.
        if (($claims->email_verified ?? null) !== true) {
            throw new RuntimeException('Google email is not verified');
        }

        // The hosted domain claim must match the configured university domain.
        $hostedDomain = strtolower($config['hosted_domain']);

        if (
            !isset($claims->hd) ||
            !is_string($claims->hd) ||
            !hash_equals($hostedDomain, strtolower($claims->hd))
        ) {
            throw new RuntimeException('Invalid Google hosted domain');
        }

        $allowedDomain = $hostedDomain;
    }

    // Use only the email claim from the verified ID token.
    $email = $claims->email ?? null;

    if (
        !is_string($email) ||
        $email === '' ||
        strlen($email) > 255 ||
        filter_var($email, FILTER_VALIDATE_EMAIL) === false
    ) {
        throw new RuntimeException('Verified email is unavailable');
    }

    $at = strrpos($email, '@');

    if ($at === false) {
        throw new RuntimeException('Invalid email address');
    }

    $localPart = substr($email, 0, $at);
    $domain = strtolower(substr($email, $at + 1));

    if (
        $domain !== $allowedDomain ||
        !preg_match('/^[A-Za-z0-9._-]{1,64}$/D', $localPart)
    ) {
        throw new RuntimeException('Email domain is not allowed');
    }

    $username = strtolower($localPart);

    // Read the display name from the verified ID token.
    $providerName = '';

    if (isset($claims->name) && is_string($claims->name)) {
        $providerName = mb_substr(
            trim($claims->name),
            0,
            150,
            'UTF-8'
        );
    }

    // Clear temporary OAuth state after successful verification.
    $oidc->clearOAuthAttempt();

    unset($_SESSION['student_oauth_provider']);

    // Handle account linking separately from normal login.
    if ($flowType === 'link') {
        $studentId = $intent['student_id'];

        // Check whether this provider identity is already linked.
        $mappedStudentId = query(
            'SELECT student_id
             FROM STUDENT_OAUTH
             WHERE provider = ? AND provider_subject = ?',
            [$provider, $subject]
        )->fetchColumn();

        if ($mappedStudentId !== false) {
            if ((int) $mappedStudentId !== $studentId) {
                throw new RuntimeException(
                    'This provider identity belongs to another student'
                );
            }

            // It is already linked to this same student.
            unset($_SESSION['student_oauth_intent']);

            $_SESSION['flash'] = 'บัญชีนี้เชื่อมต่ออยู่แล้ว';
            go('student/profile.php');
        }

        // A student may have only one identity per provider.
        $existingSubject = query(
            'SELECT provider_subject
             FROM STUDENT_OAUTH
             WHERE student_id = ? AND provider = ?',
            [$studentId, $provider]
        )->fetchColumn();

        if ($existingSubject !== false) {
            throw new RuntimeException(
                'This student already has an account for this provider'
            );
        }

        // Insert the new mapping without changing the current login session.
        $pdo = db();
        $pdo->beginTransaction();

        try {
            query(
                'INSERT INTO STUDENT_OAUTH
                    (student_id, provider, provider_subject)
                 VALUES (?, ?, ?)',
                [$studentId, $provider, $subject]
            );

            $pdo->commit();

        } catch (Throwable $insertError) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Check whether another request linked the same identity meanwhile.
            if (
                $insertError instanceof PDOException &&
                $insertError->getCode() === '23000'
            ) {
                $mappedStudentId = query(
                    'SELECT student_id
                     FROM STUDENT_OAUTH
                     WHERE provider = ? AND provider_subject = ?',
                    [$provider, $subject]
                )->fetchColumn();

                if (
                    $mappedStudentId !== false &&
                    (int) $mappedStudentId === $studentId
                ) {
                    unset($_SESSION['student_oauth_intent']);

                    $_SESSION['flash'] = 'บัญชีนี้เชื่อมต่ออยู่แล้ว';
                    go('student/profile.php');
                }

                $existingSubject = query(
                    'SELECT provider_subject
                     FROM STUDENT_OAUTH
                     WHERE student_id = ? AND provider = ?',
                    [$studentId, $provider]
                )->fetchColumn();

                if ($mappedStudentId !== false || $existingSubject !== false) {
                    throw new RuntimeException(
                        'This provider identity cannot be linked'
                    );
                }
            }

            throw $insertError;
        }

        unset($_SESSION['student_oauth_intent']);

        $_SESSION['flash'] = 'เชื่อมบัญชี ' .
            ($provider === 'microsoft' ? 'Microsoft' : 'Google') .
            ' เรียบร้อยแล้ว';

        go('student/profile.php');
    }

    // Normal login: find an existing mapping by provider subject.
    $linkedStudentId = query(
        'SELECT student_id
         FROM STUDENT_OAUTH
         WHERE provider = ? AND provider_subject = ?',
        [$provider, $subject]
    )->fetchColumn();

    if ($linkedStudentId !== false) {
        unset(
            $_SESSION['student_oauth_intent'],
            $_SESSION['student_oauth_pending']
        );

        sign_in_student((int) $linkedStudentId);
    }

    // No mapping exists: continue to registration.
    // Never automatically link accounts by email or username.
    unset($_SESSION['student_oauth_intent']);

    $_SESSION['student_oauth_pending'] = [
        'provider' => $provider,
        'provider_subject' => $subject,
        'email' => $email,
        'username' => $username,
        'name' => $providerName,
        'created_at' => time(),
    ];

    go('student/register.php');

} catch (Throwable $error) {
    if ($oidc instanceof CampusJobsOpenIDConnectClient) {
        $oidc->clearOAuthAttempt();
    }

    unset(
        $_SESSION['student_oauth_intent'],
        $_SESSION['student_oauth_provider']
    );

    error_log(
        'Student ' . $provider . ' OAuth callback failed: ' .
        get_class($error)
    );

    if ($flowType === 'link' && identity('student') !== null) {
        $_SESSION['flash'] = 'ไม่สามารถเชื่อมบัญชีได้ บัญชีอาจถูกเชื่อมกับผู้ใช้อื่นแล้ว หรือเกิดข้อผิดพลาด กรุณาลองใหม่';
        go('student/profile.php');
    }

    unset($_SESSION['student_oauth_pending']);

    http_response_code(403);
    exit('ไม่สามารถยืนยันบัญชีได้ กรุณาเริ่มเข้าสู่ระบบใหม่');
}
