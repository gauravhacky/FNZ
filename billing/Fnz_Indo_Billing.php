<?php
require_once '/var/www/html/billing/common_function/update_userbase.php';
require_once '/var/www/html/billing/common_function/common_functions.php';
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Kolkata');
class Fnz_Indo_Billing {

    function __construct() {
        //Subscription Table
        $this->subTableName     = 'typhoon.fnz_indo_subscription';
        $this->transTableName   = 'typhoon.fnz_indo_transaction';
        $this->operatorId       = '15';
        $this->operatorName     = 'fnz_indo';
        $this->countryCode      = '62';
        $this->currency         = 'IDR';

        $this->aProductDetails['vod']['daily']    = array('productId'=>'1614', 'planId'=>'', 'validity' => '+1 day', 'rate' => '325');
        $this->aProductDetails['vod']['weekly']    = array('productId'=>'1595', 'planId'=>'', 'validity' => '+7 day', 'rate' => '1100');
  
        $this->merchantId       = "274";
        $this->merchantName     = "FNZ";

        $this->publisherSecretKey   = "195207502f27dccd8819fbf41f79a8e107fd5455";
        $this->clientKey            = "5fc4c3e01441fc4a3da6dc94";

        $this->billingUrl       = 'https://payment.upoint.co.id/common/v1/transaction';
        
        $this->successUrl       = "http://49.50.107.98/fnz/success.php";
        $this->cancelUrl          = "http://49.50.107.98/fnz/cancel.php";
        $this->sdpCallbackUrl   = "http://49.50.107.98/fnz/billing/sdp_callback.php";

        $this->billingLogFilePath = '/var/log/billing/' . date('Y') . '/' . date('m') . '/fnz_indo/fnz_indo_billing_' . date('Ymd') . '.log';

        checkLogPath($this->billingLogFilePath);
    }

    public function charge($paramArray) {
        $this->sStartTime       = microtime(true);
        $this->msisdn           = ($paramArray['msisdn']) ? $this->countryCode.substr((int)$paramArray['msisdn'],-9) : "";
        $this->errorCode        = 'C999';
        $this->errorMessage     = 'Unknown Error';
        $this->errorDescription = '';
        $this->operatorErrorCode= '';
        $this->productId        = trim($paramArray['product_id']);
        $this->planId           = trim($paramArray['plan_id']);
        
        $this->transactionId    = date("ymdHis") . $this->msisdn . rand(1000, 9999);
        $this->aProductDetail   = $this->aProductDetails[$this->productId][$this->planId];

        $this->sAction          = strtoupper(trim($paramArray['action']));
        $this->other1           = strtolower(trim($paramArray['other1']));
        $this->other2           = strtolower(trim($paramArray['other2']));
        $this->affId            = isset($paramArray['aff_id']) ? $paramArray['aff_id'] : '';
        $this->promoId          = isset($paramArray['promo_id']) ? $paramArray['promo_id'] : '';
        $this->cp_transaction_id= isset($paramArray['cp_transaction_id']) ? $paramArray['cp_transaction_id'] : '';

        $this->rate             = $this->aProductDetail['rate'];
        $this->validity         = $this->aProductDetail['validity'];
        $this->sOpStatus        = "";
        $this->bUpadateBase     = FALSE;

        $sLog = __FUNCTION__."|".__LINE__."|{$this->msisdn}|{$this->productId}|{$this->planId}|{$this->affId}|{$this->promoId}|Input Parameter : " . json_encode($paramArray);
        commonLogging($sLog,$this->billingLogFilePath);

        if ($this->msisdn == '' || !$this->aProductDetail) {
            $this->errorCode        = 'C400';
            $this->errorMessage    = 'Bad Request';
        } else {

            switch ($this->sAction) {
                case 'SUB' :
                    if($this->isAlreadySubscribed()){
                        $this->sStatus      = "OK";
                        $this->errorCode    = "C101";
                        $this->errorMessage = "Already Subscribed";
                    }
                    else {
                        $this->subscription();
                    }
                    break;
                case 'UNSUB' :
                    $this->unSubscription();
                    break;
                case 'STATUS':
                    if($this->isAlreadySubscribed()){
                        $this->sStatus      = "OK";
                        $this->errorCode    = "C101";
                        $this->errorMessage = "Already Subscribed";
                    }
                    else{
                        $this->sStatus      = "FAIL";
                        $this->errorCode    = "C999";
                        $this->errorMessage = "InActive";
                    }
                    break;
            }
        }
        /*
        if ($this->bUpadateBase) {
            $this->callDBEntry();
        }
        */
        $this->sStatus = in_array($this->errorCode, array('C000','C001','C101','C002')) ? 'OK' : 'FAIL';

        //$aData = array('msisdn' => $this->msisdn, 'amount' => $this->rate, 'trans_id' => $this->transactionId, 'result' => array('status' => $this->sStatus, 'code' => $this->errorCode, 'message' => $this->errorMessage), 'operator' => array('name' => 'fnz_indo'));

        $aData = array('msisdn' => $this->msisdn, 'cp_transaction_id' => $this->cp_transaction_id, 'trans_id' => $this->transactionId, 'result' => array('status' => $this->sStatus, 'code' => $this->errorCode, 'message' => $this->errorMessage), 'operator' => array('name' => 'fnz_indo'));

        $json_data = json_encode($aData);
        $sLog = __FUNCTION__."|".__LINE__."|{$this->msisdn}|{$this->productId}|{$this->planId}|{$this->affId}|{$this->promoId}|Json Data = " . $json_data;
        commonLogging($sLog,$this->billingLogFilePath);

        return $json_data;
    }

    private function subscription() {
        $this->generateToken();
        if(!isset($this->token)){
            return false;
        }
        $data = array (
                    'payment_type' => 'telkomsel',
                    'telkomsel' => 
                        array (
                            'phone_number' => $this->msisdn,
                        ),
                );
        
        $jsonData       = json_encode($data);
        $header = array(                   
                    "Content-Type: application/json",
                    "X-Client-Key: {$this->clientKey}"
                    );
        $url = "{$this->billingUrl}/{$this->token}/pay";
        $sApiResponse   = curlSend($url, 10, $jsonData, 'POST',$header);

        $aApiResponse   = json_decode($sApiResponse['result'], true);

        
        $sLog = __FUNCTION__."|".__LINE__."|{$this->msisdn}|{$this->productId}|{$this->planId}|{$this->affId}|{$this->promoId}|{$url}|{$jsonData}|" . json_encode($sApiResponse);
        commonLogging($sLog, $this->billingLogFilePath);

        if ($aApiResponse['response_code']) {
            
            $this->errorCode = $this->status = 'C001';
            $this->errorMessage = $this->response = $this->sDescription = $aApiResponse['transaction_status'];
            $this->bUpadateBase = false;
        } else {
            $this->errorCode = $this->status = 'C199';
            $this->errorMessage = $this->response = $this->sDescription = "Subscription Fail";
        }
    }

    private function transactionStatus() {
        /*
        if ($this->isAlreadySubscribed()) {
            $this->errorCode = "C101";
            $this->errorMessage = "Already Subscribed.";
            return false;
        }
        */
        $aSelect = selectDetails($this->transTableName, 'unique_token', array('msisdn' => " = '" . $this->msisdn . "'",'product_id' => " = '" . $this->productId . "'"), 'order by id desc');

        $sLog = __FUNCTION__."|".__LINE__."|{$this->msisdn}|{$this->productId}|{$this->planId}|{$this->affId}|{$this->promoId}|Select Token => " . json_encode($aSelect);
        commonLogging($sLog, $this->billingLogFilePath);

        $token = $aSelect['data']['unique_token'];

        $url = "{$this->billingUrl}/{$token}/status";

        $header = array(                   
                    "Content-Type: application/json",
                    "X-Server-Key: {$this->publisherSecretKey}"
                    );
        $sApiResponse   = curlSend($url, 10, '', 'GET',$header);
        $aApiResponse   = json_decode($sApiResponse['result'], true);

        if ($aApiResponse['response_code']) {
            $this->errorCode = $this->status = 'C001';
            $this->errorMessage = $this->response = $this->sDescription = $aApiResponse['transaction_status'];
            
        } else {
            $this->errorCode = $this->status = 'C199';
            $this->errorMessage = $this->response = $this->sDescription = $aApiResponse['transaction_status'];
        }

        $sLog = __FUNCTION__."|".__LINE__."|{$this->msisdn}|{$this->productId}|{$this->planId}|{$this->affId}|{$this->promoId}|{$url}|" . json_encode($sApiResponse);
        commonLogging($sLog, $this->billingLogFilePath);
    }

    private function transactionDetail() {
        /*
        if ($this->isAlreadySubscribed()) {
            $this->errorCode = "C101";
            $this->errorMessage = "Already Subscribed.";
            return false;
        }
        */
        $aSelect = selectDetails($this->transTableName, 'unique_token', array('msisdn' => " = '" . $this->msisdn . "'",'product_id' => " = '" . $this->productId . "'"), 'order by id desc');

        $sLog = __FUNCTION__."|".__LINE__."|{$this->msisdn}|{$this->productId}|{$this->planId}|{$this->affId}|{$this->promoId}|Select Token => " . json_encode($aSelect);
        commonLogging($sLog, $this->billingLogFilePath);

        $token = $aSelect['data']['unique_token'];

        $url = "{$this->billingUrl}/{$token}";

        $header = array(                   
                    "Content-Type: application/json",
                    "X-Server-Key: {$this->publisherSecretKey}"
                    );
        $sApiResponse   = curlSend($url, 10, '', 'GET',$header);
        $aApiResponse   = json_decode($sApiResponse['result'], true);

        if ($aApiResponse['response_code']) {
            $this->errorCode = $this->status = 'C001';
            $this->errorMessage = $this->response = $this->sDescription = $aApiResponse['transaction_status'];
            
        } else {
            $this->errorCode = $this->status = 'C199';
            $this->errorMessage = $this->response = $this->sDescription = $aApiResponse['transaction_status'];
        }

        $sLog = __FUNCTION__."|".__LINE__."|{$this->msisdn}|{$this->productId}|{$this->planId}|{$this->affId}|{$this->promoId}|{$url}|" . json_encode($sApiResponse);
        commonLogging($sLog, $this->billingLogFilePath);
    }

    private function unSubscription() {
        if ($this->isNumExist()) {
            if ($this->isAlreadyUnsubscribed()) {
                $this->errorCode = "C102";
                $this->errorMessage = "Already Unsubscribed";
                return false;
            }
        } else {
            $this->errorCode = "C201";
            $this->errorMessage = "Forbidden";
            return false;
        }

        $sUnsubUrl = $this->unSubscribeUrl . "user=" . urlencode($this->aes128Encrypt($this->spId)) . "&password=" . urlencode($this->aes128Encrypt($this->spPassword)) . "&msisdn=" . urlencode($this->aes128Encrypt($this->msisdn)) . "&packageid=" . urlencode($this->aes128Encrypt($this->packageId));

        $sApiResponse = curlSend($sUnsubUrl, 10);

        if (trim($sApiResponse) == "Deactivation_Success") {
            $this->errorCode = $this->status = 'C001';
            $this->errorMessage = $this->response = $this->sDescription = $sApiResponse;
        } else {
            $this->errorCode = $this->status = 'C199';
            $this->errorMessage = $this->response = $this->sDescription = $sApiResponse;
        }

        $sLog = __FUNCTION__."|".__LINE__."|{$this->msisdn}|{$this->productId}|{$this->planId}|{$this->affId}|{$this->promoId}|URL => " . $sUnsubUrl . '| Response =>' . $sApiResponse;
        commonLogging($sLog, $this->billingLogFilePath);
    }
    
    private function generateToken() {

        $data = array (
                        'order_id' => $this->transactionId,
                        'item' => 'dummy',
                        'merchant' => 
                            array (
                                'merchant_id' => $this->merchantId,
                                'name' => $this->merchantName,
                                'callback_url' => $this->sdpCallbackUrl,
                                'success_url' => $this->successUrl,
                                'cancel_url' => $this->cancelUrl,
                            ),
                    );

        $jsonData       = json_encode($data);
        $header = array(                   
                    "Content-Type: application/json",
                    "X-Server-Key: {$this->publisherSecretKey}"
                    );
        $url = $this->billingUrl;
        $sApiResponse   = curlSend($url, 10, $jsonData, 'POST', $header);

        $aApiResponse   = json_decode($sApiResponse['result'], true);

        
        $sLog = __FUNCTION__."|".__LINE__."|{$this->msisdn}|{$this->productId}|{$this->planId}|{$this->affId}|{$this->promoId}|{$url}|{$jsonData}|" . json_encode($sApiResponse);
        commonLogging($sLog, $this->billingLogFilePath);

        if ($aApiResponse['token']) {
            $this->token = $aApiResponse['token'];
            /*
            $this->errorCode = $this->status = 'C001';
            $this->errorMessage = $this->response = $this->sDescription = $aApiResponse[0];
            $this->bUpadateBase = false;
            */
            $aInputValue['msisdn']      = "'".$this->msisdn."'";
            $aInputValue['product_id']  = "'".$this->productId."'";
            $aInputValue['token']       = "'".$this->token."'";
            $aDBInsertReturn = insertTable($this->transTableName, $aInputValue);

            $sInsertLog = __FUNCTION__."|".__LINE__."|{$this->msisdn}|{$this->productId}|{$this->planId}|{$this->affId}|{$this->promoId}|Insert_Transaction_Token|" . json_encode($aInputValue). "|". json_encode($aDBInsertReturn);
            commonLogging($sInsertLog,$this->billingLogFilePath);
            
        } else {
            $this->errorCode = $this->status = 'C199';
            $this->errorMessage = $this->response = $this->sDescription = "Token Generation Error";
        }
    }

    private function isAlreadySubscribed() {
        return $isSubscribed = false;
        //$aSelect = selectDetails($this->subTableName, 'id', array('msisdn' => " = '" . $this->msisdn . "'", 'event_id' => " = '" . $this->productId . "'", 'status' => " = 2", 'expiry_date' => "> now()"));
        $aSelect = selectDetails($this->subTableName, 'id', array('msisdn' => " = '" . $this->msisdn . "'", 'event_id' => " = '" . $this->productId . "'", 'status' => " = 2", 'expiry_date' => "> now()"));

        if ($aSelect['status'] == 'success' && $aSelect['data'] && !empty($aSelect['data'])) {
            $isSubscribed = true;
        }

        $sLog = __FUNCTION__."|".__LINE__."|{$this->msisdn}|{$this->productId}|{$this->planId}|{$this->affId}|{$this->promoId}|Select  = " . json_encode($aSelect)."|".$isSubscribed;
        commonLogging($sLog, $this->billingLogFilePath);

        return $isSubscribed;
    }

    private function isNumExist() {
        $isNumExist = false;
        $sStartTime = microtime(true);
        $aSelect = selectDetails($this->subTableName, 'id', array('msisdn' => " = '" . $this->msisdn . "'", 'event_id' => " = '" . $this->productId . "'"));

        if ($aSelect['status'] == 'success' && $aSelect['data'] && !empty($aSelect['data'])) {
            $isNumExist = true;
        }
        $sLog = __FUNCTION__."|".__LINE__."|{$this->msisdn}|{$this->productId}|{$this->planId}|{$this->affId}|{$this->promoId}|Select  = " . json_encode($aSelect)."|".$isNumExist;
        commonLogging($sLog, $this->billingLogFilePath);
        return $isNumExist;
    }

    private function isAlreadyUnsubscribed() {
        $isAlreadyUnsubscribed = false;
        $sStartTime = microtime(true);

        $aSelect = selectDetails($this->subTableName, 'id', array('msisdn' => " = '" . $this->msisdn . "'", 'event_id' => " = '" . $this->productId . "'", 'status' => " = 6"));

        if ($aSelect['status'] == 'success' && $aSelect['data'] && !empty($aSelect['data'])) {
            $isAlreadyUnsubscribed = true;
        }

        $sLog = __FUNCTION__."|".__LINE__."|{$this->msisdn}|{$this->productId}|{$this->planId}|{$this->affId}|{$this->promoId}|Select  = " . json_encode($aSelect)."|".$isAlreadyUnsubscribed;
        commonLogging($sLog, $this->billingLogFilePath);
        return $isAlreadyUnsubscribed;
    }

    private function aes128Encrypt($data) {

        $key = $this->secreteKey;
        if (16 !== strlen($key))
            $key = hash('MD5', $key, true);
        $padding = 16 - (strlen($data) % 16);
        $data .= str_repeat(chr($padding), $padding);
        return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $data, MCRYPT_MODE_CBC, str_repeat("\0", 16)));
    }

    private function aes128Decrypt($data) {
        $key = $this->secreteKey;
        $data = base64_decode($data);
        if (16 !== strlen($key))
            $key = hash('MD5', $key, true);
        $data = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $data, MCRYPT_MODE_CBC, str_repeat("\0", 16));
        $padding = ord($data[strlen($data) - 1]);
        return substr($data, 0, -$padding);
    }

}

?>