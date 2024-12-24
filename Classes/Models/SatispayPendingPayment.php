<?php

namespace Satispay\Prestashop\Classes\Models;

if (!defined('_PS_VERSION_')) {
    exit;
}

// Prestashop
use \Cart;
use \Configuration;
use \Customer;
use \Db;
use \DbQuery;
use \ObjectModel;
use \Order;
use \Validate;

/**
 * Class SatispayPendingPayment.
 */
class SatispayPendingPayment extends ObjectModel
{
    /**
     * @var int Primary key of the SatispayPendingPayment model.
     */
    public $id;

    /**
     * @var int|null The order id associated with the payment.
     */
    public $order_id;

    /**
     * @var int The cart id associated with the payment.
     */
    public $cart_id;

    /**
     * @var string The Satispay payment id.
     */
    public $payment_id;

    /**
     * @var string The order reference.
     */
    public $reference;

    /**
     * @var int The order amount in cents.
     */
    public $amount_unit;

    /**
     * @var string Date and time when the payment object was created.
     */
    public $date_add;

    /**
     * @var string Date and time when the payment object was last modified.
     */
    public $date_upd;

    /**
     * Definition of the SatispayPendingPayment entity.
     * 
     * @see ObjectModel::$definition
     * 
     * @var array
     */
    public static $definition = [
        'table' => 'satispay_pending_payments',
        'primary' => 'id',
        'multilang' => false,
        'fields' => [
            'order_id' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'cart_id' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'payment_id' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'reference' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'amount_unit' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat'],
        ],
        'collation' => 'utf8_general_ci',
    ];

    /**
     * Retrieves a SatispayPendingPayment object by the Satispay payment id.
     *
     * @param string $payment_id The Satispay payment id
     * 
     * @return SatispayPendingPayment|null The corresponding SatispayPendingPayment object or null if not found
     */
    public static function getByPaymentId($payment_id)
    {
        $query = new DbQuery();

        $query
            ->select('*')
            ->from(self::$definition['table'])
            ->where("payment_id = '" . pSQL($payment_id) . "'");

        $result = Db::getInstance()->getRow($query);

        if ($result) {
            $satispayPendingPayment = new self();
            $satispayPendingPayment->hydrate($result);

            return $satispayPendingPayment;
        }

        return null;
    }

    /**
     * Retrieves a SatispayPendingPayment object by the cart id.
     *
     * @param int $cart_id The PrestaShop cart id
     * 
     * @return SatispayPendingPayment|null The corresponding SatispayPendingPayment object or null if not found
     */
    public static function getByCartId($cart_id)
    {
        $query = new DbQuery();

        $query
            ->select('id')
            ->from(self::$definition['table'])
            ->where('cart_id = ' . pSQL($cart_id));
        
        $result = Db::getInstance()->getRow($query);

        if ($result) {
            $satispayPendingPayment = new self();
            $satispayPendingPayment->hydrate($result);

            return $satispayPendingPayment;
        }

        return null;
    }

    /**
     * Accepts and validates the Satispay payment and creates an order.
     *
     * This method verifies the payment details against the cart, validates the order, and updates 
     * the PrestaShop system with the new order information. If successful, it deletes the pending
     * payment record and returns the created Order object.
     *
     * @param int $amountUnit The Satispay payment amount_unit.
     * @param Satispay $module The Satispay payment module.
     *
     * @return Order|void Returns the created Order object or void if validation fails.
     */
    public function acceptOrder($amountUnit, $module)
    {
        $amountUnit = (int) $amountUnit;

        $cart = new Cart((int) $this->cart_id);
        $cartAmountUnit = (int) round($cart->getOrderTotal(true, Cart::BOTH) * 100);

        $customer = new Customer((int) $cart->id_customer);

        if (
            // stop if we don't have a cart and a customer associated
            !(Validate::isLoadedObject($cart) && Validate::isLoadedObject($customer)) ||

            // stop if the amount unit paid is different from the one in the cart
            !(
                $amountUnit === $cartAmountUnit &&
                $cartAmountUnit === $this->amount_unit
            )
        ) {
            return;
        }

        $validated = $module->validateOrder(
            (int) $cart->id,
            (int) Configuration::get('PS_OS_PAYMENT'),
            $this->amount_unit / 100,
            $module->displayName,
            null,
            [
                'transaction_id' => $this->payment_id
            ],
            (int) $cart->id_currency,
            false,
            $customer->secure_key,
            null,
            $this->reference
        );

        if ($validated) {
            $order = Order::getByCartId($cart->id);

            $this->order_id = $order->id;
            $this->save();

            return $order;
        }
    }
}
