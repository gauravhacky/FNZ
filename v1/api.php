<?php

require_once '/var/www/html/billing/common_function/update_userbase.php';
require_once '/var/www/html/billing/common_function/common_functions.php';
require_once '/var/www/html/fnz/billing/Fnz_Indo_Billing.php';
require_once 'productConfig.php';
$phpInput   = file_get_contents('php://input');
$headers = apache_request_headers();
$auth = $headers['Authorization'];
$decodedAuth = base64_decode($auth);
//for testing
$decodedAuth = "test:test123";
$username = "test";
$password = "test123";
// print_r($phpInput); die;
$sLog = __FILE__."|".__LINE__."|Start|".json_encode($_REQUEST)."|".$phpInput."|".json_encode($headers);
commonLogging($sLog, $logPath);
$getData = json_decode($phpInput ,true);
// print_r($getData ); die;

$msisdn     = $getData['msisdn'];
$cpid       = $getData['cpid'];
$operatotId = $getData['operatorid'];
$action     = $getData['action'];
$productId  = $getData['productid'];
$planId     = $getData['planid'];
$cp_transaction_id  = $getData['cp_transaction_id'];
$json = isJson($phpInput);

if($_SERVER['REQUEST_METHOD'] != 'POST'){
    http_response_code(405);
    $responseData = array('msisdn' =>$getData['msisdn'],'result' => array('status' => 'failure', 'code' => 'C405', 'message' => 'Method not supported'), 'operator' => array('name' => 'fnz_indo'));
  
}
else if($json != 1){
    http_response_code(400);
    $responseData = array('msisdn' =>$getData['msisdn'],'result' => array('status' => 'failure', 'code' => 'C400', 'message' => 'Bad Request'), 'operator' => array('name' => 'fnz_indo'));  
}
else if($decodedAuth != $username.":".$password){
    http_response_code(401);
    $responseData = array('msisdn' => $msisdn, 'cp_transaction_id' => $cp_transaction_id, 'trans_id' => $transactionId, 'result' => array('status' => 'FAIL', 'code' => 'C401', 'message' => 'Authorization failure'), 'operator' => array('name' => 'fnz_indo'));
    //echo json_encode($aData);exit;
}
else if(empty($msisdn)|| empty($cpid) || empty($operatotId) || empty($action) || empty($productId) || empty($planId) ){
    http_response_code(400);
    $responseData = array('msisdn' => $msisdn, 'cp_transaction_id' => $cp_transaction_id, 'trans_id' => $transactionId, 'result' => array('status' => 'FAIL', 'code' => 'C202', 'message' => 'Missing Mandatory Parameters'), 'operator' => array('name' => 'fnz_indo'));

  //echo json_encode($aData);
} else{
    $data = array('msisdn' => $msisdn, 'product_id' => $productId, 'plan_id' => $planId, 'action' =>$action, 'cp_transaction_id' => $cp_transaction_id );
    $billingObj = new Fnz_Indo_Billing();
    $billingRes 	= $billingObj->charge($data);
    $responseData   = json_decode($billingRes);
    $sLog = __FILE__."|".__LINE__."|BILLING_RESPONSE|".$billingRes;
    commonLogging($sLog, $logPath);
    //echo  $billingRes;
}

$sLog = __FILE__."|".__LINE__."|End|".json_encode($_REQUEST)."|".$phpInput."|".json_encode($headers);
commonLogging($sLog, $logPath);
echo json_encode($responseData);
exit();

function isJson($string) {
  json_decode($string);
  return (json_last_error() == JSON_ERROR_NONE);
 }
?>