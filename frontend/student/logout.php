
<?php
require dirname(__DIR__, 2) . '/app/bootstrap.php';

// ตรวจสอบ CSRF ก่อนออกจากระบบ
check_csrf();

// ล้างข้อมูล Session
$_SESSION = [];

// ลบ Session Cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => $params['path'],
        'secure' => $params['secure'],
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

// ทำลาย Session
session_destroy();

// กลับไปหน้า Student Login
go('student/login.html');
