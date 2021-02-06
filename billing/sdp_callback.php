<?php

require_once '/var/www/html/billing/common_function/update_userbase.php';
require_once '/var/www/html/billing/common_function/common_functions.php';
//require_once 'sdp_callback_process.php';

$logPath = '/var/log/billing/'.date('Y')."/".date('m')."/fnz/sdp_callback_".date('Ymd').".log";

$phpInput   = file_get_contents('php://input');
$sLog = "Start|".json_encode($_REQUEST)."|".$phpInput;
commonLogging($sLog, $logPath);
//print_r($_REQUEST); die;
$sQueueMsg = json_encode($_REQUEST);

//processCallback($sQueueMsg);
echo "ACCEPTED";
exit();
?>
