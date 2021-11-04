<?php
/*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

/**
 * @since 1.5.0
 */

class ShurjopayValidationModuleFrontController extends ModuleFrontController
{
	/**
	 * @see FrontController::postProcess()
	**/
	public function postProcess()
	{
        $actual_link = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $query_str = parse_url($actual_link, PHP_URL_QUERY);
        parse_str($query_str, $query_params);
        $tran_id =$query_params['order_id'];
	   // echo $tran_id = $_get['tran_id'];

	    $cart = $this->context->cart;
	    $shurjopay = Module::getInstanceByName('SHURJOPAY');

		if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active)
			Tools::redirect('index.php?controller=order&step=1');

		// Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
/*		$authorized = false;

		foreach (Module::getPaymentModules() as $module)
		{

			if ($module['name'] == 'SHURJOPAY')
			{
				$authorized = true;
				break;
			}

		}
		if (!$authorized)
		{
			die($this->module->getTranslator()->trans('This payment method is not available.', array(), 'Modules.SHURJOPAY.Shop'));
		}*/
    
		$customer = new Customer($cart->id_customer);
		$currency = $this->context->currency;
		$total = (float)$cart->getOrderTotal(true, Cart::BOTH);
		//$order_id = Order::getOrderByCartId((int)($tran_id));
		$val_id = $_POST['val_id'];
		
// 		echo $tran_id."----".$order_id."----".$val_id;exit;

		$sp_mode = Configuration::get('MODE');
		if (!Validate::isLoadedObject($customer))
		{
			Tools::redirect('index.php?controller=order&step=1');
		}
		
	    if( $sp_mode == 1 )
        {
            $valid_url_own = ("");
        }
        else
        {
            $valid_url_own = ("");
        }
        
        // echo $valid_url_own."<br>";
        
        $objOrder = new Order($order_id);
        $history = new OrderHistory();
        $history->id_order = (int)$objOrder->id;
        $order_status = $objOrder->current_state;
        // echo $history->id_order;
        // echo $tran_id;
        // echo $objOrder->id;
        // print_r($_POST);
        // print_r($history);
        // exit;
        
        $success_URL = Tools::getHttpHost( true ).__PS_BASE_URI__.'index.php?controller=order-confirmation&id_cart='.$tran_id.'&id_module='.$this->module->id.'&id_order='.$history->id_order.'&key='.$customer->secure_key;
        $failed_URL = Tools::getHttpHost( true ).__PS_BASE_URI__.'index.php?controller=order-detail&id_cart='.$tran_id.'&id_module='.$this->module->id.'&id_order='.$history->id_order.'&key='.$customer->secure_key;
        $data['store_id'] = Configuration::get('SHURJOPAY_STORE_ID');
        $data['store_passwd'] = Configuration::get('SHURJOPAY_STORE_PASSWORD');

///

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
        $arr=json_decode($response);
        $order_id = array(
            'order_id' => $tran_id);
        $tran_id=json_encode($order_id);

        if(!empty($arr->token))
        {

            $tok=($arr->token);
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $data['engine_url'].'/api/verification',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS =>$tran_id,
                CURLOPT_HTTPHEADER => array(
                    'Authorization:Bearer '.$tok,
                    'Content-Type: application/json'
                ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);
        }
        $response=json_decode($response);


        $sp_code= $response[0]->sp_code;
        if($sp_code==1000)
        {

            Tools::redirect($success_URL);


        }else{
            Tools::redirect($failed_URL);

        }
///

 /*       if($_POST['status'] == 'VALID' && $order_status == 3)
        {
            if (isset($_POST['tran_id']) && isset($_POST['val_id']) && isset($_POST['amount'])) 
    		{
    			$handle = curl_init();
                curl_setopt($handle, CURLOPT_URL, $valid_url_own);
                curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
                $result = curl_exec($handle);
                $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

                // print_r($result);exit;
                if($code == 200 && !( curl_errno($handle)))
                {	
                	# TO CONVERT AS ARRAY
                    # $result = json_decode($result, true);
                	# $status = $result['status'];
                	
                	
                	# TO CONVERT AS OBJECT
                	$result = json_decode($result);
                	# TRANSACTION INFO
                	$status = $result->status;	
                	$tran_date = $result->tran_date;
                	$tran_id = $result->tran_id;
                	$val_id = $result->val_id;
                	$amount = $result->amount;
                	$store_amount = $result->store_amount;
                	$currency_amount = $result->currency_amount;
                	$bank_tran_id = $result->bank_tran_id;
                	$card_type = $result->card_type;
                	$currency_amount= $result->currency_amount;
                	# ISSUER INFO
                	$card_no = $result->card_no;
                	$card_issuer = $result->card_issuer;
                	$card_brand = $result->card_brand;
                	$card_issuer_country = $result->card_issuer_country;
                	$card_issuer_country_code = $result->card_issuer_country_code;   
    
                	//Payment Risk Status
                	$risk_level = $result->risk_level;
                	$risk_title = $result->risk_title;
                	
                	if($status=='VALID')
                    {
                        if($risk_level==0)
                        { 
                            $status = 'success';
                            $return_url = $success_URL;
                        }
                        if($risk_level==1)
                        { 
                            $status = 'risk';
                            $return_url = $failed_URL;
                        } 
                    }
                    elseif($status=='VALIDATED')
                    {
                        if($risk_level==0)
                        { 
                            $status = 'success';
                            $return_url = $success_URL;
                        }
                        if($risk_level==1)
                        { 
                            $status = 'risk';
                            $return_url = $failed_URL;
                        } 
                    }
                    else
                    {
                        $status = 'failed';
                        $return_url = $failed_URL;
                    }
                    
                    if($status == 'success')
                    {
                        if($order_status == 3)
                        {
                            $history->changeIdOrderState(2, (int)($objOrder->id));
                            Tools::redirect($return_url);
                        }
                        else
                        {
                            Tools::redirect($return_url);
                        }
                    }
                    elseif($status == 'risk')
                    {
                        if($order_status == 3)
                        {
                            $history->changeIdOrderState(8, (int)($objOrder->id));
                            Tools::redirect($return_url);
                        }
                        else
                        {
                            Tools::redirect($return_url);
                        }
                    }
                    elseif($status == 'failed')
                    {
                        if($order_status == 3)
                        {
                            $history->changeIdOrderState(8, (int)($objOrder->id));
                            Tools::redirect($return_url);
                        }
                        else
                        {
                            Tools::redirect($return_url);
                        }
                    }
                    else
                    {
                        // If payment fails, delete the purchase log
                        if($order_status == 3)
                        {
                            $history->changeIdOrderState(8, (int)($objOrder->id));
                            Tools::redirect($return_url);
                        }
                        else
                        {
                            Tools::redirect($return_url); 
                        }
                    }
                    
    		    } 
        		else 
        		{
        		    echo "Order not validate";
        		}
    		}
    		else
    		{
    		    echo "Transaction Id or Validation Id missing";
    		    exit;
    		}
        }
        elseif($order_status == 2)
        {
            Tools::redirect($success_URL);
        }
        elseif($_POST['status'] == 'FAILED')
        {
            $history->changeIdOrderState(8, (int)($objOrder->id));
            Tools::redirect($failed_URL);
        }
        elseif($_POST['status'] == 'CANCELLED')
        {
            $history->changeIdOrderState(6, (int)($objOrder->id));
            // echo $objOrder->id;
            Tools::redirect($failed_URL);
        }*/
        
	}
}
