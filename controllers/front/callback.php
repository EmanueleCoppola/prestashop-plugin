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
    /**
     * Process the incoming Satispay callback request.
     *
     * This method retrieves the payment ID from the request, attempts to lock the payment
     * to prevent race conditions, and handles the payment processing logic. Depending on
     * the Satispay payment status, it either creates a new order, cancels the payment, or
     * deletes the pending payment record.
     *
     * @return void
     */
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
                    if (!Validate::isLoadedObject($satispayPendingPayment)) return;

                    $order = Order::getByCartId($satispayPendingPayment->cart_id);

                    // stop if we already have an order with this payment
                    if (Validate::isLoadedObject($order)) {
                        $satispayPendingPayment->delete();

                        return;
                    };

                    $satispayPayment = Payment::get($paymentId);

                    if ($satispayPayment->status === 'ACCEPTED') {
                        $order = $satispayPendingPayment->acceptOrder(
                            $satispayPayment->amount_unit,
                            $this->module
                        );

                        if (!Validate::isLoadedObject($order)) {
                            try {
                                $cancelOrRefund = Payment::update($paymentId, ['action' => 'CANCEL_OR_REFUND']);

                                if (
                                    $cancelOrRefund->status === 'CANCELED' ||
                                    $cancelOrRefund->status === 'ACCEPTED'
                                ) {
                                    $satispayPendingPayment->delete();
                                }
                            } catch (Exception) {}
                        }
                    } else if ($satispayPayment->status === 'CANCELED') {
                        $satispayPendingPayment->delete();
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
