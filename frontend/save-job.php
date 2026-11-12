<?php
require dirname(__DIR__) . '/app/bootstrap.php';

// ตรวจสอบว่านักศึกษาเข้าสู่ระบบแล้วหรือไม่
$student = identity('student');

if (!$student) {
    go('student/login.html');
}

// รับเฉพาะ POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    go('jobs.php');
}

// ตรวจสอบ CSRF
check_csrf();

// ตรวจสอบ job_id
try {
    $jobId = positive_id(input('job_id', $_POST));
} catch (InvalidArgumentException $ex) {
    http_response_code(400);
    exit('รหัสงานไม่ถูกต้อง');
}

// ตรวจสอบว่าประกาศงานมีอยู่จริง
$job = query(
    'SELECT job_id FROM JOB WHERE job_id = ?',
    [$jobId]
)->fetch();

if (!$job) {
    http_response_code(404);
    exit('ไม่พบประกาศงาน');
}

// เพิ่มงานลงรายการที่บันทึก
query(
    'INSERT INTO SAVED_JOB (student_id, job_id)
     VALUES (?, ?)
     ON DUPLICATE KEY UPDATE saved_at = CURRENT_TIMESTAMP',
    [$student, $jobId]
);

// กลับไปหน้ารายการที่บันทึก
go('saved.php');
