<?php

namespace Satispay\Prestashop\Upgrade;

// Prestashop
use \Configuration;
use \OrderState;
use \Validate;

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_3_0_0($module) {
    /** @var Satispay $module */

    // remove Satispay order state
    $orderStateId = (int) Configuration::get('SATISPAY_PENDING_STATE');

    if ($orderStateId) {
        $orderState = new OrderState($orderStateId);

        if (Validate::isLoadedObject($orderState)) {
            return $orderState->delete() && Configuration::deleteByName('SATISPAY_PENDING_STATE');
        }
    }

    return true;
}
