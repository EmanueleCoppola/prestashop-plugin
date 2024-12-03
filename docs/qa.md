# Satispay PrestaShop module - QA Guide

To confirm that the plugin is functioning correctly, you must run all the end-to-end (E2E) tests manually in your browser.

## Requirements

### Requirements

All tests must be conducted on a PrestaShop installation in an online environment with a host capable of receiving server-to-server callbacks.\
Be aware that ModSecurity might block certain requests; consider disabling it or updating the rules if necessary.

Use the built-in module configuration feature to verify if the callback is functioning correctly, and ensure the Satispay PrestaShop module is installed and configured on the host.

Lastly, make sure you have the Satispay staging app installed on your mobile device with an active account.

## Tests

To approve the module for publishing, all the tests listed below must produce the expected outcome.

### Test 1 - code activation

**PrestaShop Versions:** `v1.7`, `v8.x`

**Execution Steps:**
1. Access the PrestaShop admin panel from a desktop computer.
2. Enter a valid activation code and check the sandbox flag.
3. Save the configuration.

**Expected Outcome:**
You should see a green alert message indicating code activation success.\
The server-to-server health check should either be running or succeeded.

If it is running, you can refresh the page after 10 seconds to see it marked as succeeded.\
If it turns red (failed), there may be something blocking the callbacks.

### 

Execution:
1. Open the PrestaShop website from a desktop computer.
2. Add products to the cart and proceed to the checkout page.
3. Complete all required fields to access the payment page.
4. Select the Satispay payment method. After being redirected to the Satispay page, complete the payment by approving the QR code in the Consumer app.

Expected outcome:
The user should be redirected to the order success page on the desktop computer.

### Test 2

Prestashop verions: `v1.7`, `v8.x`

Execution:
1. Open the PrestaShop website from a mobile device with the Satispay Consumer app installed.
2. Add products to the cart and proceed to the checkout page.
3. Complete all required fields to access the payment page.
4. Select the Satispay payment method. After the Satispay app opens, complete the payment by approving the QR code in the Consumer app.

Expected outcome:
The Satispay app should launch automatically with the payment details. Once the payment is accepted, the user should be redirected to the order success page in the mobile browser.