<?php
require dirname(__DIR__, 2) . '/app/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}
admin();
check_csrf();
$_SESSION = [];
session_regenerate_id(true);
go('admin/login.php');
