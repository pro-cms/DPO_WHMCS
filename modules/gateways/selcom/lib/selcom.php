<?php
/*
 * Copyright (c) 2021 selcom Group
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the MIT License
 */

namespace SelcomPay;

class Selcom
{
   
    const BASE_URL = 'https://apigw.selcommobile.com/v1';

    private $selcomUrl;
    private $selcomGateway;
    private $testMode = false;
    private $apiKey;
    private $apiSecret;
    private $vendor;

    public function __construct( $apiKey,$apiSecret,$vendor )
    {
           $this->apiKey = $apiKey;
           $this->apiSecret = $apiSecret;
           $this->vendor = $vendor;
           $this->selcomUrl = self::BASE_URL;
        
        $this->selcomGateway = $this->selcomUrl . '/checkout/create-order-minimal';
    }

    

    /**
     * Create a selcom token for payment processing
     * @param $data
     * @return array
     */
    public function createPaymentLink( $data )
    {
 
        date_default_timezone_set('Africa/Dar_es_Salaam');
        $timestamp = date('c');  
        $signed_fields  = implode(',', array_keys($data));
        $authorization = base64_encode($this->apiKey);
        $digest = $this->computeSignature($data, $signed_fields, $timestamp, $this->apiSecret);
        $response = $this->sendJSONPost($this->selcomGateway, 1, json_encode($data), $authorization, $digest, $signed_fields, $timestamp);
        if($response['result'] == 'SUCCESS'){
        // try {
        $url = base64_decode($response['data'][0]['payment_gateway_url']);
          
            // } catch (\Throwable $th) {
            // return null;
            // }
    
        return $url;

        } else {
            return [
                'success'           => false,
                'result'            => !empty( $error ) ? $error : 'Unknown error occurred in token creation',
                'resultExplanation' => !empty( $error ) ? $error : 'Unknown error occurred in token creation',
            ];
        }
    }

    /**
     * Verify the selcom token created in first step of transaction
     * @param $data
     * @return bool|string
     */
    public function verifyToken( $data )
    {
        $companyToken = $data['companyToken'];
        $transToken   = $data['transToken'];

        try {
            $curl = curl_init();
            curl_setopt_array( $curl, array(
                CURLOPT_URL            => $this->selcomUrl . "/API/v6/",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => "",
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => "POST",
                CURLOPT_POSTFIELDS     => "<?xml version=\"1.0\" encoding=\"utf-8\"?>\r\n<API3G>\r\n  <CompanyToken>" . $companyToken . "</CompanyToken>\r\n  <Request>verifyToken</Request>\r\n  <TransactionToken>" . $transToken . "</TransactionToken>\r\n</API3G>",
                CURLOPT_HTTPHEADER     => array(
                    "cache-control: no-cache",
                ),
            ) );

            $response = curl_exec( $curl );
            $err      = curl_error( $curl );

            curl_close( $curl );

            if ( strlen( $err ) > 0 ) {
                echo "cURL Error #:" . $err;
            } else {
                return $response;
            }
        } catch ( Exception $e ) {
            throw $e;
        }
    }

    
    public function computeSignature($parameters, $signed_fields, $request_timestamp, $api_secret){
        $fields_order = explode(',', $signed_fields);
        $sign_data = "timestamp=$request_timestamp";
        if($signed_fields!=null){
        foreach ($fields_order as $key) {
          $sign_data .= "&$key=".$parameters[$key];
        }
    }
        //HS256 Signature Method
        return base64_encode(hash_hmac('sha256', $sign_data, $api_secret, true));
}

    public function sendJSONPost($url, $isPost, $json, $authorization, $digest, $signed_fields, $timestamp) {
    $headers = array(
      "Content-type: application/json;charset=\"utf-8\"", "Accept: application/json", "Cache-Control: no-cache",
      "Authorization: SELCOM $authorization",
      "Digest-Method: HS256",
      "Digest: $digest",
      "Timestamp: $timestamp",
      "Signed-Fields: $signed_fields",
    );
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    if($isPost){
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch,CURLOPT_TIMEOUT,90);
    $result = curl_exec($ch);
    curl_close($ch);
    $resp = json_decode($result, true);
    return $resp;
    }

    
  
    public function getStatus($url, $isPost, $authorization, $digest, $signed_fields,$timestamp){
        // /v1/checkout/order-status?order_id={order_id}
        $url = $url;
        $isPost = false;
        //get request
        $headers = array(
            "Content-type: application/json;charset=\"utf-8\"", "Accept: application/json", "Cache-Control: no-cache",
            "Authorization: SELCOM $authorization",
            "Digest-Method: HS256",
            "Digest: $digest",
            "Timestamp: $timestamp",
            // "Signed-Fields: $signed_fields",
          );
          $ch = curl_init();
          curl_setopt($ch, CURLOPT_URL, $url);
          if($isPost){
            curl_setopt($ch, CURLOPT_POST, 1);
          }
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
          curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
          curl_setopt($ch,CURLOPT_TIMEOUT,90);
          $result = curl_exec($ch);
          curl_close($ch);
          $resp = json_decode($result, true);
          return $resp;
    
      }

}
