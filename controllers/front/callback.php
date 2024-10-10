<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

use Satispay\Prestashop\Classes\Lock\Lock;
use Satispay\Prestashop\Classes\Models\SatispayPendingPayment;
use SatispayGBusiness\Payment;

/**
 * Class SatispayCallbackModuleFrontController.
 *
 * @property Satispay $module
 */
class SatispayCallbackModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $paymentId = Tools::getValue('payment_id');

        $lock = new Lock($paymentId);

        $lock->block(
            5,
            function() use ($paymentId) {
                try {
                    $satispayPendingPayment = SatispayPendingPayment::getByPaymentId($paymentId);

                    // stop if we don't have any pending payment with that id
                    if (!$satispayPendingPayment) return;

                    $order = Order::getByCartId($satispayPendingPayment->cart_id);

                    // stop if we already have an order with this payment
                    if ($order) return;

                    $satispayPayment = Payment::get($paymentId);

                    if ($satispayPayment->status === 'ACCEPTED') {
                        $cart = new Cart((int) $satispayPendingPayment->cart_id);
                        $cartAmountUnit = (int) round($cart->getOrderTotal(true, Cart::BOTH) * 100);

                        $customer = new Customer((int) $cart->id_customer);

                        // stop if we don't have a cart and a customer associated
                        if (!($cart && $customer)) return;

                        // stop if the amount unit paid is different from the one in the cart
                        if (
                            !(
                                $satispayPayment->amount_unit === $cartAmountUnit &&
                                $cartAmountUnit === $satispayPendingPayment->amount_unit
                            )
                        ) return;

                        $validated = $this->module->validateOrder(
                            $cart->id,
                            (int) Configuration::get('PS_OS_PAYMENT'),
                            $satispayPendingPayment->amount_unit / 100,
                            $this->module->displayName,
                            null,
                            [
                                'transaction_id' => $satispayPendingPayment->payment_id
                            ],
                            $cart->id_currency,
                            false,
                            $customer->secure_key,
                            null,
                            $satispayPendingPayment->reference
                        );

                        if ($validated) {
                            $satispayPendingPayment->delete();
                        }
                    } else if ($satispayPayment->status === 'CANCELED') {
                        $satispayPendingPayment = SatispayPendingPayment::getByPaymentId($paymentId);

                        if ($satispayPendingPayment) {
                            $satispayPendingPayment->delete();
                        }
                    }
                } catch (Exception $e) {
                    $this->module->log(
                        get_class($this) . "@postProcess error while processing the callback {$e->getMessage()}",
                        PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR
                    );
                }
            }
        );

        header('HTTP/1.1 204 No Content');

        return;
    }
}
