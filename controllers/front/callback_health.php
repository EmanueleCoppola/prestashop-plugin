<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

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
        /** @var Satispay $satispay */
        $satispay = $this->module;

        $satispay->callbackHealth->validate(
            Tools::getValue('nonce')
        );

        header('HTTP/1.1 204 No Content');

        exit;
    }
}
