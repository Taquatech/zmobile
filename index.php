<?php

session_start();
//exit('aaaa');
//$_SESSION['UID'] = 0;
//session_destroy();
//$AimDir = "Aim/";

require_once("../Aim/aim.php");

$pg = isset($_SESSION['MID']) && (int)$_SESSION['MID'] > 0?"root://ui/main/start.aim":"root://ui/login/login.aim";
// $_->Start("ui://main.aim");
//  $pg = "root://ui/main/start.aim";
//$pg = "root://ui/login/login.aim";
$_->Start($pg);

?>