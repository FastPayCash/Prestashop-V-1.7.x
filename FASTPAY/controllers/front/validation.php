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

class FastpayValidationModuleFrontController extends ModuleFrontController
{
	/**
	 * @see FrontController::postProcess()
	**/
	public function postProcess()
	{
	
	    $tran_id = $_REQUEST['tran_id'];
	    $cart = $this->context->cart;
	    $fastpay = Module::getInstanceByName('FASTPAY');
		if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active)
			Tools::redirect('index.php?controller=order&step=1');

		// Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
		$authorized = false;
		foreach (Module::getPaymentModules() as $module)
		{
			if ($module['name'] == 'FASTPAY')
			{
				$authorized = true;
				break;
			}
		}
		if (!$authorized)
		{
			die($this->module->getTranslator()->trans('This payment method is not available.', array(), 'Modules.FASTPAY.Shop'));
		}
    
		$customer = new Customer($cart->id_customer);
		$currency = $this->context->currency;
		$total = (float)$cart->getOrderTotal(true, Cart::BOTH);
		$order_id = Order::getOrderByCartId((int)($tran_id));
//val_id = $_REQUEST['val_id'];
		
// 		echo $tran_id."----".$order_id."----".$val_id;exit;

		$sslc_mode = Configuration::get('MODE');
		if (!Validate::isLoadedObject($customer))
		{
			Tools::redirect('index.php?controller=order&step=1');
		}
		
	    if( $sslc_mode == 1 )
        {
            //$valid_url_own = ("https://securepay.fastpay.com/validator/api/validationserverAPI.php?val_id=".$val_id."&Store_Id=".Configuration::get('FASTPAY_STORE_ID')."&Store_Passwd=".Configuration::get('FASTPAY_STORE_PASSWORD')."&v=1&format=json");
            $valid_url_own = ("https://secure.fast-pay.cash/merchant/payment/validation");
        }
        else
        {
            //$valid_url_own = ("https://sandbox.fastpay.com/validator/api/validationserverAPI.php?val_id=".$val_id."&Store_Id=".Configuration::get('FASTPAY_STORE_ID')."&Store_Passwd=".Configuration::get('FASTPAY_STORE_PASSWORD')."&v=1&format=json");
            $valid_url_own = ("https://dev.fast-pay.cash/merchant/payment/validation");
        }
        
        // echo $valid_url_own."<br>";
        
        $objOrder = new Order($order_id);
        $history = new OrderHistory();
        $history->id_order = (int)$objOrder->id;
        $order_status = $objOrder->current_state;
        // echo $history->id_order;
        // echo $tran_id;
        // echo $objOrder->id;
        // print_r($_REQUEST);
        // print_r($history);
        // exit;
        
        $success_URL = Tools::getHttpHost( true ).__PS_BASE_URI__.'index.php?controller=order-confirmation&id_cart='.$tran_id.'&id_module='.$this->module->id.'&id_order='.$history->id_order.'&key='.$customer->secure_key;
        $failed_URL = Tools::getHttpHost( true ).__PS_BASE_URI__.'index.php?controller=order-detail&id_cart='.$tran_id.'&id_module='.$this->module->id.'&id_order='.$history->id_order.'&key='.$customer->secure_key;

        if( $order_status == 3) //$_REQUEST['status'] == 'VALID' &&
        {
            if (isset($_REQUEST['tran_id'])) 
    		{
                $post_data = array();
                $post_data['merchant_mobile_no']=Configuration::get('FASTPAY_STORE_ID');
                $post_data['store_password']=Configuration::get('FASTPAY_STORE_PASSWORD');
                $post_data['order_id']=$_REQUEST['tran_id'];


    			$handle = curl_init();
                curl_setopt($handle, CURLOPT_URL, $valid_url_own);
                curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($handle, CURLOPT_POST, 1 );
                curl_setopt($handle, CURLOPT_POSTFIELDS, $post_data);
                $result = curl_exec($handle);
                $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

                // print_r($result);exit;
                if($code == 200 && !( curl_errno($handle)))
                {	
                	# TO CONVERT AS ARRAY
                    # $result = json_decode($result, true);
                	# $status = $result['status'];
                	
                	
                	# TO CONVERT AS OBJECT
                	$result = json_decode($result, true);
                	# TRANSACTION INFO
                	$code = $result['code'];	
                	$messages = $result['messages'];
                	$data = $result['data'];
                    /*echo "<pre>";
                    print_r($data);exit;*/
                    $status = $data['status'];
                	$bank_tran_id = $data['transaction_id'];
                	$amount = $data['bill_amount'];
                	$received_at = $data['received_at'];
                	$order_id = $data['order_id'];
                	
                    
                    
                    if(strtolower($status) == 'success')
                    {
                        if($order_status == 3)
                        {
                            $history->changeIdOrderState(2, (int)($objOrder->id));
                            Tools::redirect($success_URL);
                        }
                        else
                        {
                            Tools::redirect($success_URL);
                        }
                    }
                    elseif($status == 'cancelled')
                    {
                        if($order_status == 3)
                        {
                            $history->changeIdOrderState(8, (int)($objOrder->id));
                            Tools::redirect($failed_URL);
                        }
                        else
                        {
                            Tools::redirect($failed_URL);
                        }
                    }
                    elseif($status == 'failed')
                    {
                        if($order_status == 3)
                        {
                            $history->changeIdOrderState(8, (int)($objOrder->id));
                            Tools::redirect($failed_URL);
                        }
                        else
                        {
                            Tools::redirect($failed_URL);
                        }
                    }
                    else
                    {
                        // If payment fails, delete the purchase log
                        if($order_status == 3)
                        {
                            $history->changeIdOrderState(8, (int)($objOrder->id));
                            Tools::redirect($failed_URL);
                        }
                        else
                        {
                            Tools::redirect($failed_URL); 
                        }
                    }
                    
                    
    		    } 
        		else 
        		{
        		    echo "Order not validate";
                    exit;
        		}
    		}
    		else
    		{
    		    echo "Tran Id or Val Id missing";
    		    exit;
    		}
        }
        elseif($order_status == 2)
        {
            Tools::redirect($success_URL);
        }
        else{
            $history->changeIdOrderState(6, (int)($objOrder->id));
            // echo $objOrder->id;
            Tools::redirect($failed_URL);
        }        
	}
}
