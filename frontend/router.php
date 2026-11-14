<?php
// Development server: php -S 127.0.0.1:8000 -t frontend frontend/router.php
$routes=['/jobs.html'=>'/jobs.php','/job-detail.html'=>'/job-detail.php','/my-applications.html'=>'/my-applications.php','/employer/login.html'=>'/employer/login.php','/employer/register.html'=>'/employer/register.php','/employer/profile.html'=>'/employer/profile.php','/employer/jobs.html'=>'/employer/jobs.php','/employer/job-form.html'=>'/employer/job-form.php'];
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$routes += [
    '/dashboard.html' => '/dashboard.php',
    '/saved.html' => '/saved.php',
    '/student/profile.html' => '/student/profile.php',
    '/student/register.html' => '/student/register.php',
    '/employer/applicants.html' => '/employer/applicants.php',
];
if (preg_match('~(?:^|/)\.~', rawurldecode((string)$path))) { http_response_code(404); echo 'Not found'; return true; }
if ($path === '/job-detail.html' && !isset($_GET['job_id'])) return false;
if(isset($routes[$path])) { $query=$_SERVER['QUERY_STRING']??''; header('Location: '.$routes[$path].($query!==''?'?'.$query:''),true,302); return true; }
if($path==='/') { header('Location: /jobs.php'); return true; }
return false;
