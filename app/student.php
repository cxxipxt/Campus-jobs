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

function sign_in_student(int $id): never {
    session_regenerate_id(true);
    $_SESSION = ['auth' => ['role' => 'student', 'id' => $id]];
    go('dashboard.php');
}

function require_student(): int {
    return identity('student') ?? go('student/login.html#student');
}

function student_resume_link(string $url): ?string {
    $url = optional_text($url, 2048, 'ลิงก์เรซูเม่');
    if ($url !== null && (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('~^https?://~', $url))) {
        throw new InvalidArgumentException('ลิงก์เรซูเม่ต้องเป็น URL แบบ HTTP หรือ HTTPS');
    }
    return $url;
}
