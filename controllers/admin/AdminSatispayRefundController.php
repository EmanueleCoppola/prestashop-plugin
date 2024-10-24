<?php

use Satispay\Prestashop\Classes\Models\SatispayRefund;
use SatispayGBusiness\Payment;

/**
 * Class AdminSatispayController
 * 
 * @property Satispay $module
 */
class AdminSatispayRefundController extends ModuleAdminController
{
    /**
     * AdminSatispayController constructor.
     */
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    public function initContent()
    {
        parent::initContent();
        
        $orderId = (int) Tools::getValue('satispay_refund_id_order');
        
        // process amount
        $refundAmount = Tools::getValue('satispay_refund_amount');
        $refundAmount = str_replace(',', '.', $refundAmount);
        $refundAmount = floatval($refundAmount) * 100;
        $refundAmount = intval($refundAmount);

        // process the refund
        $this->refund($orderId, $refundAmount);

        return 
            Tools::redirectAdmin(
                $this
                    ->context
                    ->link
                    ->getAdminLink(
                        'AdminOrders',
                        true,
                        [],
                        [
                            'vieworder' => 1,
                            'id_order' => $orderId
                        ]
                    ) . '#view_satispay_refunds'
        );
    }

    /**
     * Processes a refund for a specific order.
     *
     * This method handles the refund of a specified amount for a given order.
     * It checks if the refund action was initiated, retrieves the order payments,
     * validates the refund amount, and processes the refund via Satispay if allowed.
     *
     * @param int $orderId The ID of the order to refund.
     * @param int $amount The amount to be refunded, in cents.
     * @return void
     */
    public function refund($orderId, $refundAmount)
    {
        if (!Tools::isSubmit('satispay_refund')) return;

        $order = new Order($orderId);

        $orderPayment = $order->getOrderPayments();

        if (count($orderPayment) === 0) return;
        
        /** @var OrderPayment $orderPayment */
        $orderPayment = $orderPayment[0];

        $satispayPayment = null;

        try {
            $satispayPayment = Payment::get($orderPayment->transaction_id);
        } catch (Exception) {
            $this->module->flash(
                'refund-error-message',
                $this->module->l('An error occurred while processing the refund. Please try again later.')
            );

            return;
        }
        
        $satispayRefunds = SatispayRefund::getByPaymentId($orderPayment->transaction_id);

        // compute refunded and refundable
        $refundedAmount = 0;

        foreach ($satispayRefunds as $satispayRefund) {
            $refundedAmount += $satispayRefund->amount_unit;
        }

        $refundableAmount = $satispayPayment->amount_unit - $refundedAmount;

        if ($refundableAmount === 0) {
            $order
                ->setCurrentState(
                    (int) Configuration::get('PS_OS_REFUND')
                );

            $this->module->flash(
                'refund-error-message',
                $this->module->l('The order has already been refunded.')
            );

            return;
        } else if ($refundAmount > $refundableAmount) {
            $this->module->flash(
                'refund-error-message',
                sprintf(
                    $this->module->l('The maximum amount that you can refund for this payment is of %s'),
                    $this->formatPrice($refundableAmount)
                )
            );

            return;
        }

        try {
            $satispayRefund = Payment::create([
                'flow' => 'REFUND',
                'parent_payment_uid' => $orderPayment->transaction_id,
                'external_code' => $order->reference,
                'amount_unit' => $refundAmount
            ]);

            if ($satispayRefund->status === 'ACCEPTED') {
                // refunded and refundable recalculation
                $refundedAmount += $refundAmount;
                $refundableAmount = $satispayPayment->amount_unit - $refundedAmount;

                $satispayRefundModel = new SatispayRefund();
                $satispayRefundModel
                    ->hydrate([
                        'refund_id' => $satispayRefund->id,
                        'payment_id' => $orderPayment->transaction_id,
                        'amount_unit' => $refundAmount
                    ]);

                if ($satispayRefundModel->save()) {
                    if ($refundableAmount === 0) {
                        $order
                            ->setCurrentState(
                                (int) Configuration::get('PS_OS_REFUND')
                            );
                    }
                    
                    $this->module->flash(
                        'refund-success-message',
                        sprintf($this->module->l('A refund of %s has been correctly processed.'), $this->formatPrice($refundAmount)),
                    );
                }
            }
        } catch (Exception) {
            $this->module->flash(
                'refund-error-message',
                $this->module->l('An error occurred while processing the refund. Please try again later.')
            );
        }
    }

    /**
     * Formats a price amount into a locale-specific string representation.
     *
     * This method takes an amount in cents, divides it by 100 to convert it to the base currency unit,
     * and formats it according to the current locale and currency ISO code.
     *
     * @param int $amount The price amount in cents (can be a float or an integer).
     * 
     * @return string The formatted price string.
     */
    protected function formatPrice($amount)
    {
        return
            $this->context->currentLocale->formatPrice(
                $amount / 100,
                $this->context->currency->iso_code
            );
    }
}
