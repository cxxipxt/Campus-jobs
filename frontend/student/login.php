<?php
require_once dirname(__DIR__,2).'/app/bootstrap.php';
if (isset($_GET['job_id'])) {
    try { $_SESSION['return_job']=positive_id(input('job_id',$_GET)); }
    catch (InvalidArgumentException $ex) { unset($_SESSION['return_job']); }
}
$loginPanel = 'student';
require dirname(__DIR__) . '/employer/login.php';
