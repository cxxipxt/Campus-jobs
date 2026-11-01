<?php
declare(strict_types=1);

// Keep this directory and .env OUTSIDE the public document root (frontend).
foreach (is_file(dirname(__DIR__) . '/.env') ? file(dirname(__DIR__) . '/.env', FILE_IGNORE_NEW_LINES) : [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
    [$key, $value] = explode('=', $line, 2);
    if (getenv(trim($key)) === false) putenv(trim($key) . '=' . trim($value, " \t\n\r\0\x0B\"'"));
}
date_default_timezone_set('UTC');
ini_set('session.use_strict_mode', '1');
session_name('campus_jobs');
session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax', 'path' => '/']);
session_start();
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
set_exception_handler(function (Throwable $error): void {
    // Do not log SQL parameters, passwords or personal data.
    error_log('Campus-jobs error: ' . get_class($error) . ' code ' . $error->getCode());
    http_response_code(500);
    echo '<!doctype html><html lang="th"><meta charset="utf-8"><title>ระบบขัดข้อง</title><h1>ระบบขัดข้องชั่วคราว</h1><p>กรุณาลองอีกครั้ง หรือตรวจการตั้งค่าฐานข้อมูลกับผู้ดูแล</p></html>';
});
require_once __DIR__ . '/functions.php';

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_NAME') ?: 'campus_jobs';
        foreach ([$host, $port, $name] as $piece) if (str_contains($piece, ';')) throw new RuntimeException('Invalid DB config');
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", getenv('DB_USER') ?: 'campus_jobs_app', getenv('DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
        $pdo->exec("SET SESSION time_zone = '+00:00'");
        $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
    }
    return $pdo;
}
