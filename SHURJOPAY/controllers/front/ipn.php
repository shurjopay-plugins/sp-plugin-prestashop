<?php 
	class ShurjopayIpnModuleFrontController extends ModuleFrontController
	{
		/**
		 * @see FrontController::postProcess()
		**/
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
    	
		public function postProcess()
		{
            if(isset($_POST))
            {
                $tran_id = $_POST['tran_id'];
                $val_id = $_POST['val_id'];
                $cart = $this->context->cart;
                $currency = $this->context->currency;
                $order_id = Order::getOrderByCartId((int)($tran_id));
                $objOrder = new Order($order_id);
                $history = new OrderHistory();
                $history->id_order = (int)$objOrder->id;
                
                $store_passwd = Configuration::get('SHURJOPAY_STORE_PASSWORD');
                
                $order_status = $objOrder->current_state;
                $status = $_POST['status'];
                
                $sp_mode = Configuration::get('MODE');
                
                // echo "<pre>";
                // print_r($_POST);
                // echo "TranId ".$tran_id."<br>";
                // echo "OrderId ".$order_id."<br>";
                // echo "HisOrderId ".$history->id_order."<br>";
                // echo "OrderState ".$order_status."<br>";
                
                if($status == 'FAILED')
                {
                    echo $order_status;
                    $history->changeIdOrderState(8, (int)($objOrder->id));
                    echo "Order ".$status." By IPN";
                }
                elseif($status == 'CANCELLED')
                {
                    echo $order_status;
                    $history->changeIdOrderState(6, (int)($objOrder->id));
                    echo "Order ".$status." By IPN";
                }
                elseif($status == 'VALID' || $status == 'VALIDATED')
                {
                    if( $sp_mode == 1 )
                    {
                        $valid_url_own = ("http://shurjotest.com/validator/api/validationserverAPI.php?val_id=".$val_id."&Store_Id=".Configuration::get('SHURJOPAY_STORE_ID')."&Store_Passwd=".Configuration::get('SHURJOPAY_STORE_PASSWORD')."&v=1&format=json");
                    }
                    else
                    {
                        $valid_url_own = ("https://shurjotest.com/validator/api/validationserverAPI.php?val_id=".$val_id."&Store_Id=".Configuration::get('SHURJOPAY_STORE_ID')."&Store_Passwd=".Configuration::get('SHURJOPAY_STORE_PASSWORD')."&v=1&format=json");  
                    }
            
                    $handle = curl_init();
                    curl_setopt($handle, CURLOPT_URL, $valid_url_own);
                    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
                    curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
                        
                    $result = curl_exec($handle);
                        
                    
                    $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
                        
                    if($code == 200 && !( curl_errno($handle)))
                    {   
                        $result = json_decode($result);
            
                        if($this->shurjopay_hash_key($store_passwd, $_POST))
                        {
                            if ($_POST['currency_amount'] == $result->currency_amount) 
                            {
                                if($result->status=='VALIDATED' || $result->status=='VALID') 
                                {
                                    if($order_status == 3)
                                    {
                                        if($_POST['card_type'] != "")
                                        {
                                            $history->changeIdOrderState(2, (int)($objOrder->id));
                                            $msg =  "Hash validation success.";
                                        }
                                        else
                                        {
                                            $msg=  "Card Type Empty or Mismatched";
                                        }
                                    }
                                    else
                                    {
                                        $msg=  "Order already in processing Status";
                                    }
                                }
                                else
                                {
                                    $msg=  "Your Validation id could not be Verified";
                                }
                            }
                            else
                            {
                                $msg= "Your Paid Amount is Mismatched";
                            }   
                        }
                        else
                        {
                            $msg =  "Hash validation failed.";                      
                        }
                        echo $msg;
                    }
                }
                else
                {
                    echo "<h4>No data found for IPN Request</h4>";
                }
            }
		}
		
		
		//------------------------------ END ---------------------------------
	}
?>
