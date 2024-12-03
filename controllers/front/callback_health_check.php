<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

use \Satispay;

/**
 * Class SatispayCallback_Health_CheckModuleFrontController
 *
 * Handles the health check callback for the Satispay module.
 */
class SatispayCallback_Health_CheckModuleFrontController extends ModuleFrontController
{
    /**
     * Override the default maintenance mode behavior to allow Satispay
     * activation even when the shop is in maintenance mode.
     *
     * Used for admin testing only.
     */
    protected function displayMaintenancePage() {}

    /**
     * Register the HTTP callback by the Satispay servers.
     * 
     * @see https://developers.satispay.com/reference/callback-s2s
     */
    public function initContent()
    {
        /** @var Satispay $satispay */
        $satispay = $this->module;

        $satispay->callbackHealthCheck->validate(
            Tools::getValue('nonce')
        );

        header('HTTP/1.1 204 No Content');
    }
}
