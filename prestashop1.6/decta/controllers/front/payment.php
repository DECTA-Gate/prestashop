<?php
require_once _PS_ROOT_DIR_ . '/modules/decta/lib/decta_api.php';
require_once _PS_ROOT_DIR_ . '/modules/decta/lib/decta_logger_prestashop.php';

class DectaPaymentModuleFrontController extends ModuleFrontController
{
    private $decta;

    public function initContent()
    {
        parent::initContent();

        $cart = $this->context->cart;

        if (!$this->module->checkCurrency($cart)) {
            Tools::redirect('index.php?controller=order');
        }

        $language = strtolower(Language::getIsoById(intval($this->context->cookie->id_lang)));
        $language = (!in_array($language, array('en', 'lv', 'ru'))) ? 'en' : $language;

        $this->decta = new DectaAPI(
            Configuration::get('DECTA_PRIVATE_KEY'),
            Configuration::get('DECTA_PUBLIC_KEY'),
            Configuration::get('EXPIRATION_TIME'),
            new DectaLoggerPrestashop()
        );

        $currency = new CurrencyCore($cart->id_currency);
        $customer = new Customer($cart->id_customer);
        $expirationTime = (int)$this->decta->getExpirationTime();
        $minutesToAdd = empty($expirationTime) ? 30 : $expirationTime;
        $this->decta->log_info("Expiration time: " . $minutesToAdd);
        $currentDate = new DateTime("now", new \DateTimeZone("UTC"));
        $total = round($cart->getOrderTotal(true, Cart::BOTH), 2);
        $this->decta->log_info("Total: " . $total);

        $params = array(
            'number' => $cart->id,
            'referrer' => 'prestashop v1.6 module ' . DECTA_MODULE_VERSION,
            'language' => $language,
            'success_redirect' => $this->context->link->getModuleLink('decta', 'validation'),
            'failure_redirect' => $this->context->link->getModuleLink('decta', 'validation'),
            'currency' => $currency->iso_code,
            'issued_overried' => $currentDate->getTimestamp(),
            'due' => $currentDate->add(new DateInterval('PT' . $minutesToAdd . 'M'))->getTimestamp(),
        );

        $this->addUserData($cart, $params);

        $params['products'][] = array(
            'price' => $total,
            'title' => $this->module->l('Invoice for payment #') . $cart->id,
            'quantity' => 1
        );

        $payment = $this->decta->create_payment($params);

        if ($payment) {
            $this->decta->log_info("Create prestashop order start: " . date("d-m-Y H:i:s"));
            $this->module->validateOrder($cart->id, _PS_OS_BANKWIRE_, $total, $this->module->l('Visa / MasterCard'), $this->module->l('Payment successful'), null, (int)$currency->id, false, $customer->secure_key);
            $this->decta->log_info('decta_payment_id: ' . $payment['id']);
            $orderId = Order::getOrderByCartId((int)$cart->id);
            $this->decta->log_info('decta_order_id: ' . $orderId);
            $this->decta->log_info('decta_cart_id: ' . $cart->id);
            $this->context->cookie->__set('decta_payment_id', $payment['id']);
            $this->context->cookie->__set('decta_order_id', $orderId);
            $this->context->cookie->__set('decta_cart_id', $cart->id);
            $this->decta->log_info("Create prestashop order end: " . date("d-m-Y H:i:s"));
            $this->decta->log_info('Got checkout url, redirecting');
            Tools::redirect($payment['full_page_checkout']);
        } else {
            $this->decta->log_error('Error getting checkout url, redirecting');
            Tools::redirect('index.php?controller=order');
        }
    }

    protected function addUserData($cart, &$params)
    {
        $user_address = new Address(intval($cart->id_address_invoice));
        $phone = ($user_address->phone_mobile) ? $user_address->phone_mobile : $user_address->phone;

        $address = $this->context->customer->getAddresses((int)Configuration::get('PS_LANG_DEFAULT'))[0];
        $user_data = [
            'email' => $this->context->customer->email,
            'first_name' => $this->context->customer->firstname,
            'last_name' => $this->context->customer->lastname,
            'phone' => $phone,
            'send_to_email' => true
        ];

        $findUser = $this->decta->getUser($user_data['email'],$user_data['phone']);
        if(!$findUser) {
            if($this->decta->createUser($user_data)){
                $findUser = $this->decta->getUser($user_data['email'],$user_data['phone']);
            }
        }
        $user_data['original_client'] = $findUser['id'];
        $params['client'] = $user_data;
    }
}
