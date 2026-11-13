<?php
declare(strict_types=1);

function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $path): string { return rtrim(getenv('APP_BASE_PATH') ?: '', '/') . '/' . ltrim($path, '/'); }
function go(string $path): never { header('Location: ' . url($path), true, 303); exit; }
function input(string $key, ?array $source = null): string {
    $value = ($source ?? $_POST)[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}
function csrf_field(): void {
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    echo '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}
function check_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], input('csrf'))) {
        http_response_code(403); exit('คำขอหมดอายุ กรุณาเปิดหน้าใหม่แล้วลองอีกครั้ง');
    }
}
function identity(string $role): ?int {
    $auth = $_SESSION['auth'] ?? null;
    if (!is_array($auth) || ($auth['role'] ?? null) !== $role) return null;
    $id = filter_var($auth['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? null : $id;
}
function employer(): int { $id = identity('employer'); if (!$id) go('employer/login.php'); return $id; }
function required_text(string $value, int $max, string $label): void {
    if ($value === '' || mb_strlen($value, 'UTF-8') > $max) throw new InvalidArgumentException("กรุณากรอก$label ไม่เกิน $max ตัวอักษร");
}
function optional_text(string $value, int $max, string $label): ?string {
    if ($value === '') return null;
    required_text($value, $max, $label); return $value;
}
function money(string $value): string {
    if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $value)) throw new InvalidArgumentException('ค่าจ้างต้องเป็นเลขไม่ติดลบ ไม่เกิน 99999999.99 บาท และทศนิยมไม่เกิน 2 ตำแหน่ง');
    return $value;
}
function positive_id(string $value): int {
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4294967295]]);
    if ($id === false) throw new InvalidArgumentException('รหัสรายการไม่ถูกต้อง');
    return $id;
}
function query(string $sql, array $params = []): PDOStatement { $stmt = db()->prepare($sql); $stmt->execute($params); return $stmt; }
function owned_job(int $jobId, int $owner, bool $lock = false): array {
    $job = query('SELECT * FROM JOB WHERE job_id = ? AND employer_id = ?' . ($lock ? ' FOR UPDATE' : ''), [$jobId, $owner])->fetch();
    if (!$job) { http_response_code(404); exit('ไม่พบประกาศงาน'); } return $job;
}
function statuses(): array { return ['Pending' => 'รอพิจารณา', 'Reviewing' => 'กำลังพิจารณา', 'Accepted' => 'ได้รับเลือก', 'Rejected' => 'ไม่ผ่านการพิจารณา']; }
function thai_time(string $utc): string { return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Bangkok'))->format('d/m/Y H:i'); }
function start_page(string $title, string $layout = '', bool $showHeading = true): void {
    echo '<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . ' | CampusJobs</title><link rel="stylesheet" href="' . e(url('assets/styles/campus.css')) . '"><script defer src="' . e(url('assets/campus.js')) . '"></script></head><body class="' . e($layout) . '"><a class="skip" href="#main">ข้ามไปเนื้อหาหลัก</a><header class="campus-header"><a class="brand" href="' . e(url('index.html')) . '">CampusJobs</a><nav aria-label="เมนูหลัก">';
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $links = ['jobs.php' => 'ค้นหางาน'];
    if (identity('student')) $links += ['dashboard.php' => 'ภาพรวม', 'my-applications.php' => 'การสมัครของฉัน', 'saved.php' => 'งานที่บันทึก', 'student/profile.php' => 'โปรไฟล์'];
    if (identity('employer')) $links += ['employer/jobs.php' => 'จัดการประกาศ', 'employer/profile.php' => 'ข้อมูลบริษัท'];
    foreach ($links as $path => $label) echo '<a href="' . e(url($path)) . '"' . ($currentPath === url($path) ? ' aria-current="page"' : '') . '>' . e($label) . '</a>';
    if (identity('employer')) { echo '<form method="post" action="' . e(url('employer/logout.php')) . '">'; csrf_field(); echo '<button>ออกจากระบบ</button></form>'; }
    else echo '<a href="' . e(url('employer/login.php')) . '">เข้าสู่ระบบนายจ้าง</a>';
    echo '</nav></header><main id="main">';
    if ($showHeading) echo '<h1>' . e($title) . '</h1>';
    if (isset($_SESSION['flash'])) { echo '<p class="notice" role="status">' . e($_SESSION['flash']) . '</p>'; unset($_SESSION['flash']); }
}
function end_page(): void { echo '</main><footer>CampusJobs · งานพาร์ทไทม์สำหรับนักศึกษา</footer></body></html>'; }
function error_box(?string $error): void { if ($error) echo '<p class="error" role="alert">' . e($error) . '</p>'; }
function field(string $name, string $label, string $value = '', string $type = 'text', bool $required = true, int $max = 255): void {
    echo '<p><label for="' . e($name) . '">' . e($label) . '</label><input id="' . e($name) . '" name="' . e($name) . '" type="' . e($type) . '" maxlength="' . $max . '" value="' . e($value) . '"' . ($required ? ' required' : '') . '></p>';
}
