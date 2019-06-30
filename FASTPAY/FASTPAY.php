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

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class FASTPAY extends PaymentModule
{
    const FLAG_DISPLAY_PAYMENT_INVITE = 'BANK_WIRE_PAYMENT_INVITE';
    protected $_html = '';
    protected $_postErrors = array();

    public $mode;
    public $title;
    public $storeid;
    public $password;
    public $details;

	public function __construct()
    {
        $this->name = 'FASTPAY';
        $this->tab = 'payments_gateways';
        $this->version = '1.7.4';
        $this->author = 'G.M. Nayem Hossain';
        $this->controllers = array('payment', 'validation', 'request', 'ipn');
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $config = Configuration::getMultiple(array('MODE', 'FASTPAY_TITLE', 'FASTPAY_STORE_ID', 'FASTPAY_STORE_PASSWORD', 'FASTPAY_DETAILS'));
        if (!empty($config['MODE'])) {
            $this->mode = $config['MODE'];
        }
        if (!empty($config['FASTPAY_TITLE'])) {
            $this->title = $config['FASTPAY_TITLE'];
        }
        if (!empty($config['FASTPAY_STORE_ID'])) {
            $this->storeid = $config['FASTPAY_STORE_ID'];
        }
        if (!empty($config['FASTPAY_STORE_PASSWORD'])) {
            $this->password = $config['FASTPAY_STORE_PASSWORD'];
        }
        if (!empty($config['FASTPAY_DETAILS'])) {
            $this->details = $config['FASTPAY_DETAILS'];
        }

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans('FASTPAY', array(), 'Modules.FASTPAY.Admin');
        $this->description = $this->trans('Online Payment Gateway (Fast Pay Payment Module)', array(), 'Modules.FASTPAY.Admin');
        $this->confirmUninstall = $this->trans('Are you sure about removing these details?', array(), 'Modules.FASTPAY.Admin');
    }

    public function install()
    {
        Configuration::updateValue(self::FLAG_DISPLAY_PAYMENT_INVITE, true);
        if (!parent::install() || !$this->registerHook('paymentReturn') || !$this->registerHook('paymentOptions')) {
            return false;
        }
        return true;
    }

    public function uninstall()
    {
        if (!Configuration::deleteByName('MODE')
                || !Configuration::deleteByName('FASTPAY_TITLE')
                || !Configuration::deleteByName('FASTPAY_STORE_ID')
                || !Configuration::deleteByName('FASTPAY_STORE_PASSWORD')
                || !Configuration::deleteByName('FASTPAY_DETAILS')
                || !Configuration::deleteByName(self::FLAG_DISPLAY_PAYMENT_INVITE)
                || !parent::uninstall()) {
            return false;
        }
        return true;
    }

    protected function _postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue(self::FLAG_DISPLAY_PAYMENT_INVITE,
                Tools::getValue(self::FLAG_DISPLAY_PAYMENT_INVITE));

            if (!Tools::getValue('FASTPAY_STORE_ID')) {
                $this->_postErrors[] = $this->trans('Please Enter Your Store ID!', array(), 'Modules.FASTPAY.Admin');
            } elseif (!Tools::getValue('FASTPAY_STORE_PASSWORD')) {
                $this->_postErrors[] = $this->trans('Please Enter Store Password!', array(), "Modules.FASTPAY.Admin");
            }
        }
    }

    protected function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('MODE', Tools::getValue('MODE'));
            Configuration::updateValue('FASTPAY_TITLE', Tools::getValue('FASTPAY_TITLE'));
            Configuration::updateValue('FASTPAY_STORE_ID', Tools::getValue('FASTPAY_STORE_ID'));
            Configuration::updateValue('FASTPAY_STORE_PASSWORD', Tools::getValue('FASTPAY_STORE_PASSWORD'));
            Configuration::updateValue('FASTPAY_DETAILS', Tools::getValue('FASTPAY_DETAILS'));
        }

        $this->_html .= $this->displayConfirmation($this->trans('Settings Updated.', array(), 'Admin.Global'));
    }

    protected function _displayFastpay()
    {
        return $this->display(__FILE__, 'infos.tpl');
    }

    public function getContent()
    {
        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            if (!count($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        } else {
            $this->_html .= '<br />';
        }

        $this->_html .= $this->_displayFastpay();
        $this->_html .= $this->renderForm();

        return $this->_html;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        if(Tools::getValue(self::FLAG_DISPLAY_PAYMENT_INVITE,
                Configuration::get(self::FLAG_DISPLAY_PAYMENT_INVITE)) != 1)

        {
            return;
        }

        // $this->smarty->assign(
        //     $this->getTemplateVarInfos()
        // );

        $newOption = new PaymentOption();
        $newOption->setModuleName($this->name)
                ->setCallToActionText($this->trans(Configuration::get('FASTPAY_TITLE'), array(), 'Modules.FASTPAY.Shop'))
                // ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
                ->setAction($this->context->link->getModuleLink($this->name, 'request', array(), true))
                ->setAdditionalInformation($this->trans(Configuration::get('FASTPAY_DETAILS')));
                // ->setAdditionalInformation($this->fetch('module:FASTPAY/views/templates/hook/FASTPAY_intro.tpl'));
        $payment_options = [
            $newOption,
        ];

        return $payment_options;
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->trans('FASTPAY Configuration', array(), 'Modules.FASTPAY.Admin'),
                    'icon' => 'icon-cogs'
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->trans('Active Module', array(), 'Modules.FASTPAY.Admin'),
                        'name' => self::FLAG_DISPLAY_PAYMENT_INVITE,
                        'is_bool' => true,
                        'hint' => $this->trans('Enable Or Disable Module', array(), 'Modules.FASTPAY.Admin'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->trans('Enable', array(), 'Admin.Global'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->trans('Disable', array(), 'Admin.Global'),
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->trans('Live Mode', array(), 'Modules.FASTPAY.Admin'),
                        'name' => 'MODE',
                        'is_bool' => true,
                        'hint' => $this->trans('Your country\'s legislation may require you to send the invitation to pay by email only. Disabling the option will hide the invitation on the confirmation page.', array(), 'Modules.FASTPAY.Admin'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->trans('Test', array(), 'Admin.Global'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->trans('Live', array(), 'Admin.Global'),
                            )
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Title', array(), 'Modules.FASTPAY.Admin'),
                        'name' => 'FASTPAY_TITLE'
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Merchant ID', array(), 'Modules.FASTPAY.Admin'),
                        'name' => 'FASTPAY_STORE_ID',
                        'desc' => $this->trans('Your Fastpay Merchant ID is the integration credential which can be collected through our managers.', array(), 'Modules.FASTPAY.Admin'),
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Merchant Password', array(), 'Modules.FASTPAY.Admin'),
                        'name' => 'FASTPAY_STORE_PASSWORD',
                        'desc' => $this->trans('Your Fastpay Merchant Password needed to validate transection.', array(), 'Modules.FASTPAY.Admin'),
                        'required' => true
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => $this->trans('Details', array(), 'Modules.FASTPAY.Admin'),
                        'name' => 'FASTPAY_DETAILS'
                    ),
                    array(
                        'label' => $this->trans('IPN URL', array(), 'Modules.FASTPAY.Admin'),
                        'hint' => $this->trans('Use this IPN URL to your merchant panel', array(), 'Modules.FASTPAY.Admin'),
                        'desc' => $this->trans($this->context->link->getModuleLink('FASTPAY', 'ipn', array(), true), array(), 'Modules.FASTPAY.Admin')
                    ),
                ),
                'submit' => array(
                    'title' => $this->trans('Save', array(), 'Admin.Actions'),
                )
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? : 0;
        $this->fields_form = array();
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='
            .$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        return $helper->generateForm(array($fields_form, $fields_form_customization));
    }

    public function getConfigFieldsValues()
    {
        return array(
            'MODE' => Tools::getValue('MODE', Configuration::get('MODE')),
            'FASTPAY_TITLE' => Tools::getValue('FASTPAY_TITLE', Configuration::get('FASTPAY_TITLE')),
            'FASTPAY_STORE_ID' => Tools::getValue('FASTPAY_STORE_ID', Configuration::get('FASTPAY_STORE_ID')),
            'FASTPAY_STORE_PASSWORD' => Tools::getValue('FASTPAY_STORE_PASSWORD', Configuration::get('FASTPAY_STORE_PASSWORD')),
            'FASTPAY_DETAILS' => Tools::getValue('FASTPAY_DETAILS', Configuration::get('FASTPAY_DETAILS')),
            self::FLAG_DISPLAY_PAYMENT_INVITE => Tools::getValue(self::FLAG_DISPLAY_PAYMENT_INVITE,
                Configuration::get(self::FLAG_DISPLAY_PAYMENT_INVITE))

        );
    }

    public function data()
    {   
        global $cookie, $cart; 
        if (!$this->active)
        {
            return;
        }
        $cart = new Cart(intval($cookie->id_cart));
        
        // Buyer details
        $customer = new Customer((int)($cart->id_customer));
        
        $toCurrency = new Currency(Currency::getIdByIsoCode('ZAR'));
        $fromCurrency = new Currency((int)$cookie->id_currency);
        $address = new Address(intval($cart->id_address_invoice));
        $address_ship = new Address(intval($cart->id_address_delivery));
        $total = $cart->getOrderTotal();
        $currency = new Currency(intval($cart->id_currency));
        $currency_iso_code = $currency->iso_code;
        $pfAmount = Tools::convertPriceFull( $total, $fromCurrency, $toCurrency );
       
        $data = array();

        $currency = $this->getCurrency((int)$cart->id_currency);
        if ($cart->id_currency != $currency->id)
        {
            // If fastpay currency differs from local currency
            $cart->id_currency = (int)$currency->id;
            $cookie->id_currency = (int)$cart->id_currency;
            $cart->update();
        }
        

        // Use appropriate merchant identifiers
        // Live
        if( Configuration::get('FASTPAY_MODE') == 'live' )
        {
            $data['info']['merchant_mobile_no'] = Configuration::get('FASTPAY_MERCHANT_ID');
            $data['info']['store_password'] = Configuration::get('FASTPAY_MERCHANT_KEY');
            $data['FASTPAY_url'] = 'https://secure.fast-pay.cash/merchant/generate-payment-token';
        }
        // Sandbox
        else
        {
            $data['info']['merchant_mobile_no'] = Configuration::get('FASTPAY_MERCHANT_ID');
            $data['info']['store_password'] = Configuration::get('FASTPAY_MERCHANT_KEY');
            $data['fastpay_url'] = 'https://dev.fast-pay.cash/merchant/generate-payment-token';
        }
        $data['fastpay_paynow_text'] = Configuration::get('FASTPAY_PAYNOW_TEXT');        
        $data['fastpay_paynow_logo'] = Configuration::get('FASTPAY_PAYNOW_LOGO');
        $data['fastpay_paynow_align'] = Configuration::get('FASTPAY_PAYNOW_ALIGN');
    
        // Create URLs
        $data['info']['value_a'] = $this->context->link->getPageLink( 'order-confirmation', null, null, 'key='.$cart->secure_key.'&id_cart='.(int)($cart->id).'&id_module='.(int)($this->id));
        $data['info']['value_b'] = $this->context->link->getPageLink( 'order-confirmation', null, null, 'key='.$cart->secure_key.'&id_cart='.(int)($cart->id).'&id_module='.(int)($this->id));

        $data['info']['success_url'] = Tools::getHttpHost( true ).__PS_BASE_URI__.'modules/fastpay/validation.php?itn_request=true';
        $data['info']['fail_url'] = Tools::getHttpHost( true ).__PS_BASE_URI__.'modules/fastpay/validation.php?itn_request=true';

        $data['info']['cancel_url'] = Tools::getHttpHost( true ).__PS_BASE_URI__;
        $data['info']['ipn_url'] = Tools::getHttpHost( true ).__PS_BASE_URI__.'modules/fastpay/validation.php?itn_request=true';
        
        //AMOUNT AND CURRENCY OTHER
        $data['info']['order_id'] = $cart->id;
        $data['info']['desc'] = Configuration::get('PS_SHOP_NAME') .' purchase, Cart Item ID #'. $cart->id; 
        $data['info']['currency'] =  $currency_iso_code;
        $data['info']['total_amount'] = number_format( sprintf( "%01.2f", $total ), 2, '.', '' );
        
        //Billing Information 
        $data['info']['cus_name'] = $customer->firstname.' '.$customer->lastname;
        $data['info']['cus_email'] = $customer->email;      
        $data['info']['cus_add1'] = $address->address1;  
        $data['info']['cus_add2'] = $address->address2;  
        $data['info']['cus_city'] = $address->city;  
        $data['info']['cus_state'] = $customer->email;  
        $data['info']['cus_postcode'] = $address->postcode;  
        $data['info']['cus_country'] = $address->country; 
        $data['info']['cus_phone'] = $address->phone; 
        
        //Shipping Information 
        $data['info']['ship_name'] = $address_ship->firstname.' '.$address_ship->lastname;
        $data['info']['ship_add1'] = $address_ship->address1;   
        $data['info']['ship_add2'] = $address_ship->address2; 
        $data['info']['ship_city'] = $address_ship->city; 
        $data['info']['ship_state'] = $customer->email; 
        $data['info']['ship_postcode'] = $address_ship->postcode;  
        $data['info']['ship_country'] = $address_ship->country; 
        return $data;
    }

    // public function getTemplateVarInfos()
    // {

    //     $FastpayCustomText = Tools::nl2br(Configuration::get('FASTPAY_DETAILS'));
    //     if (false === $FastpayCustomText) {
    //         $FastpayCustomText = '';
    //     }

    //     return array(
    //         'FastpayCustomText' => $FastpayCustomText,
    //     );
    // }

}