<?php
require_once _PS_ROOT_DIR_ . '/modules/decta/lib/decta_api.php';
require_once _PS_ROOT_DIR_ . '/modules/decta/lib/decta_logger_prestashop.php';

class DectaValidationModuleFrontController extends ModuleFrontController
{

	public function postProcess()
	{
		$decta = new DectaAPI(
			Configuration::get('DECTA_PRIVATE_KEY'),
			Configuration::get('DECTA_PUBLIC_KEY'),
			new DectaLoggerPrestashop()
		);	

		$decta->log_info('Processing success callback');
		$cart = $this->context->cart;
		if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
			$decta->log_error('Internal prestashop error occured', $cart);
			Tools::redirect('index.php?controller=order&step=1');
		}

		$customer = new Customer($cart->id_customer);
		if (!Validate::isLoadedObject($customer)) {
			$decta->log_error('Internal prestashop customer error occured', $customer);
			Tools::redirect('index.php?controller=order&step=1');
		}

		$currency = $this->context->currency;
		$total = (float)$cart->getOrderTotal(true, Cart::BOTH);

		$customerGroup = Group::getCurrent();
        $customerGroupName = $customerGroup->name[1];
		$groupSymbol = strtolower($customerGroupName[0]);
		$cartId = (string) $groupSymbol . $cart->id;
				
		if ($decta->was_payment_successful($cartId, $this->context->cookie->decta_payment_id)) {
			$this->module->validateOrder($cart->id, _PS_OS_PAYMENT_, $total, $this->module->l('Visa / MasterCard'), $this->module->l('Payment successful'), NULL, (int)$currency->id, false, $customer->secure_key);
		} else {
			$this->module->validateOrder($cart->id, _PS_OS_ERROR_, $total, $this->module->l('Visa / MasterCard'), $this->module->l('ERROR: Payment received, but verification failed'), NULL, (int)$currency->id, false, $customer->secure_key);
		}
		$decta->log_info('Verification order ' . $this->module->currentOrder . ' done, redirecting');
		$decta->log_info("REDIRECT = " . 'index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
		Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
	}
}
