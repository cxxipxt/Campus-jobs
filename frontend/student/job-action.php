<?php
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$id = require_student();
check_csrf();
try { $jobId = positive_id(input('job_id')); }
catch (InvalidArgumentException $ex) { http_response_code(400); exit('รหัสงานไม่ถูกต้อง'); }
$action = input('action');
if (!in_array($action, ['apply','save','unsave'], true)) { http_response_code(400); exit('คำสั่งไม่ถูกต้อง'); }

db()->beginTransaction();
try {
    $status = query('SELECT status FROM JOB WHERE job_id=? FOR UPDATE', [$jobId])->fetchColumn();
    if ($status === false) { db()->rollBack(); http_response_code(404); exit('ไม่พบประกาศงาน'); }
    if ($action === 'unsave') {
        query('DELETE FROM SAVED_JOB WHERE student_id=? AND job_id=?', [$id,$jobId]);
        $message = 'ยกเลิกบันทึกงานแล้ว';
    } elseif ($status !== 'Open') {
        $message = 'งานนี้ปิดรับสมัครแล้ว';
    } elseif ($action === 'apply') {
        if (query('SELECT application_id FROM APPLICATION WHERE student_id=? AND job_id=?', [$id,$jobId])->fetchColumn()) {
            $message = 'คุณสมัครงานนี้แล้ว';
        } else {
            query('INSERT INTO APPLICATION (student_id,job_id) VALUES (?,?)', [$id,$jobId]);
            $message = 'ส่งใบสมัครแล้ว';
        }
    } else {
        query('INSERT INTO SAVED_JOB (student_id,job_id) VALUES (?,?) ON DUPLICATE KEY UPDATE saved_at=SAVED_JOB.saved_at', [$id,$jobId]);
        $message = 'บันทึกงานแล้ว';
    }
    db()->commit();
} catch (Throwable $ex) { db()->rollBack(); throw $ex; }
$_SESSION['flash'] = $message;
go(input('return') === 'saved' ? 'saved.php' : 'job-detail.php?job_id='.$jobId);
