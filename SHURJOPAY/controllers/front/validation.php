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


    
		$customer = new Customer($cart->id_customer);
		$currency = $this->context->currency;
		$total = (float)$cart->getOrderTotal(true, Cart::BOTH);

		$sp_mode = Configuration::get('MODE');
		if (!Validate::isLoadedObject($customer))
		{
			Tools::redirect('index.php?controller=order&step=1');
		}
		


        $objOrder = new Order($tran_id);
        $history = new OrderHistory();
        $history->id_order = (int)$objOrder->id;
        $order_status = $objOrder->current_state;

        
        $success_URL = Tools::getHttpHost( true ).__PS_BASE_URI__.'index.php?controller=order-confirmation&id_cart='.$tran_id.'&id_module='.$this->module->id.'&id_order='.$history->id_order.'&key='.$customer->secure_key;
        $failed_URL = Tools::getHttpHost( true ).__PS_BASE_URI__.'index.php?controller=order-detail&id_cart='.$tran_id.'&id_module='.$this->module->id.'&id_order='.$history->id_order.'&key='.$customer->secure_key;
        $data['store_id'] = Configuration::get('SHURJOPAY_STORE_ID');
        $data['store_passwd'] = Configuration::get('SHURJOPAY_STORE_PASSWORD');
        $data['store_prefix'] = Configuration::get('SHURJOPAY_PREFIX');
        $data['engine_url'] = Configuration::get('ENGINE_URL');



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
            CURLOPT_POSTFIELDS =>'{                                            "username": "'.$data['store_id'].'",
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
            /* print_r($customer);
             exit();*/

            $this->module->validateOrder($cart->id, Configuration::get('PS_OS_PAYMENT'), $total, $shurjopay->displayName, NULL, array(), (int)$currency->id, false, $customer->secure_key);
            //exit();
            $history->changeIdOrderState(2, (int)($objOrder->id)); //order status= Payment accepted

            Tools::redirect($success_URL);


        }else{
            $history->changeIdOrderState(6, (int)($objOrder->id));

            Tools::redirect($failed_URL);

        }
        
	}
}
