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
 * Class SatispayRefund.
 */
class SatispayRefund extends ObjectModel
{
    /**
     * @var string The Satispay refund id.
     */
    public $refund_id;

    /**
     * @var string The Satispay payment id where the refund belongs.
     */
    public $payment_id;

    /**
     * @var int The refund amount in cents.
     */
    public $amount_unit;

    /**
     * @var string Date and time when the payment object was created.
     */
    public $date_add;

    /**
     * Definition of the SatispayRefund entity.
     * 
     * @see ObjectModel::$definition
     * 
     * @var array
     */
    public static $definition = [
        'table' => 'satispay_refunds',
        'primary' => 'refund_id',
        'multilang' => false,
        'fields' => [
            'refund_id' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'payment_id' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'amount_unit' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat']
        ],
        'collation' => 'utf8_general_ci',
    ];

    /**
     * Retrieves a collection of SatispayRefund objects by the Satispay payment id.
     *
     * @param string $payment_id The Satispay payment id
     * 
     * @return SatispayRefund[] A collection of SatispayRefund objects
     */
    public static function getByPaymentId($payment_id)
    {
        $query = new DbQuery();

        $query
            ->select('*')
            ->from(self::$definition['table'])
            ->where("payment_id = '" . pSQL($payment_id) . "'");

        $results = Db::getInstance()->executeS($query);

        return ObjectModel::hydrateCollection(self::class, $results);
    }
}
