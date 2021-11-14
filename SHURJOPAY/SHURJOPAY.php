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

class SHURJOPAY extends PaymentModule
{
    const FLAG_DISPLAY_PAYMENT_INVITE = 'BANK_WIRE_PAYMENT_INVITE';
    protected $_html = '';
    protected $_postErrors = array();

    public $mode;
    public $title;
    public $storeid;
    public $password;
    public $details;
    public $prefix;
    public $engine;

	public function __construct()
    {
        $this->name = 'SHURJOPAY';
        $this->tab = 'payments_gateways';
        $this->version = '2.0.0';
        $this->author = 'Nazia Afsan Mowmita';
        $this->className  = array('payment', 'validation', 'request', 'ipn');
        //$this->is_eu_compatible = 1;
		//$this->ps_versions_compliancy = array('min' => '1.6', 'max' => '1.6');

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $config = Configuration::getMultiple(array('MODE', 'SHURJOPAY_TITLE', 'SHURJOPAY_STORE_ID', 'SHURJOPAY_STORE_PASSWORD', 'SHURJOPAY_DETAILS', 'SHURJOPAY_PREFIX', 'ENGINE_URL'));
        if (!empty($config['MODE'])) {
            $this->mode = $config['MODE'];
        }
        if (!empty($config['SHURJOPAY_TITLE'])) {
            $this->title = $config['SHURJOPAY_TITLE'];
        }
        if (!empty($config['SHURJOPAY_STORE_ID'])) {
            $this->storeid = $config['SHURJOPAY_STORE_ID'];
        }
        if (!empty($config['SHURJOPAY_STORE_PASSWORD'])) {
            $this->password = $config['SHURJOPAY_STORE_PASSWORD'];
        }
        if (!empty($config['SHURJOPAY_DETAILS'])) {
            $this->details = $config['SHURJOPAY_DETAILS'];
        }
        if (!empty($config['SHURJOPAY_PREFIX'])) {
            $this->prefix = $config['SHURJOPAY_PREFIX'];
        }
        if (!empty($config['ENGINE_URL'])) {
            $this->engine = $config['ENGINE_URL'];
        }

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = 'SHURJOPAY';
        $this->description = 'Online Payment Gateway (Local or International Debit/Credit/VISA/Master Card, bKash, DBBL, NAGAD etc)';
        $this->confirmUninstall = 'Are you sure about removing these details?';
    }

    public function install()
    {
        Configuration::updateValue(self::FLAG_DISPLAY_PAYMENT_INVITE, true);
        if (!parent::install() || !$this->registerHook('paymentReturn') || !$this->registerHook('paymentOptions') || !$this->registerHook('actionFrontControllerSetMedia')) {
            return false;
        }
        return true;
    }

    public function uninstall()
    {
        if (!Configuration::deleteByName('MODE')
                || !Configuration::deleteByName('SHURJOPAY_TITLE')
                || !Configuration::deleteByName('SHURJOPAY_STORE_ID')
                || !Configuration::deleteByName('SHURJOPAY_STORE_PASSWORD')
                || !Configuration::deleteByName('SHURJOPAY_DETAILS')
                 || !Configuration::deleteByName('SHURJOPAY_PREFIX')
                 || !Configuration::deleteByName('ENGINE_URL')
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

            if (!Tools::getValue('SHURJOPAY_STORE_ID')) {
                $this->_postErrors[] = 'Please Enter Your Store ID!';
            } elseif (!Tools::getValue('SHURJOPAY_STORE_PASSWORD')) {
                $this->_postErrors[] = 'Please Enter Store Password!';
            }
        }
    }

    protected function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('MODE', Tools::getValue('MODE'));
            Configuration::updateValue('SHURJOPAY_TITLE', Tools::getValue('SHURJOPAY_TITLE'));
            Configuration::updateValue('SHURJOPAY_STORE_ID', Tools::getValue('SHURJOPAY_STORE_ID'));
            Configuration::updateValue('SHURJOPAY_STORE_PASSWORD', Tools::getValue('SHURJOPAY_STORE_PASSWORD'));
            Configuration::updateValue('SHURJOPAY_DETAILS', Tools::getValue('SHURJOPAY_DETAILS'));
            Configuration::updateValue('SHURJOPAY_PREFIX', Tools::getValue('SHURJOPAY_PREFIX'));
            Configuration::updateValue('ENGINE_URL', Tools::getValue('ENGINE_URL'));

        }

        $this->_html .= $this->displayConfirmation('Settings Updated.');
    }

    protected function _displayShurjopay()
    {
        return $this->display(__FILE__, 'infos.tpl');
    }

    public function hookActionFrontControllerSetMedia($params)
    {
        // On every pages
        $this->context->controller->registerJavascript('shurjopay','modules/'.$this->name.'/js/lib/shurjopay.js',['position' => 'bottom','priority' => 10,]);
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

        $this->_html .= $this->_displayShurjopay();
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

        $this->smarty->assign(
            $this->getTemplateVars()
        );

        $newOption = new PaymentOption();
        $newOption->setModuleName($this->name)
                ->setCallToActionText(Configuration::get('SHURJOPAY_TITLE'))
                ->setAction($this->context->link->getModuleLink($this->name, 'request', array(), true))
                // ->setAdditionalInformation($this->trans(Configuration::get('SHURJOPAY_DETAILS')));
                ->setAdditionalInformation($this->fetch('module:SHURJOPAY/views/templates/hook/easy_checkout.tpl'));
        $newOption->setBinary(true);
        $payment_options = [
            $newOption,
        ];

        return $payment_options;
    }

    // public function getTemplateVarInfos()
    // {
    //     return $this->display(__FILE__, 'infos.tpl');
    // }

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
                    'title' => 'SHURJOPAY Configuration',
                    'icon' => 'icon-cogs'
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => 'Active Module',
                        'name' => self::FLAG_DISPLAY_PAYMENT_INVITE,
                        'is_bool' => true,
                        'hint' => 'Enable Or Disable Module',
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => 'Enable',
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' =>'Disable',
                            )
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'Title',
                        'name' => 'SHURJOPAY_TITLE'
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'Merchant ID',
                        'name' => 'SHURJOPAY_STORE_ID',
                        'desc' => 'Your SHURJOPAY Merchant ID is the integration credential which can be collected through our managers.',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'Merchant Password',
                        'name' => 'SHURJOPAY_STORE_PASSWORD',
                        'desc' => 'Your SHURJOPAY Merchant Password needed to validate transection.',
                        'required' => true
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => 'Details',
                        'name' => 'SHURJOPAY_DETAILS'
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => 'Prefix',
                        'name' => 'SHURJOPAY_PREFIX',
                        'required' => true
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => 'Engine URL',
                        'name' => 'ENGINE_URL',
                        'required' => true
                    ),
                ),
                'submit' => array(
                    'title' => 'Save',
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

        return $helper->generateForm(array($fields_form ));
    }

    public function getConfigFieldsValues()
    {
        return array(
            'MODE' => Tools::getValue('MODE', Configuration::get('MODE')),
            'SHURJOPAY_TITLE' => Tools::getValue('SHURJOPAY_TITLE', Configuration::get('SHURJOPAY_TITLE')),
            'SHURJOPAY_STORE_ID' => Tools::getValue('SHURJOPAY_STORE_ID', Configuration::get('SHURJOPAY_STORE_ID')),
            'SHURJOPAY_STORE_PASSWORD' => Tools::getValue('SHURJOPAY_STORE_PASSWORD', Configuration::get('SHURJOPAY_STORE_PASSWORD')),
            'SHURJOPAY_DETAILS' => Tools::getValue('SHURJOPAY_DETAILS', Configuration::get('SHURJOPAY_DETAILS')),
            'SHURJOPAY_PREFIX' => Tools::getValue('SHURJOPAY_PREFIX', Configuration::get('SHURJOPAY_PREFIX')),
            'ENGINE_URL' => Tools::getValue('ENGINE_URL', Configuration::get('ENGINE_URL')),
            self::FLAG_DISPLAY_PAYMENT_INVITE => Tools::getValue(self::FLAG_DISPLAY_PAYMENT_INVITE,
                Configuration::get(self::FLAG_DISPLAY_PAYMENT_INVITE))

        );
    }

    public function getTemplateVars()
    {
        global $cookie, $cart; 

        $cart = new Cart(intval($cookie->id_cart));
        $tran_id = $cart->id;
        $sp_mode = Configuration::get('MODE');
        if($sp_mode == 1)
        {
            $api_type = "securepay";
        }
        else
        {
            $api_type = "sandbox";
        }

        return [
            'tran_id' => $tran_id,
            'payment_options' => $this->name,
            'details' => Tools::getValue('SHURJOPAY_DETAILS', Configuration::get('SHURJOPAY_DETAILS')),
            'endpoint' => $this->context->link->getModuleLink($this->name, 'request', array(), true),
            'api_type' => $api_type,
        ];
    }

}




