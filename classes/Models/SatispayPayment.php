<?php

namespace Satispay\Prestashop\Classes\Models;

if (!defined('_PS_VERSION_')) {
    exit;
}

// Prestashop
use \Db;
use \DbQuery;
use \ObjectModel;

/**
 * Class SatispayPayment.
 */
class SatispayPayment extends ObjectModel
{
    /**
     * @var int Primary key of the SatispayPayment model.
     */
    public $id;

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
     * @var int The order amount.
     */
    public $amount;

    /**
     * @var string Date and time when the payment object was created.
     */
    public $date_add;

    /**
     * @var string Date and time when the payment object was last modified.
     */
    public $date_upd;

    /**
     * Definition of the SatispayPayment entity.
     * 
     * @see ObjectModel::$definition
     * 
     * @var array
     */
    public static $definition = [
        'table' => 'satispay_payments',
        'primary' => 'id',
        'multilang' => false,
        'fields' => [
            'cart_id' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'payment_id' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'reference' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'amount' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat'],
        ],
        'collation' => 'utf8_general_ci',
    ];

    /**
     * Retrieves a SatispayPayment object by the Satispay payment id.
     *
     * @param string $payment_id The Satispay payment id
     * 
     * @return SatispayPayment|null The corresponding SatispayPayment object or null if not found
     */
    public static function getByPaymentId($payment_id)
    {
        $query = new DbQuery();

        $query
            ->select('*')
            ->from(self::$definition['table'])
            ->where('transaction_id = ' . pSQL($payment_id));

        return 
            (new self())
                ->hydrate(
                    Db::getInstance()->getRow($query)
                );
    }

    /**
     * Retrieves a SatispayPayment object by the cart id.
     *
     * @param int $cart_id The PrestaShop cart id
     * 
     * @return SatispayPayment|null The corresponding SatispayPayment object or null if not found
     */
    public static function getByCartId($cart_id)
    {
        $query = new DbQuery();

        $query
            ->select('id')
            ->from(self::$definition['table'])
            ->where('cart_id = ' . pSQL($cart_id));

        return 
            (new self())
                ->hydrate(
                    Db::getInstance()->getRow($query)
                );
    }
}
