<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

use SatispayGBusiness\Payment;
use Satispay\Prestashop\Classes\Models\SatispayPendingPayment;

/**
 * Class SatispayPaymentModuleFrontController
 *
 * This front controller handles the Satispay payment process in PrestaShop.
 * It initiates a payment request using Satispay's API, creates a payment record,
 * and redirects the user to complete the payment.
 *
 * @property Satispay $module
 */
class SatispayPaymentModuleFrontController extends ModuleFrontController
{
    /**
     * Handles the Satispay payment initiation.
     *
     * This method creates a payment request with Satispay, stores the transaction details,
     * and redirects the user to complete the payment. In case of an error, it redirects the user to the cart page.
     *
     * @return void A redirect to the Satispay payment page
     */
    public function postProcess()
    {
        $cart     = $this->context->cart;
        $currency = $this->context->currency;

        $amountUnit = round($cart->getOrderTotal(true, Cart::BOTH) * 100);
        $reference = Order::generateReference();

        $satispayPendingPayment = new SatispayPendingPayment();
        $satispayPendingPayment
            ->hydrate([
                'cart_id' => $cart->id,
                'reference' => $reference,
                'amount_unit' => $amountUnit
            ]);
        $satispayPendingPayment->save();

        try {
            $payment = Payment::create([
                'flow' => 'MATCH_CODE',
                'amount_unit' => $amountUnit,
                'currency' => $currency->iso_code,
                'callback_url' => urldecode(
                    $this->context->link->getModuleLink(
                        $this->module->name,
                        'callback',
                        [
                            'payment_id' => '{uuid}',
                        ]
                    )
                ),
                'external_code' => $reference,
                'redirect_url' => urldecode(
                    $this->context->link->getModuleLink(
                        $this->module->name,
                        'redirect',
                        [
                            // spp => SatispayPendingPayment
                            'spp' => base64_encode($satispayPendingPayment->id)
                        ]
                    )
                ),
                'metadata' => [
                    'cart_id' => $cart->id,
                ]
            ]);

            $satispayPendingPayment
                ->hydrate([
                    'payment_id' => $payment->id
                ]);
            $satispayPendingPayment->save();

            return Tools::redirect($payment->redirect_url);
        } catch (Exception $e) {
            $satispayPendingPayment->delete();

            $this->module->log(
                get_class($this) . "@postProcess error while creating the payment \"{$e->getMessage()}\"",
                PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR
            );
        }

        $this->errors[] = $this->module->l('An error occurred while paying with Satispay. Please try again later.'); 

        return
            $this->redirectWithNotifications(
                $this->context->link->getPageLink('cart', true, $this->context->language->id)
            );
    }
}
