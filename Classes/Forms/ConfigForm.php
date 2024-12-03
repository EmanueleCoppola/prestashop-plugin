<?php

namespace Satispay\Prestashop\Classes\Forms;

// Prestashop
use \Configuration;
use \Tools;

//
use \Exception;
use PrestaShopLogger;
use \Satispay;
use Satispay\Prestashop\Classes\CallbackHealthCheck;
use SatispayGBusiness\Api;
use SatispayGBusiness\Payment;

class ConfigForm extends Form
{
    /**
     * @inheritdoc
     */
    public function name()
    {
        return 'satispay-config-form';
    }

    /**
     * @inheritdoc
     */
    public function fields()
    {
        $smarty = $this->context->smarty;

        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Satispay settings'),
                    'icon' => 'icon-cogs'
                ],
                'success' => $this->success,
                'error' => $this->error,
                'input' => [
                    [
                        'col' => 3,
                        'type' => 'text',
                        'label' => $this->l('Activation Code'),
                        'name' => $this->field('activation-code'),
                        'desc' => sprintf($this->l('Get a six characters activation code from the Online Shop section on %sSatispay Dashboard%s.'), '<a href="https://dashboard.satispay.com/signup" target="_blank">', '</a>'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'switch',
                        'label' => $this->l('Sandbox Mode'),
                        'name' => $this->field('sandbox'),
                        'is_bool' => true,
                        'desc' => sprintf($this->l('Sandbox Mode can be used to test the module.') . '<br />' . $this->l('Request a Sandbox Account %shere%s.'), '<a href="https://developers.satispay.com/docs/credentials#sandbox-account" target="_blank">', '</a>'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            ]
                        ]
                    ],
                    [
                        'col' => 3,
                        'type' => 'html',
                        'label' => $this->l('Callback Health Check'),
                        'name' => $this->field('callback-health-check'),
                        'html_content' => 
                            html_entity_decode( // sorry to be that brutal, not my fault :/
                                $smarty
                                    ->assign([
                                        // statuses = todo | pending | success | failed
                                        'status' => Configuration::get(Satispay::SATIPAY_CALLBACK_HEALTH_STATUS) ?? CallbackHealthCheck::STATUS_TODO
                                    ])
                                    ->fetch('module:satispay/views/admin/templates/callback-health-check-field.tpl')
                            )
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'label' => $this->l('Payment Duration (in minutes)'),
                        'name' => $this->field('payment-duration-minutes'),
                        'desc' => $this->l('Specify the duration (in minutes) within which the payment must be completed after its creation. The miminum amount is 1 minute.'),
                    ]
                ],
                'submit' => [
                    'title' => $this->l('Save')
                ]
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function values()
    {
        return [
            $this->field('activation-code') => Configuration::get(Satispay::SATISPAY_ACTIVATION_CODE),
            $this->field('sandbox') => Configuration::get(Satispay::SATISPAY_SANDBOX),
            $this->field('callback') => null,
            $this->field('payment-duration-minutes') => Configuration::get(Satispay::SATISPAY_PAYMENT_DURATION_MINUTES) ?? '60'
        ];
    }

    /**
     * @inheritdoc
     */
    public function render()
    {
        $sandbox = Configuration::get(Satispay::SATISPAY_SANDBOX) ?? false;

        //
        $activationCode = Configuration::get(Satispay::SATISPAY_ACTIVATION_CODE);

        //
        $this->module->callbackHealthCheck->verify();

        if ($activationCode) {
            try {
                Payment::all();
            } catch (Exception $e) {
                $this->module->log(get_class($this) . "@render exception {$e->getMessage()}", PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR);

                $this->error = sprintf($this->l('Satispay is not correctly configured, get an Activation Code from Online Shop section on %sSatispay Dashboard%s.'), '<a href="https://dashboard.satispay.com/signup" target="_blank">', '</a>').'<br />';
            }
        }

        return parent::render();
    }

    /**
     * @inheritdoc
     */
    protected function process()
    {
        $oldSandbox = (bool) Configuration::get(Satispay::SATISPAY_SANDBOX);
        $newSandbox = Tools::getValue($this->field('sandbox')) === '1';

        // TODO: Swap with the new SDK
        Api::setSandbox($newSandbox);

        $oldActivationCode = Configuration::get(Satispay::SATISPAY_ACTIVATION_CODE, '');
        $newActivationCode = Tools::getValue($this->field('activation-code'));

        Configuration::updateValue(
            Satispay::SATISPAY_PAYMENT_DURATION_MINUTES,
            max(
                1,
                (int) Tools::getValue($this->field('payment-duration-minutes'))
            )
        );

        if (
            $oldSandbox !== $newSandbox &&
            $oldActivationCode !== $newActivationCode
        ) {
            // use the activation code
            $activated = false;

            try {
                $authentication = Api::authenticateWithToken($newActivationCode);

                Configuration::updateValue(Satispay::SATISPAY_SANDBOX, $newSandbox);

                Configuration::updateValue(Satispay::SATISPAY_ACTIVATION_CODE, $newActivationCode);
                Configuration::updateValue(Satispay::SATISPAY_KEY_ID, $authentication->keyId);
                Configuration::updateValue(Satispay::SATISPAY_PRIVATE_KEY, $authentication->privateKey);
                Configuration::updateValue(Satispay::SATISPAY_PUBLIC_KEY, $authentication->publicKey);

                $this->module->bootSatispayClient();

                $this->module->log(get_class($this) . "@process activation succedeed with code {$newActivationCode}");
                $this->success = $this->l('Activation successful.');

                $activated = true;
            } catch (Exception $e) {
                $this->module->bootSatispayClient();

                $this->module->log(get_class($this) . "@process activation failed {$e->getMessage()}", PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR);
                $this
                    ->error =
                        sprintf(
                            $this->l('The Activation Code "%s" is invalid. Please verify that it belongs to the %s environment and it\'s not beign used before.'),
                            $newActivationCode,
                            $newSandbox ? $this->l('sandbox') : $this->l('production')
                        );
            }

            // trigger callback health-check
            if (!$activated) {
                return;
            }

            $this->module->callbackHealthCheck->run();
        } else {
            $this->success = $this->l('Settings saved succesfully.');
        }
    }
}
