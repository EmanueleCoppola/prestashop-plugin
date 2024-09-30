<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

// Prestashop
use \Configuration;

//
use \Satispay;

/**
 * Class SatispayCallbackHealthModuleFrontController
 *
 * Handles the health check callback for the Satispay module.
 */
class SatispayCallback_HealthModuleFrontController extends ModuleFrontController
{
    /**
     * Register the HTTP callback by the Satispay servers.
     * 
     * @see https://developers.satispay.com/reference/callback-s2s
     */
    public function initContent()
    {
        $nonce = Configuration::get(Satispay::SATIPAY_CALLBACK_HEALTH_NONCE);

        if ($nonce && Tools::getValue('nonce') === $nonce) {
            Configuration::updateValue(Satispay::SATIPAY_CALLBACK_HEALTH_STATUS, 'success');
        }

        header('HTTP/1.1 204 No Content');

        exit;
    }
}
