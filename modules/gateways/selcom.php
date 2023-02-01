<?php
 /*
 * Copyright (c) 2021 DPO Group
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 *
 * This module facilitates DPO Group payments for WHMCS clients
 *
 */

// Require libraries needed for gateway module functions
require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../includes/invoicefunctions.php';

 
 
if ( !defined( "WHMCS" ) ) {
    die( "This file cannot be accessed directly" );
}

 

 

/**
 * Check for existence of SelcomPayselcom table and create if not
 * In earlier versions this table was named paygateselcom -> rename if necessary
 */
 

 

if ( isset( $_POST['INITIATE'] ) && $_POST['INITIATE'] == 'initiate' ) {
    
    print_r(selcom_initiate( $params ) );
	// header( 'Location: ' . selcom_initiate( $params ) );
  
}

/**
 * Define module related meta data
 *
 * Values returned here are used to determine module related capabilities and
 * settings
 *
 * @return array
 */
function selcom_MetaData()
{
    return array(
        'DisplayName'                 => 'Direct Pay Online (selcom)',
        'APIVersion'                  => '1.1', // Use API Version 1.1
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage'            => true,
    );
}

/**
 * Define gateway configuration options
 *
 *
 * @return array
 */
function selcom_config()
{
    return array(
        // defined here for backwards compatibility
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => ' selcom Payment Gateway',
        ),
        // a text field type allows for single line text input
        'vendorID' => array(
            'FriendlyName' => 'Vendor ID',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter your app Name here',
        ),
        // a text field type allows for single line text input
        'apiKey' => array(
            'FriendlyName' => 'API key',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter your Client ID here',
        ),
         // a text field type allows for single line text input
         'apiSecret' => array(
            'FriendlyName' => 'API secret',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter your Client ID here',
        ),
        // the yesno field type displays a single checkbox option
        'testMode' => array(
            'FriendlyName' => 'Test Mode',
            'Type' => 'yesno',
            'Description' => 'Tick to enable test mode',
        ),

         // a text field type allows for single line text input
         'redirect_url' => array(
            'FriendlyName' => 'Redirect URL',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter your Client ID here',
        ),
         // a text field type allows for single line text input
         'cancel_url' => array(
            'FriendlyName' => 'Cancel URL',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter your Client ID here',
        ),
        // the yesno field type displays a single checkbox option
        'webhook' => array(
            'FriendlyName' => 'Cancel URL',
            'Type' => 'yesno',
            'Description' => 'Tick to enable test mode',
        ),
    );
}

function selcom_link( $params )
{
    $jparams   = base64_encode( json_encode( $params ) );
    $systemurl = "https://zepsonhost.com/modules/gateways/selcom.php"; 
    $html      = <<<HTML
    <form method="post" action="{$systemurl}" >
    <input type="hidden" name="INITIATE" value="initiate" />
    <!-- <input type="hidden" name="jparams" value="$jparams" /> -->
    <input type="submit" value="Pay Using Selcom" />
    </form>
HTML;

    return $html;
}

function selcom_initiate( $params )
{
    $testMode = false;
    // Callback urls
    $systemUrl = $params['systemurl'];
    $notifyUrl = $systemUrl . 'modules/gateways/callback/selcom.php';
    $returnUrl = $systemUrl . 'modules/gateways/callback/selcom.php';

    $vendor = $params['vendorID'];
    $apiKey = $params['apiKey'];
    $apiSecret = $params['apiSecret'];
    $redirect_url = base64_encode($params['redirect_url']);
    $cancel_url = base64_encode($params['cancel_url']);
    $webhook = base64_encode($params['webhook']);
    $invoiceId = $params['invoiceid'];
    $amount = $params['amount'];
    $phone =  $params['clientdetails']['phonenumber'];
    $email = $params['clientdetails']['email'];
    $fullName = $params['clientdetails']['firstname']. " ".$params['clientdetails']['lastname'];

    $data =[
        "vendor" =>  "TILL60896081",
        "order_id" =>time(),
        "buyer_email" => $email,
        "buyer_name" => $fullName,
        "buyer_phone" => "255777777777",
        "amount" => "5000",
        "currency" =>  "TZS",
        // "payment_methods" => "ALL",
        "redirect_url" => $redirect_url,
        "cancel_url" =>  $cancel_url,
        "webhook" => $webhook,
        // "billing" => [
        //   "firstname" =>  $params['clientdetails']['firstname'],
        //   "lastname" =>$params['clientdetails']['lastname'],
        //   "address_1" => $params['clientdetails']['address1'],
        //   "address_2" => $params['clientdetails']['address2'],
        //   "city" => $params['clientdetails']['city'],
        //   "state_or_region" => $params['clientdetails']['region']??"Dar Es Salaam",
        //   "postcode_or_pobox" => $params['clientdetails']['zipcode'],
        //   "country" => "TZ",
        //   "phone" =>   "255777777777"
        // ],
        "no_of_items" => 1
    ];

    // Create token
    $selcom    = new Selcom($apiKey,$apiSecret,$vendor);
    return  $payment_link = $selcom->createPaymentLink($data);
    

    if ( $payment_link!=null) {
        return $payment_link;


    } else {
        echo 'Something went wrong: ' ;
        $url = $systemUrl . 'viewinvoice.php?id=' . $data['companyRef'];
        echo <<<HTML
<br><br><a href="$url">Click here to return</a>
HTML;

    }
}



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
           $this->apiKey = "ZEPSON-WsGHweDFyW5OOiAs";
           $this->apiSecret = "987LLk3-khfd-54fa-Pj63-8dh7y9eb69b2";
           $this->vendor = "TILL60896081";
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
        $authorization = base64_encode("ZEPSON-WsGHweDFyW5OOiAs");
        $digest = $this->computeSignature($data, $signed_fields, $timestamp, $this->apiSecret);
        $response = $this->sendJSONPost($this->selcomGateway, 1, json_encode($data), $authorization, $digest, $signed_fields, $timestamp);
        // return $response;
        // if($response['result'] == 'SUCCESS'){
        // try {
        $url = base64_decode($response['data'][0]['payment_gateway_url']);
          
            // } catch (\Throwable $th) {
            // return null;
            // }
    
        return $url;

        // } else {
        //     return [
        //         'success'           => false,
        //         'result'            => !empty( $error ) ? $error : 'Unknown error occurred in token creation',
        //         'resultExplanation' => !empty( $error ) ? $error : 'Unknown error occurred in token creation',
        //     ];
        // }
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
