<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class Decta extends PaymentModule
{
    protected $_html = '';
    protected $_postErrors = array();

    public function __construct()
    {
        $this->name = 'decta';
        $this->tab = 'payments_gateways';
        $this->version = '2.0';
        $this->author = 'Decta';
        $this->controllers = array('payment', 'validation');
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Decta');
        $this->description = $this->l('Accept payments for your products via bank cards');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall the module?');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }

    }

    public function install()
    {
        if (
            !parent::install() ||
            !$this->registerHook('payment') ||
            !$this->registerHook('paymentReturn') ||
            !$this->registerHook('displayHeader')
        ) {
            return false;
        }

        return true;
    }

    public function uninstall()
    {
        if (!Configuration::deleteByName('DECTA_PUBLIC_KEY')
            || !Configuration::deleteByName('DECTA_PRIVATE_KEY')
            || !parent::uninstall()) {
            return false;
        }

        return true;
    }

    protected function _postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            if (!Tools::getValue('DECTA_PUBLIC_KEY')) {
                $this->_postErrors[] = $this->l('Public key is required.');
            }

            if (!Tools::getValue('DECTA_PRIVATE_KEY')) {
                $this->_postErrors[] = $this->l('Private key is required.');
            }

        }
    }

    protected function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('DECTA_PUBLIC_KEY', Tools::getValue('DECTA_PUBLIC_KEY'));
            Configuration::updateValue('DECTA_PRIVATE_KEY', Tools::getValue('DECTA_PRIVATE_KEY'));
        }
        $this->_html .= $this->displayConfirmation($this->l('Settings updated'));
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

        $this->_html .= $this->display(__FILE__, 'infos.tpl');
        $this->_html .= $this->renderForm();

        return $this->_html;
    }

    public function hookPayment($params)
    {

        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $this->smarty->assign(array(
            'this_path' => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/',
        ));
        return $this->display(__FILE__, 'payment.tpl');
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        $state = $params['objOrder']->getCurrentState();
        if (in_array($state, array(_PS_OS_PAYMENT_, _PS_OS_OUTOFSTOCK_PAID_, _PS_OS_ERROR_))) {
            $this->smarty->assign(array(
                'total_to_pay' => Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false),
                'status' => 'ok',
                'id_order' => $params['objOrder']->id,
            ));
            if (isset($params['objOrder']->reference) && !empty($params['objOrder']->reference)) {
                $this->smarty->assign('reference', $params['objOrder']->reference);
            }

        } else {
            $this->smarty->assign('status', 'failed');
        }

        return $this->display(__FILE__, 'payment_return.tpl');
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
                    'title' => $this->l('API Keys'),
                    'icon' => 'icon-envelope',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Public key'),
                        'name' => 'DECTA_PUBLIC_KEY',
                        'required' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Secret key'),
                        'name' => 'DECTA_PRIVATE_KEY',
                        'required' => true,
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $this->fields_form = array();
        $helper->id = (int) Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($fields_form));
    }

    public function getConfigFieldsValues()
    {
        return array(
            'DECTA_PUBLIC_KEY' => Tools::getValue('DECTA_PUBLIC_KEY', Configuration::get('DECTA_PUBLIC_KEY')),
            'DECTA_PRIVATE_KEY' => Tools::getValue('DECTA_PRIVATE_KEY', Configuration::get('DECTA_PRIVATE_KEY')),
        );
    }

    public function hookDisplayHeader()
    {
        $this->context->controller->addCSS($this->_path . 'css/decta.css', 'all');
    }
}
