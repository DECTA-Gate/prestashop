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
			Configuration::get('EXPIRATION_TIME'),
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

		$cartId = $this->context->cookie->decta_cart_id;
		$orderId = $this->context->cookie->decta_order_id;
		$paymentId = $this->context->cookie->decta_payment_id;

		$order = new Order($orderId);
	    
		if (!Validate::isLoadedObject($order)) {
			$decta->log_error('Internal prestashop order error occured', $order);
			Tools::redirect('index.php?controller=order&step=1');
		}
				
		$redirectingUrl = 'index.php?controller=order-confirmation&id_cart='.$cartId.'&id_module='.$this->module->id.'&id_order='.$orderId.'&key='.$customer->secure_key;

		if ($decta->was_payment_successful($cartId, $paymentId)) {
			$order->setCurrentState(Configuration::get('PS_OS_PAYMENT'));
            $order->setMessage('Payment saccessful');
			$decta->log_info('Verification order #' . $cartId . ' done, redirecting to ' . $redirectingUrl);
		} else {
			$order->setCurrentState(Configuration::get('PS_OS_ERROR'));
            $order->setMessage('ERROR: Payment received, but verification failed');
			$decta->log_info('Verification order #' . $cartId . ' failed, redirecting to ' . $redirectingUrl);
		}

		Tools::redirect($redirectingUrl);
    }
}
