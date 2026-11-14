<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

$error = null;
$pending = $_SESSION['student_oauth_pending'] ?? null;

// The pending identity must come from a verified OAuth callback.
if (!is_array($pending)) {
    go('student/login.html');
}

// Validate pending identity expiration.
$createdAt = $pending['created_at'] ?? null;

if (
    !is_int($createdAt) ||
    $createdAt > time() ||
    time() - $createdAt > 600
) {
    unset($_SESSION['student_oauth_pending']);
    go('student/login.html');
}

// Read verified identity from the server-side session.
$provider = $pending['provider'] ?? null;
$subject = $pending['provider_subject'] ?? null;
$email = $pending['email'] ?? null;
$username = $pending['username'] ?? null;
$providerName = $pending['name'] ?? '';

$allowedDomains = [
    'microsoft' => 'kmutt.ac.th',
    'google' => 'mail.kmutt.ac.th',
];

// Validate identity data.
if (
    !is_string($provider) ||
    !isset($allowedDomains[$provider]) ||
    !is_string($subject) ||
    $subject === '' ||
    strlen($subject) > 255 ||
    !is_string($email) ||
    !is_string($username) ||
    !is_string($providerName)
) {
    unset($_SESSION['student_oauth_pending']);
    http_response_code(400);
    exit('ข้อมูลบัญชีไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่');
}

$at = strrpos($email, '@');

if ($at === false) {
    unset($_SESSION['student_oauth_pending']);
    http_response_code(400);
    exit('อีเมลไม่ถูกต้อง');
}

$emailPrefix = substr($email, 0, $at);
$emailDomain = strtolower(substr($email, $at + 1));

if (
    strlen($email) > 255 ||
    filter_var($email, FILTER_VALIDATE_EMAIL) === false ||
    $emailDomain !== $allowedDomains[$provider] ||
    $username !== strtolower($emailPrefix) ||
    !preg_match('/^[a-z0-9._-]{1,64}$/D', $username)
) {
    unset($_SESSION['student_oauth_pending']);
    http_response_code(403);
    exit('บัญชีนี้ไม่ได้รับอนุญาตให้สมัครสมาชิก');
}

/*
 * Match an existing student account.
 *
 * KMUTT accounts may use either:
 * - kmutt.ac.th
 * - mail.kmutt.ac.th
 *
 * The email local-part must still match the university username.
 * The existing STUDENT email is never overwritten.
 */
$matchesExistingUniversityAccount = static function (
    array $account
) use ($username): bool {
    $storedUsername = strtolower(
        (string) ($account['university_username'] ?? '')
    );

    $storedEmail = strtolower(
        trim((string) ($account['email'] ?? ''))
    );

    if (
        $storedUsername === '' ||
        !hash_equals($username, $storedUsername) ||
        filter_var($storedEmail, FILTER_VALIDATE_EMAIL) === false
    ) {
        return false;
    }

    $storedAt = strrpos($storedEmail, '@');

    if ($storedAt === false) {
        return false;
    }

    $storedEmailPrefix = substr($storedEmail, 0, $storedAt);
    $storedEmailDomain = strtolower(
        substr($storedEmail, $storedAt + 1)
    );

    $recognizedUniversityDomains = [
        'kmutt.ac.th',
        'mail.kmutt.ac.th',
    ];

    return hash_equals($username, $storedEmailPrefix)
        && in_array(
            $storedEmailDomain,
            $recognizedUniversityDomains,
            true
        );
};

// Check whether this exact provider identity is already linked.
$linkedStudentId = query(
    'SELECT student_id
     FROM STUDENT_OAUTH
     WHERE provider = ? AND provider_subject = ?',
    [$provider, $subject]
)->fetchColumn();

if ($linkedStudentId !== false) {
    unset($_SESSION['student_oauth_pending']);
    sign_in_student((int) $linkedStudentId);
}

// Find an existing student account by username or email.
$existing = query(
    'SELECT student_id, university_username, email
     FROM STUDENT
     WHERE university_username = ? OR email = ?
     LIMIT 2',
    [$username, $email]
)->fetchAll();

$existingStudentId = null;

if (count($existing) === 1) {
    $account = $existing[0];

    if ($matchesExistingUniversityAccount($account)) {
        $existingStudentId = (int) $account['student_id'];

        $error = 'พบบัญชีเดิมของคุณในระบบ สามารถยืนยันเพื่อเชื่อมบัญชี ' .
            ucfirst($provider) . ' ได้';
    } else {
        $error = 'พบข้อมูลอีเมลหรือชื่อผู้ใช้ที่ไม่ตรงกัน กรุณาติดต่อผู้ดูแลระบบ';
    }
} elseif (count($existing) > 1) {
    $error = 'พบข้อมูลบัญชีซ้ำ กรุณาติดต่อผู้ดูแลระบบ';
}

// Handle explicit linking to an existing student account.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'link_existing'
) {
    try {
        check_csrf();

        if ($existingStudentId === null) {
            throw new RuntimeException(
                'ไม่พบบัญชีเดิมที่สามารถเชื่อมได้'
            );
        }

        $pdo = db();
        $pdo->beginTransaction();

        try {
            // Re-check the existing account inside the transaction.
            $account = query(
                'SELECT student_id, university_username, email
                 FROM STUDENT
                 WHERE student_id = ?
                 FOR UPDATE',
                [$existingStudentId]
            )->fetch();

            if (
                !$account ||
                !$matchesExistingUniversityAccount($account)
            ) {
                throw new RuntimeException(
                    'ข้อมูลบัญชีไม่ตรงกัน กรุณาติดต่อผู้ดูแลระบบ'
                );
            }

            // Check whether this exact OAuth identity is already linked.
            $linkedStudentId = query(
                'SELECT student_id
                 FROM STUDENT_OAUTH
                 WHERE provider = ? AND provider_subject = ?',
                [$provider, $subject]
            )->fetchColumn();

            if ($linkedStudentId !== false) {
                if ((int) $linkedStudentId !== $existingStudentId) {
                    throw new RuntimeException(
                        'บัญชีนี้เชื่อมกับบัญชีนักศึกษาอื่นแล้ว'
                    );
                }

                $pdo->commit();
                unset($_SESSION['student_oauth_pending']);
                sign_in_student($existingStudentId);
            }

            // Check whether this student already linked this provider.
            $otherProviderAccount = query(
                'SELECT oauth_id
                 FROM STUDENT_OAUTH
                 WHERE student_id = ? AND provider = ?',
                [$existingStudentId, $provider]
            )->fetchColumn();

            if ($otherProviderAccount !== false) {
                throw new RuntimeException(
                    'บัญชีเดิมมีการเชื่อมผู้ให้บริการนี้ไว้แล้ว'
                );
            }

            // Link the verified OAuth identity to the existing account.
            query(
                'INSERT INTO STUDENT_OAUTH
                    (student_id, provider, provider_subject)
                 VALUES (?, ?, ?)',
                [$existingStudentId, $provider, $subject]
            );

            $pdo->commit();

        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }

        // Sign in after the link has been saved successfully.
        unset($_SESSION['student_oauth_pending']);
        sign_in_student($existingStudentId);

    } catch (PDOException $exception) {
        error_log(
            'Student account linking failed: ' . get_class($exception)
        );

        $error = 'ไม่สามารถเชื่อมบัญชีได้ บัญชีอาจถูกเชื่อมไปแล้ว กรุณาลองใหม่';

    } catch (RuntimeException $exception) {
        $error = $exception->getMessage();

    } catch (Throwable $exception) {
        error_log(
            'Student account linking failed: ' . get_class($exception)
        );

        $error = 'เกิดข้อผิดพลาดในการเชื่อมบัญชี กรุณาลองใหม่';
    }
}

// Use the verified provider name for account creation; profile details are
// completed from the dashboard after the student signs in.
$fullName = $providerName !== '' ? $providerName : $username;

// Create the account after the OAuth callback has verified the university
// identity. Profile details are collected from the dashboard after sign-in.
if (
    $_SERVER['REQUEST_METHOD'] === 'GET' &&
    $error === null &&
    $existingStudentId === null
) {
    try {
        required_text($fullName, 150, 'ชื่อ-นามสกุล');

        $pdo = db();
        $pdo->beginTransaction();

        try {
            // Create the student account.
            query(
                'INSERT INTO STUDENT
                    (university_username, full_name, email, phone, major, resume_url, profile_completed)
                 VALUES (?, ?, ?, ?, ?, ?, 0)',
                [
                    $username,
                    $fullName,
                    $email,
                    null,
                    null,
                    null,
                ]
            );

            $studentId = (int) $pdo->lastInsertId();

            // Link the verified OAuth identity to the new student.
            query(
                'INSERT INTO STUDENT_OAUTH
                    (student_id, provider, provider_subject)
                 VALUES (?, ?, ?)',
                [
                    $studentId,
                    $provider,
                    $subject,
                ]
            );

            $pdo->commit();

        } catch (Throwable $insertError) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $insertError;
        }

        unset($_SESSION['student_oauth_pending']);
        sign_in_student($studentId, true);

    } catch (PDOException $exception) {
        error_log(
            'Student registration failed: ' . get_class($exception)
        );

        $error = 'ไม่สามารถสร้างบัญชีได้ บัญชีอาจมีอยู่แล้ว กรุณาลองใหม่';

    } catch (InvalidArgumentException | RuntimeException $exception) {
        $error = $exception->getMessage();

    } catch (Throwable $exception) {
        error_log(
            'Student registration failed: ' . get_class($exception)
        );

        $error = 'ไม่สามารถสร้างบัญชีได้ กรุณาลองใหม่อีกครั้ง';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ยืนยันบัญชีนักศึกษา | Campus-jobs</title>
</head>
<body>
<a href="#main">ข้ามไปเนื้อหาหลัก</a>

<header>
    <p><a href="../index.html"><strong>Campus-jobs</strong></a></p>
</header>

<main id="main">
    <h1>ยืนยันบัญชีมหาวิทยาลัย</h1>

    <section>
        <h2>บัญชีมหาวิทยาลัยที่ยืนยันแล้ว</h2>
        <dl>
            <dt>ผู้ให้บริการ</dt>
            <dd><?= e($provider) ?></dd>
            <dt>อีเมลมหาวิทยาลัย</dt>
            <dd><?= e($email) ?></dd>
            <dt>ชื่อผู้ใช้มหาวิทยาลัย</dt>
            <dd><?= e($username) ?></dd>
        </dl>
        <p><a href="login.html">เข้าสู่ระบบด้วยบัญชีอื่น</a></p>
    </section>

    <?php error_box($error); ?>

    <?php if ($existingStudentId !== null): ?>
        <section>
            <h2>พบบัญชีเดิมในระบบ</h2>
            <p>
                บัญชีมหาวิทยาลัยนี้ตรงกับบัญชีนักศึกษาที่มีอยู่แล้ว
                หากเป็นบัญชีของคุณ สามารถกดยืนยันเพื่อเชื่อมบัญชี
                <?= e(ucfirst($provider)) ?> ได้
            </p>
            <form method="post" action="<?= e(url('student/register.php')) ?>">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="link_existing">
                <button type="submit">
                    ยืนยันและเชื่อมบัญชี <?= e(ucfirst($provider)) ?>
                </button>
            </form>
        </section>
    <?php endif; ?>
</main>

</body>
</html>
