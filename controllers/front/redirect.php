<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

use Satispay\Prestashop\Classes\Lock\Lock;
use Satispay\Prestashop\Classes\Models\SatispayPendingPayment;
use SatispayGBusiness\Payment;

/**
 * Class SatispayRedirectModuleFrontController
 *
 * @property Satispay $module
 */
class SatispayRedirectModuleFrontController extends ModuleFrontController
{
    /**
     * Processes the Satispay payment response.
     *
     * This method retrieves the pending payment information, checks the payment status from Satispay,
     * and processes the payment accordingly. It may redirect the user to the order confirmation page,
     * or back to the cart if the payment is canceled or invalid.
     *
     * @return void
     */
    public function postProcess()
    {
        $spp = Tools::getValue('spp', null);

        if ($spp) {
            $spp = (int) base64_decode($spp);
        }

        $satispayPendingPayment = new SatispayPendingPayment($spp);

        if (!$satispayPendingPayment->id) {
            return $this->redirectToCart();
        }

        $lock = new Lock($satispayPendingPayment->payment_id);

        return
            $lock->block(
                5,
                function() use ($satispayPendingPayment) {
                    $order = Order::getByCartId($satispayPendingPayment->cart_id);

                    // stop if we already have an order with this payment
                    // this means that the order was successful
                    if (Validate::isLoadedObject($order)) {
                        return
                            $this->redirectToConfirmation(
                                $satispayPendingPayment->cart_id,
                                $order->id,
                                $order->getCustomer()->secure_key
                            );
                    }

                    try {
                        $satispayPayment = Payment::get($satispayPendingPayment->payment_id);

                        if ($satispayPayment->status === 'ACCEPTED') {
                            $order = $satispayPendingPayment->acceptOrder(
                                $satispayPayment->amount_unit,
                                $this->module
                            );

                            if (Validate::isLoadedObject($order)) {
                                return
                                    $this->redirectToConfirmation(
                                        $satispayPendingPayment->cart_id,
                                        $order->id,
                                        $order->getCustomer()->secure_key
                                    );
                            } else {
                                try {
                                    $cancelOrRefund = Payment::update(
                                        $satispayPendingPayment->payment_id,
                                        [
                                            'action' => 'CANCEL_OR_REFUND'
                                        ]
                                    );
    
                                    if (
                                        $cancelOrRefund->status === 'CANCELED' ||
                                        $cancelOrRefund->status === 'ACCEPTED'
                                    ) {
                                        $satispayPendingPayment->delete();
                                    }
                                } catch (Exception $e) {
                                    $this->module->log(
                                        get_class($this) . "@postProcess error while executing CANCEL_OR_REFUND {$e->getMessage()}",
                                        PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR
                                    );
                                }
                            }
                        } else if ($satispayPayment->status === 'PENDING') {
                            try {
                                // try to cancel the payment
                                try {
                                    $cancel = Payment::update($satispayPendingPayment->payment_id, ['action' => 'CANCEL']);

                                    if ($cancel->status === 'CANCELED') {
                                        return $this->redirectToCart();
                                    }
                                } catch (Exception $e) {
                                    $this->module->log(
                                        get_class($this) . "@postProcess error while executing CANCEL {$e->getMessage()}",
                                        PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR
                                    );

                                    $satispayPayment = Payment::get($satispayPendingPayment->payment_id);

                                    if ($satispayPayment->status === 'ACCEPTED') {
                                        $order = $satispayPendingPayment->acceptOrder(
                                            $satispayPayment->amount_unit,
                                            $this->module
                                        );

                                        if (Validate::isLoadedObject($order)) {
                                            return
                                                $this->redirectToConfirmation(
                                                    $satispayPendingPayment->cart_id,
                                                    $order->id,
                                                    $order->getCustomer()->secure_key
                                                );
                                        } else {
                                            try {
                                                $cancelOrRefund = Payment::update($satispayPendingPayment->payment_id, ['action' => 'CANCEL_OR_REFUND']);
                
                                                if (
                                                    $cancelOrRefund->status === 'CANCELED' ||
                                                    $cancelOrRefund->status === 'ACCEPTED'
                                                ) {
                                                    $satispayPendingPayment->delete();
                                                }
                                            } catch (Exception $e) {
                                                $this->module->log(
                                                    get_class($this) . "@postProcess error while executing CANCEL_OR_REFUND {$e->getMessage()}",
                                                    PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR
                                                );
                                            }
                                        }
                                    }
                                }
                            } catch (Exception) {
                                // here we can use silent catch as in this case
                                // everything will be handled by the default return
                            }
                        } else if ($satispayPayment->status === 'CANCELED') {
                            $satispayPendingPayment->delete();
                        }
                    } catch (Exception $e) {
                        $this->module->log(
                            get_class($this) . "@postProcess error while processing the redirect {$e->getMessage()}",
                            PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR
                        );
                    }

                    return $this->redirectToCart();
                }
            );
    }

    /**
     * Redirects the user back to the cart page.
     *
     * This method redirects the user to the cart in case the payment process needs
     * to be canceled or when the user decides to modify their cart before completing the order.
     *
     * @return void
     */
    public function redirectToCart()
    {
        return
            Tools::redirect(
                $this->context->link->getPageLink(
                    'order',
                    true,
                    $this->context->language->id
                )
            );
    }

    /**
     * Redirects the user to the order confirmation page.
     *
     * This method redirects the user to the order confirmation page after a successful payment.
     * It passes the necessary details, such as the cart ID, order ID, module ID, and customer key, 
     * to display the appropriate order summary.
     *
     * @param int $cartId The ID of the cart for which the order was placed.
     * @param int $orderId The ID of the created order.
     * @param string $key The customer's secure key to ensure proper access.
     *
     * @return void
     */
    public function redirectToConfirmation($cartId, $orderId, $key)
    {
        return
            Tools::redirect(
                $this->context->link->getPageLink(
                    'order-confirmation',
                    true,
                    $this->context->language->id,
                    [
                        'id_cart' => $cartId,
                        'id_order' => $orderId,
                        'id_module' => $this->module->id,
                        'key' => $key
                    ]
                )
            );
    }
}
