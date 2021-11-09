<?php

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}


class ShurjopayRequestModuleFrontController extends ModuleFrontController
{
	/**
	 * @see FrontController::postProcess()
	**/
	public function postProcess()
	{



		$cart = $this->context->cart;

		$cookie = $this->context->cookie;
		$customer = new Customer($cart->id_customer);
		$currency = $this->context->currency;
		$total = (float)$cart->getOrderTotal(true, Cart::BOTH);

        $toCurrency = new Currency(Currency::getIdByIsoCode('ZAR'));
        $fromCurrency = new Currency((int)$cart->id_currency);
        $address = new Address(intval($cart->id_address_invoice));
        $address_ship = new Address(intval($cart->id_address_delivery));
        $currency = new Currency(intval($cart->id_currency));
        $currency_iso_code = $currency->iso_code;
        $pfamount = Tools::convertPriceFull( $total, $fromCurrency, $toCurrency );
        // $orderState = new OrderState();
        $shurjopay = Module::getInstanceByName('SHURJOPAY');
        
        // $order = new Order(48);
        
        $data = array();

        // $currency = $currency->getCurrency((int)$cart->id_currency);
        if ($cart->id_currency != $currency->id)
        {
            // If sp currency differs from local currency
            $cart->id_currency = (int)$currency->id;
            $cookie->id_currency = (int)$cart->id_currency;
            $cart->update();
        }
        
        $data['store_id'] = Configuration::get('SHURJOPAY_STORE_ID');
        $data['store_passwd'] = Configuration::get('SHURJOPAY_STORE_PASSWORD');
		$data['store_prefix'] = Configuration::get('SHURJOPAY_PREFIX');
		$data['engine_url'] = Configuration::get('ENGINE_URL');

		$data['tran_id'] = $cart->id;
		$data['total_amount'] = number_format( sprintf( "%01.2f", $total ), 2, '.', '' );
		if(($customer->firstname) || ($customer->lastname))
		{
			$data['cus_name'] = $customer->firstname.' '.$customer->lastname;
		}
		else{
			echo "Customer Name not found !";
		}

		if(($address->address1))
		{
			$data['cus_add1'] = $address->address1;
			$data['cus_add2'] = $address->address2;
		}
		else{
			echo "Customer Address not found !";
		}
		if(($address->city))
		{
			$data['cus_city'] = $address->city;
		}
		else{
			echo "Customer City not found !";
		}
		if(($address->city))
		{
			$data['cus_state'] = $address->city;
		}
		else{
			echo "Customer State not found !";
		}
		if(($address->postcode))
		{
			$data['cus_postcode'] = $address->postcode;
		}
		else{
			echo "Customer Postcode not found !";
		}
		if(($address->country))
		{
			$data['cus_country'] = $address->country;
		}
		else{
			echo "Customer Country not found !";
		}
		if(($address->phone))
		{
			$data['cus_phone'] = $address->phone;
		}
		else{
			echo "Customer Phone not found !";
		}
		if(($customer->email))
		{
			$data['cus_email'] = $customer->email;
		}
		else{
			echo "Customer Email not found !";
		}

		if ($address_ship) {
			$data['ship_name'] = $address_ship->firstname.' '.$address_ship->lastname;
			$data['ship_add1'] = $address_ship->address1;   
			$data['ship_add2'] = $address_ship->address2; 
			$data['ship_city'] = $address_ship->city; 
			$data['ship_state'] = $customer->email; 
			$data['ship_postcode'] = $address_ship->postcode;  
			$data['ship_country'] = $address_ship->country; 
			$ship = "YES";
		} else {
			$data['ship_name'] = '';
			$data['ship_add1'] = '';
			$data['ship_add2'] = '';
			$data['ship_city'] = '';
			$data['ship_state'] = '';
			$data['ship_postcode'] = '';
			$data['ship_country'] = '';
			$ship = "NO";
		}
              
            //   validation
		$data['currency'] = $currency_iso_code;
		$data['success_url'] = $this->context->link->getModuleLink('SHURJOPAY', 'validation', array(), true);
		$data['fail_url'] = $this->context->link->getModuleLink('SHURJOPAY', 'validation', array(), true);
		$data['cancel_url'] = $this->context->link->getModuleLink('SHURJOPAY', 'validation', array(), true);
		$data['ipn_url'] = $this->context->link->getModuleLink('SHURJOPAY', 'ipn', array(), true);

		$data['shipping_method']   = $ship;
    	$data['num_of_item']       = "0";
    	$data['product_name']      = "cartproduct";
    	$data['product_category']  = 'Ecommerce';
    	$data['product_profile']   = 'general';
       
		////Hash Key Gernarate For SP
		$security_key = $this->shurjopay_hash_key(Configuration::get('SHURJOPAY_STORE_PASSWORD'), $data);

		$data['verify_sign'] = $security_key['verify_sign'];
        $data['verify_key'] = $security_key['verify_key'];
        
        $objOrder = new Order($cart->id);
        $history = new OrderHistory();
        $history->id_order = (int)$objOrder->id;
        $history->id_order;
        
        $sp_mode = Configuration::get('MODE');
        if( $sp_mode == 1 )
        {
            $redirect_url = 'https://shurjopay.com/sp-data.php';
            $api_type = "securepay";
            $uniq_transaction_key='NOK'.time();

        }
        else
        {
            $redirect_url = 'https://shurjotest.com/sp-data.php';
            $api_type = "sandbox";
            $uniq_transaction_key='NOK'.time();
        }
        $clientIP =  $_SERVER['REMOTE_ADDR'];
        $amount=$data['total_amount'];
        
      /*  $xml_data = 'spdata=<?xml version="1.0" encoding="utf-8"?>
				<shurjoPay><merchantName>'.$data['store_id'].'</merchantName>
				<merchantPass>'.$data['store_passwd'].'</merchantPass>
				<userIP>127.0.0.1</userIP>
				<uniqID>'.$uniq_transaction_key.'</uniqID>
				<totalAmount>'.$amount.'</totalAmount>				
				<paymentOption>shurjopay</paymentOption>
				<returnURL>'.$data['success_url'].'</returnURL></shurjoPay>';*/


        //////
		///
		$info = array(
			'prefix' => $data['store_prefix'],
			'currency' => "BDT",
			'return_url' => $data['success_url'],
			'cancel_url' =>$data['success_url'],
			'amount' => $amount,
			'order_id' => $uniq_transaction_key,
			'discsount_amount' => 0,
			'disc_percent' => 0,
			'client_ip' => $clientIP,
			'customer_name' => $data['cus_name'],
			'customer_phone' => $data['cus_phone'],
			'email' => $data['cus_email'],
			'customer_address' => $data['cus_add1']."".$data['cus_add2'],
			'customer_city' => $data['cus_city'],
			'customer_state' => $data['cus_state'],
			'customer_postcode' => $data['cus_postcode'],
			'customer_country' => $data['cus_country'],
		);

		$curl = curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_URL => $data['engine_url'].'/api/get_token',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS =>'{
                                            "username": "'.$data['store_id'].'",
                                            "password": "'.$data['store_passwd'].'"
                                        }',
			CURLOPT_HTTPHEADER => array(
				'Content-Type: application/json'
			),
		));

		$response = curl_exec($curl);

		curl_close($curl);
		$arr = json_decode($response);


		   if(!empty($arr->token))
        {
            $tok=($arr->token);
            $s_id=($arr->store_id);

            $info2=array(
                'token'=>$tok,
                'store_id'=>$s_id);
            $final_array=array_merge($info2, $info);
            $bodyJson=json_encode($final_array);
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL =>$data['engine_url'].'/api/secret-pay',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS =>$bodyJson,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json'
                ),
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
			$arr = json_decode($response);

			if(!empty($arr->checkout_url))
			{
				$url = ($arr->checkout_url);
				header("Location:".$url);
				print_r($url);
				exit();

				return ($url);
			}
			else{
				return $response;
			}
        }

		///////
        
   /*     $handle = curl_init();
		curl_setopt($handle, CURLOPT_URL, $redirect_url);
		curl_setopt($handle, CURLOPT_TIMEOUT, 10);
		curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($handle, CURLOPT_POST, 1 );
		curl_setopt($handle, CURLOPT_POSTFIELDS, $xml_data);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		$content = curl_exec($handle );
		$code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
		print_r($content) ;
		exit();*/
		//echo json_encode(['status' => 'SUCCESS', 'data' => $redirect_url, 'logo' => "http://ebssbd.com/frontend_assets/switcher/css/logos/dtcl-logo.jpg" ]);
            			exit;
		
/*		if($code == 200 && !( curl_errno($handle)))
		{
		  	echo $content;
	curl_close( $handle);exit;
		  	$shurjopayResponse = $content;
		  
			# PARSE THE JSON RESPONSE
		  	$spay = json_decode($shurjopayResponse, true );

		  	if($spay['status']=='SUCCESS')
		  	{
		  	   // echo Configuration::get('PS_OS_PAYMENT')."------".$shurjopay->displayName."----".$cart->id."----".$customer->secure_key;exit;
			
	           // $this->module->validateOrder($cart->id, Configuration::get('PS_OS_PAYMENT'), $total, $shurjopay->displayName, NULL, array(), (int)$currency->id, false, $customer->secure_key);
              // $history->changeIdOrderState(2, (int)($objOrder->id)); //order status= Payment accepted
             
                if(isset($spay['GatewayPageURL']) && $spay['GatewayPageURL'] != '') 
                {
                    $result = $this->module->validateOrder(
                        $cart->id,
                        Configuration::get('PS_OS_PREPARATION'),
                        $total,
                        $this->module->displayName,
                        NULL,
                        array(),
                        intval($currency->id),
                        false,
                        $customer->secure_key
                    );
                    
                    if($api_type == "securepay")
            		{
            			echo json_encode(['status' => 'SUCCESS', 'data' => $spay['GatewayPageURL'], 'logo' => $spay['storeLogo'] ]);
            			exit;
            		}
            		else if($api_type == "sandbox")
            		{
            			echo json_encode(['status' => 'success', 'data' => $spay['GatewayPageURL'], 'logo' => $spay['storeLogo'] ]);
            			exit;
            		}
            		else {
		        	   echo json_encode(['status' => 'FAILED', 'data' => NULL, 'message' => $spay['failedreason'] ]);
		        	   exit;
		        	}

                } 
                else
				{
					echo "CURL not activate!";
					exit;
				}
            }
		}
	 	else if($spay['status']=='FAILED')
	  	{
	     	echo "FAILED TO CONNECT WITH SHURJOPAY API";
	     	echo "<br/>Status: ".$spay['status'];
	      	echo "<br/>Failed Reason: ".$spay['failedreason'];
	    	exit;
	  	}*/
	}
	
	
	public function shurjopay_hash_key($store_passwd="", $parameters=array()) 
	{
		$return_key = array(
			"verify_sign"	=>	"",
			"verify_key"	=>	""
		);
		if(!empty($parameters)) {
			# ADD THE PASSWORD
	
			$parameters['store_passwd'] = md5($store_passwd);
	
			# SORTING THE ARRAY KEY
	
			ksort($parameters);	
	
			# CREATE HASH DATA
		
			$hash_string="";
			$verify_key = "";	# VARIFY SIGN
			foreach($parameters as $key=>$value) {
				$hash_string .= $key.'='.($value).'&'; 
				if($key!='store_passwd') {
					$verify_key .= "{$key},";
				}
			}
			$hash_string = rtrim($hash_string,'&');	
			$verify_key = rtrim($verify_key,',');
	
			# THAN MD5 TO VALIDATE THE DATA
	
			$verify_sign = md5($hash_string);
			$return_key['verify_sign'] = $verify_sign;
			$return_key['verify_key'] = $verify_key;
		}
		return $return_key;
	}
}
?>
