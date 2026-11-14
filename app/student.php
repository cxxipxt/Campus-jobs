<?php
// Called only by a server-side OAuth callback after provider verification.
function stage_verified_student(string $email): void {
    $email = strtolower(trim($email));
    if (!preg_match('/^([a-z0-9._+\-]{1,64})@(kmutt\.ac\.th|mail\.kmutt\.ac\.th)$/D', $email, $parts)) {
        throw new InvalidArgumentException('ต้องใช้อีเมลนักศึกษา KMUTT');
    }
    $id = query('SELECT student_id FROM STUDENT WHERE university_username=?', [$parts[1]])->fetchColumn();
    if ($id) sign_in_student((int) $id);
    session_regenerate_id(true);
    $_SESSION = ['verified_student' => ['email' => $email, 'username' => $parts[1], 'expires' => time() + 900]];
    go('student/confirm.php');
}

function sign_in_student(int $id, bool $profileSetup = false): never {
    session_regenerate_id(true);
    $_SESSION = ['auth' => ['role' => 'student', 'id' => $id]];
    if ($profileSetup) {
        $_SESSION['student_profile_setup'] = true;
    }
    go('dashboard.php');
}

function require_student(): int {
    return identity('student') ?? go('student/login.html#student');
}

function enforce_student_profile_setup(): void {
    $studentId = identity('student');
    if ($studentId === null) {
        return;
    }

    if (!array_key_exists('student_profile_setup', $_SESSION)) {
        $profileCompleted = query(
            'SELECT profile_completed FROM STUDENT WHERE student_id = ?',
            [$studentId]
        )->fetchColumn();

        if ($profileCompleted === false) {
            http_response_code(404);
            exit('ไม่พบโปรไฟล์');
        }

        $_SESSION['student_profile_setup'] = (int) $profileCompleted === 0;
    }

    if (empty($_SESSION['student_profile_setup'])) {
        return;
    }

    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $dashboardPath = parse_url(url('dashboard.php'), PHP_URL_PATH);
    $profilePath = parse_url(url('student/profile.php'), PHP_URL_PATH);
    $logoutPath = parse_url(url('student/logout.php'), PHP_URL_PATH);

    if ($path === $dashboardPath) {
        return;
    }

    if (
        ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && ($path === $profilePath || $path === $logoutPath)
    ) {
        return;
    }

    go('dashboard.php');
}

function student_resume_link(string $url): ?string {
    $url = optional_text($url, 2048, 'ลิงก์เรซูเม่');
    if ($url !== null && (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('~^https?://~', $url))) {
        throw new InvalidArgumentException('ลิงก์เรซูเม่ต้องเป็น URL แบบ HTTP หรือ HTTPS');
    }
    return $url;
}
