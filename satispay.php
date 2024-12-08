<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Prestashop
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

//
use SatispayGBusiness\Api;
use Satispay\Prestashop\Classes\CallbackHealthCheck;
use Satispay\Prestashop\Classes\Forms\ConfigForm;
use Satispay\Prestashop\Classes\Models\SatispayRefund;

class Satispay extends PaymentModule
{
    /**
     * Allowed currencies for Satispay payment processing.
     *
     * This constant defines the list of currency codes that are supported
     * by the Satispay payment module.
     *
     * @var array<string> List of ISO 4217 currency codes.
     */
    const ALLOWED_CURRENCIES = [
        'EUR'
    ];

    /**
     * Configuration constants.
     *
     * @var string The configuration key.
     */

    // health check
    const SATIPAY_CALLBACK_HEALTH_NONCE = 'SATISPAY_CALLBACK_TEST_NONCE';
    const SATIPAY_CALLBACK_HEALTH_STATUS = 'SATISPAY_CALLBACK_TEST_STATUS';
    const SATIPAY_CALLBACK_HEALTH_TIMESTAMP = 'SATISPAY_CALLBACK_TEST_TIMESTAMP';

    // authentication
    const SATISPAY_ACTIVATION_CODE = 'SATISPAY_ACTIVATION_CODE';
    const SATISPAY_PRIVATE_KEY = 'SATISPAY_PRIVATE_KEY';
    const SATISPAY_PUBLIC_KEY  = 'SATISPAY_PUBLIC_KEY';
    const SATISPAY_KEY_ID      = 'SATISPAY_KEY_ID';

    // TODO: implement the payment duration
    const SATISPAY_PAYMENT_DURATION_MINUTES = 'SATISPAY_PAYMENT_DURATION_MINUTES';
    const SATISPAY_PAYMENT_DURATION_MINUTES_DEFAULT = 60;

    // settings
    const SATISPAY_SANDBOX = 'SATISPAY_SANDBOX';

    // custom hooks
    const SATISPAY_MEAL_VOUCHER_AMOUNT_HOOK = 'actionSatispayMealVoucherAmount';

    // --
    const SATISPAY_LOCKING_TIMEOUT_DURATION_SECONDS = 8;

    /**
     * The CallbackHealthCheck instance.
     * 
     * @var CallbackHealthCheck
     */
    public $callbackHealthCheck;

    /**
     * Satispay class constructor.
     */
    public function __construct()
    {
        $this->name = 'satispay'; // do not change this ever

        $this->tab = 'payments_gateways';
        $this->version = '3.0.0';
        $this->author = 'Satispay';
        $this->need_instance = 1;
        $this->module_key = '812ed8ea2509dd2146ef979a6af24ee5';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Satispay');
        $this->description = $this->l('Save time and money by accepting payments from your customers with Satispay. Simple, fast and secure!');
        
        $this->ps_versions_compliancy = [
            'min' => '1.7',
            'max' => _PS_VERSION_
        ];

        //
        $this->callbackHealthCheck = new CallbackHealthCheck($this);

        $this->bootSatispayClient();
    }

    /**
     * Load the Satispay configuration for the SDK.
     * 
     * This method must be called every time there
     * is a change in configuration.
     * 
     * @return void
     */
    public function bootSatispayClient()
    {
        $sandbox = (bool) Configuration::get(Satispay::SATISPAY_SANDBOX, false);

        $keyId = Configuration::get(Satispay::SATISPAY_KEY_ID);
        $privateKey = Configuration::get(Satispay::SATISPAY_PRIVATE_KEY);
        $publicKey = Configuration::get(Satispay::SATISPAY_PUBLIC_KEY);

        // TODO: Swap with the new SDK
        Api::setSandbox($sandbox);

        if ($keyId && $privateKey && $publicKey) {
            Api::setKeyId($keyId);
            Api::setPrivateKey($privateKey);
            Api::setPublicKey($publicKey);
        }
        
        Api::setPluginNameHeader('PrestaShop');
        Api::setPluginVersionHeader($this->version);
        Api::setPlatformVersionHeader(_PS_VERSION_);
        Api::setTypeHeader('ECOMMERCE-PLUGIN');
    }

    /**
     * Install the module.
     *
     * @return bool Returns true if the installation was successful, false otherwise.
     */
    public function install()
    {
        return parent::install() && 
            $this->installDb() &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('actionAdminControllerSetMedia') &&
            $this->registerHook(self::SATISPAY_MEAL_VOUCHER_AMOUNT_HOOK) &&
            $this->registerHook('displayAdminOrderMainBottom');
    }

    /**
     * Installs the database tables required for the Satispay module.
     *
     * @return bool Returns true on success, or false if any query fails.
     */
    public function installDb()
    {
        $queries = [
            "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "satispay_pending_payments` (
              `id` INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
              `cart_id` INT(11),
              `payment_id` VARCHAR(255),
              `reference` VARCHAR(255),
              `amount_unit` INT(11) UNSIGNED,
              `date_add` DATETIME,
              `date_upd` DATETIME,
              INDEX (`cart_id`)
            ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;",
            "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "satispay_locks` (
                `name` VARCHAR(255) NOT NULL,
                `expires_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`name`)
            ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;",
            "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "satispay_refunds` (
                `id` INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `refund_id` VARCHAR(255) NOT NULL,
                `payment_id` VARCHAR(255) NOT NULL,
                `amount_unit` INT(11) UNSIGNED,
                `date_add` DATETIME,
                INDEX (`payment_id`)
            ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;"
        ];

        foreach($queries as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Uninstall the module.
     *
     * @return bool Returns true if the uninstallation was successful, false otherwise.
     */
    public function uninstall()
    {
        $settings = [
            Satispay::SATIPAY_CALLBACK_HEALTH_NONCE,
            Satispay::SATIPAY_CALLBACK_HEALTH_STATUS,
            Satispay::SATIPAY_CALLBACK_HEALTH_TIMESTAMP,

            // authentication
            Satispay::SATISPAY_ACTIVATION_CODE,
            Satispay::SATISPAY_PRIVATE_KEY,
            Satispay::SATISPAY_PUBLIC_KEY,
            Satispay::SATISPAY_KEY_ID,

            // settings
            Satispay::SATISPAY_SANDBOX,
            Satispay::SATISPAY_PAYMENT_DURATION_MINUTES
        ];

        foreach($settings as $setting) {
            Configuration::deleteByName($setting);
        }

        $queries = [
            "DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "satispay_locks`;"
        ];

        foreach($queries as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return parent::uninstall();
    }

    /**
     * Build the configuration form using the HelperForm class.
     *
     * This method is responsible for creating and configuring the HelperForm instance
     * that will be used to display the configuration options for the Satispay module
     * in the back office. The configuration of the HelperForm instance within this 
     * method is required due to the variable scope, ensuring that all settings are
     * appropriately encapsulated and available within the context of rendering the form.
     *
     * @return HelperForm The configured HelperForm instance, ready to be rendered.
     */
    protected function buildConfigForm()
    {
        $configForm = new HelperForm();

        $configForm->module = $this;
        $configForm->default_form_language = $this->context->language->id;

        $configForm->identifier = $this->identifier;
        $configForm->currentIndex = $this->context->link->getAdminLink(
            'AdminModules',
            false,
            [],
            [
                'configure' => $this->name,
                'tab_module' => $this->tab,
                'module_name' => $this->name
            ]
        );
        $configForm->token = Tools::getAdminTokenLite('AdminModules');

        $configForm->tpl_vars = [
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        ];

        return $configForm;
    }

    /**
     * Load the configuration form.
     *
     * @return string The HTML form content.
     */
    public function getContent()
    {
        $forms = [
            new ConfigForm($this->buildConfigForm(), $this)
        ];

        $content = '';

        foreach ($forms as $form) {
            $form->verify();

            $content .= $form->render();
        }

        return $content;
    }

    /**
     * Hook to provide payment options during the checkout process.
     *
     * @param array $params Parameters from the context, including cart information.
     * 
     * @return array|false Array of payment options or false if the currency is not supported.
     */
    public function hookPaymentOptions($params)
    {
        // if the module is not properly configured
        // we avoid querying for currency infos
        if (
            !$this->active ||
            !(
                Configuration::get(self::SATISPAY_ACTIVATION_CODE) &&
                Configuration::get(self::SATISPAY_PRIVATE_KEY) &&
                Configuration::get(self::SATISPAY_PUBLIC_KEY) &&
                Configuration::get(self::SATISPAY_KEY_ID)
            )
        ) {
            return false;
        }

        $currency = new Currency((int) $params['cart']->id_currency);

        if (!in_array($currency->iso_code, self::ALLOWED_CURRENCIES)) {
            return false;
        }

        return [
            (new PaymentOption())
                ->setCallToActionText($this->l('Pay with Satispay'))
                ->setAction($this->context->link->getModuleLink($this->name, 'payment', [], true))
                ->setLogo(
                    Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/satispay-payment-logo.png')
                )
        ];
    }
    
    /**
     * Add assets in Satispay config page.
     *
     * @param array $params
     *
     * @return void
     */
    public function hookActionAdminControllerSetMedia(array $params)
    {
        $controller = Tools::getValue('controller');
        $configure = Tools::getValue('configure');
    
        if ($controller === 'AdminModules' && $configure === $this->name) {
            /** @var ControllerCore $controller */
            $controller = $this->context->controller;

            $controller->addJs($this->getPathUri() . "views/js/admin/{$this->name}.js");
            $controller->addCSS($this->getPathUri() . "views/css/admin/{$this->name}.css");
        }
    }

    /**
     * Hook that allows to limit the Satispay Meal Voucher amount on a specific cart.
     * Just return the Satispay Meal Voucher max amount in cents.
     * You can filter the cart for food products using your criteria.
     *
     * @param array $params
     *
     * @return int Satispay Meal Voucher max amount.
     */
    public function hookActionSatispayMealVoucherAmount($params)
    {
        // available params
        // $cart = $params['cart'];
        // $amount = $params['amount'];

        return $params['amount'];
    }

    /**
     * This hook allows to show the refund form in the order page.
     *
     * @param array $params
     *
     * @return string The HTML template.
     */
    public function hookDisplayAdminOrderMainBottom($params)
    {
        $order = new Order($params['id_order']);

        if ($order->module !== $this->name) {
            return;
        }

        $orderPayment = $order->getOrderPayments();

        if (count($orderPayment) === 0) return;

        /** @var OrderPayment $orderPayment */
        $orderPayment = $orderPayment[0];

        if ($this->name !== $order->module) return;

        $currency = new Currency($order->id_currency);

        $satispayRefunds = SatispayRefund::getByPaymentId($orderPayment->transaction_id);

        $this->context->smarty->assign([
            'order' => $order,
            'order_refunded' => $order->current_state === (int) Configuration::get('PS_OS_REFUND'),

            'refunds' => $satispayRefunds,
            'currency' => $currency,

            'error_message' => $this->flash('refund-error-message'),
            'success_message' => $this->flash('refund-success-message'),

            'satispay_refund_url' => $this->context->link->getAdminLink('AdminSatispayRefund'),
        ]);

        return $this->display(__FILE__, 'views/templates/admin/hook/adminOrderTab.tpl');
    }

    /**
     * Sets or retrieves a flash message value from the cookie.
     *
     * If a value is provided, it will store the value in the cookie under a
     * key combining the module name and the provided name. If no value is provided,
     * it will retrieve and then remove the value from the cookie.
     *
     * @param string $name The key name for the flash message.
     * @param mixed|null $value The value to set for the flash message. If null, the method retrieves and deletes the message.
     *
     * @return mixed|null The flash message value or null if not set.
     */
    public function flash($name, $value = null)
    {
        $name = $this->name . '_' . $name;

        if ($value !== null) {
            $this->context->cookie->{$name} = $value;
        } else {
            $value = isset($this->context->cookie->{$name}) ? $this->context->cookie->{$name} : null;

            unset($this->context->cookie->{$name});
        }

        $this->context->cookie->write();

        return $value;
    }

    /**
     * Log using the PS logging class.
     * The logging configuration will depend on the PS one.
     * 
     * @param string $message the log message
     * @param int $severity
     * 
     * @return void
     */
    public function log($message, $severity = PrestaShopLogger::LOG_SEVERITY_LEVEL_INFORMATIVE)
    {
        PrestaShopLogger::addLog('[Satispay] ' . $message, $severity);
    }
}
