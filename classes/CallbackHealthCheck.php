<?php

namespace Satispay\Prestashop\Classes;

if (!defined('_PS_VERSION_')) {
    exit;
}

// Prestashop
use \Configuration;
use \Context;
use \PrestaShopLogger;

//
use Carbon\Carbon;
use \Satispay;
use \Exception;
use SatispayGBusiness\Payment;

/**
 * Manages the health check process for Satispay callbacks in Prestashop.
 *
 * This class handles the creation, verification, and validation of callback
 * health checks. It ensures that callbacks are functioning correctly by
 * creating test payments and verifying their responses within a specified
 * time frame.
 */
class CallbackHealthCheck
{
    /**
     * CallbackHealthCheck constants.
     *
     * If we don't receive an update within X minutes
     * we consider the callback health failed.
     * That's why we need these constants.
     *
     * @var string
     */
    const PAYMENT_DURATION_SECONDS = 60;

    // statuses
    const STATUS_TODO = 'todo';
    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAIL = 'fail';

    /**
     * The Satispay module.
     *
     * @var Satispay
     */
    protected $module;

    /**
     * The Satispay module.
     *
     * @var Context
     */
    protected $context;

    /**
     * The CallbackHealthCheck constructor.
     */
    public function __construct($module)
    {
        /** @var Satispay $module */
        $this->module = $module;

        $this->context = Context::getContext();
    }

    /**
     * Run the health check process for the Satispay callback.
     *
     * Generates a unique nonce, creates a payment to test the callback,
     * and sets the status to pending. The callback URL contains the
     * generated nonce and a payment ID.
     *
     * @return void
     */
    public function run()
    {
        $nonce = substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', 5)), 0, 12);
        Configuration::updateValue(Satispay::SATIPAY_CALLBACK_HEALTH_NONCE, $nonce);

        $this->module->log(get_class($this) . "@run starting with nonce {$nonce}");

        try {
            Payment::create([
                'flow' => 'MATCH_CODE',
                'currency' => 'EUR',
                'amount_unit' => 1,
                'external_code' => "Prestashop callback-health {$nonce}",
                'callback_url' =>
                    $this
                        ->context
                        ->link
                        ->getModuleLink(
                            $this->module->name,
                            'callback_health',
                            [
                                'nonce' => $nonce,
                                'payment_id' => '{uuid}',
                            ],
                            true
                        ),
                'expiration_date' =>
                    Carbon::now()
                        ->timezone('UTC')
                        ->addSeconds(self::PAYMENT_DURATION_SECONDS)
                        ->format("Y-m-d\TH:i:s.v\Z")
            ]);

            Configuration::updateValue(Satispay::SATIPAY_CALLBACK_HEALTH_STATUS, self::STATUS_PENDING);

            Configuration::updateValue(
                Satispay::SATIPAY_CALLBACK_HEALTH_TIMESTAMP,
                Carbon::now()
                    ->timezone('UTC')
                    ->getPreciseTimestamp(3)
            );

            $this->module->log(get_class($this) . "@run succeded with nonce {$nonce}");
        } catch (Exception) {
            // can be silent as it doesn't affect the module
            $this->module->log(get_class($this) . "@run failed with nonce {$nonce}", PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR);
        }
    }

    /**
     * Verify the callback health status.
     *
     * Checks if the payment callback response was received within
     * the expected time frame. If the response is overdue, sets the
     * status to failed and clears the timestamp.
     *
     * @return void
     */
    public function verify()
    {
        $timestamp = Configuration::get(Satispay::SATIPAY_CALLBACK_HEALTH_TIMESTAMP);

        $this->module->log(get_class($this) . "@verify with timestamp {$timestamp}");

        if (!$timestamp) {
            return;
        }

        $timestamp = Carbon::createFromTimestampMs($timestamp);

        if (
            $timestamp
                ->addSeconds(
                    // 10 more seconds are more than enough
                    self::PAYMENT_DURATION_SECONDS + 10
                )
                ->lessThan(
                    Carbon::now()->timezone('UTC')
                )
        ) {
            Configuration::updateValue(Satispay::SATIPAY_CALLBACK_HEALTH_NONCE, null);
            Configuration::updateValue(Satispay::SATIPAY_CALLBACK_HEALTH_STATUS, self::STATUS_FAIL);
            Configuration::updateValue(Satispay::SATIPAY_CALLBACK_HEALTH_TIMESTAMP, null);
        }
    }

    /**
     * Validate the callback using a nonce.
     *
     * Compares the provided nonce with the stored nonce. If they match,
     * updates the callback health status to success.
     *
     * @param string $nonce The nonce received in the callback.
     *
     * @return void
     */
    public function validate($nonce)
    {
        $this->module->log(get_class($this) . "@validate with nonce {$nonce}");

        $_nonce = Configuration::get(Satispay::SATIPAY_CALLBACK_HEALTH_NONCE);

        if ($_nonce && $nonce === $_nonce) {
            Configuration::updateValue(Satispay::SATIPAY_CALLBACK_HEALTH_NONCE, null);
            Configuration::updateValue(Satispay::SATIPAY_CALLBACK_HEALTH_STATUS, self::STATUS_SUCCESS);
            Configuration::updateValue(Satispay::SATIPAY_CALLBACK_HEALTH_TIMESTAMP, null);
        }
    }
}
