<?php

require_once '/var/www/html/billing/common_function/update_userbase.php';
require_once '/var/www/html/billing/common_function/common_functions.php';
require_once '/var/www/html/fnz/billing/Fnz_Indo_Billing.php';
require_once 'productConfig.php';

$phpInput   = file_get_contents('php://input');
$sLog = __FILE__."|".__LINE__."Start|".json_encode($_REQUEST)."|".$phpInput;;
commonLogging($sLog, $logPath);
$getData = json_decode($phpInput ,true);
// http_response_code(503);
// $aData = array('msisdn' =>$getData['msisdn'],'result' => array('status' => 'failure', 'code' => 'C503', 'message' => 'Service Unavailable'), 'operator' => array('name' => 'fnz_indo'));


if($_SERVER['REQUEST_METHOD'] != 'POST'){
  http_response_code(400);
  $aData = array('msisdn' =>$getData['msisdn'],'result' => array('status' => 'failure', 'code' => 'C400', 'message' => 'Missing Mandatory Parameters'), 'operator' => array('name' => 'fnz_indo'));  
}

$json = isJson($phpInput);
if($json !=1){ 
  http_response_code(405);
  $aData = array('msisdn' =>$getData['msisdn'],'result' => array('status' => 'failure', 'code' => 'C405', 'message' => 'Method not supported'), 'operator' => array('name' => 'fnz_indo'));
}
 
$headers = apache_request_headers();
$auth = $headers['Authorization'];
$decodedAuth = base64_decode($auth);
$username = "rahul";
$password = 12345;

if($decodedAuth == $username.":".$password){
 
  // print_r($getData ); die;
  $billingObj = new Fnz_Indo_Billing();
  $msisdn     = $getData['msisdn'];
  $cpid       = $getData['cpid'];
  $operatotId = $getData['operatorid'];
  $action     = $getData['action'];
  $productId  = $getData['productid'];
  $planId     = $getData['planid'];
  if(empty($msisdn)|| empty($cpid) || empty($operatotId) || empty($action) || empty($productId) || empty($planId) ){
    http_response_code(400);
    $aData = array('msisdn' =>$getData['msisdn'],'result' => array('status' => 'failure', 'code' => 'C400', 'message' => 'Missing Mandatory Parameters'), 'operator' => array('name' => 'fnz_indo'));
  }else{
      $data = array('msisdn' => $msisdn, 'product_id' => $productId, 'plan_id' => $planId, 'action' =>$action );
      $billingRes 	= $billingObj->charge($data);

      $sLog = __FILE__."|".__LINE__."|BILLING_RESPONSE|".$billingRes;
      commonLogging($sLog, $logPath);
      http_response_code(200);
      echo  $billingRes;
      exit();
  } 
}else{
  http_response_code(401);
  $aData = array('msisdn' =>$getData['msisdn'],'result' => array('status' => 'failure', 'code' => 'C401', 'message' => 'Authorization failure'), 'operator' => array('name' => 'fnz_indo'));
 
}

echo json_encode($aData);
$sLog = __FILE__."|".__LINE__."METHOD_RESPONSE|".json_encode($aData);
commonLogging($sLog, $logPath);

exit();


function isJson($string) {
  json_decode($string);
  return (json_last_error() == JSON_ERROR_NONE);
 }


?>
Test
