<?php
/**
 * CoinPayments.net, based on the PayPal Payments Standard plugin
 *
 * @package blesta
 * @subpackage blesta.components.gateways.coinpayments
 * @copyright Copyright (c) 2017, Phillips Data, Inc. Copyright (c) 2014 CoinPayments.net
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class CoinPayments extends NonmerchantGateway
{
    /**
     * @var array An array of meta data for this gateway
     */
    private $meta;

    /**
     * @var string The merchant ID of Phillips Data Inc.
     */
    private $merchant_id = '10f425656e02bac792ea749dba767aba';

    /**
     * @var CoinPaymentsApi The API for the new CoinPayments platform
     */
    private $api;

    /**
     * Construct a new merchant gateway
     */
    public function __construct()
    {
        $this->loadConfig(dirname(__FILE__) . DS . 'config.json');

        // Load components required by this gateway
        Loader::loadComponents($this, ['Input']);

        // Load the language required by this gateway
        Language::loadLang('coin_payments', null, dirname(__FILE__) . DS . 'language' . DS);
    }

    /**
     * Performs migration of data from $current_version (the current installed version)
     * to the given file set version
     *
     * @param string $current_version The current installed version of this gateway
     * @param int $gateway_id The ID of the gateway instance being upgraded
     */
    public function upgrade($current_version, $gateway_id = null)
    {
        if (version_compare($current_version, '3.0.0', '<')) {
            Loader::loadModels($this, ['GatewayManager']);

            $gateways = $this->GatewayManager->getByClass('CoinPayments');

            foreach ($gateways as $gateway) {
                // Existing installations have no API version set and must keep using
                // the legacy API, the settings for the new API are not interchangeable
                $meta = ['api_version' => 'legacy'];
                foreach ($gateway->meta as $meta_item) {
                    $meta[$meta_item->key] = $meta_item->value;
                }

                $this->GatewayManager->edit($gateway->id, ['meta' => $meta]);
            }
        }
    }

    /**
     * Gets a list of the supported API versions
     *
     * @return array A list of API versions and their language
     */
    private function getApiVersions()
    {
        return [
            'legacy' => Language::_('CoinPayments.getapiversions.legacy', true),
            'new' => Language::_('CoinPayments.getapiversions.new', true)
        ];
    }

    /**
     * Gets a list of the supported API URLs
     *
     * @return array A list of API URLs and their language
     */
    private function getApiUrls()
    {
        return [
            'https://a-api.coinpayments.net' => Language::_('CoinPayments.getapiurls.a', true),
            'https://b-api.coinpayments.net' => Language::_('CoinPayments.getapiurls.b', true),
            'https://c-api.coinpayments.net' => Language::_('CoinPayments.getapiurls.c', true),
            'https://api.coinpayments.net' => Language::_('CoinPayments.getapiurls.sandbox', true)
        ];
    }

    /**
     * Gets the configured API URL
     *
     * @return string The API URL
     */
    private function getApiUrl()
    {
        $api_urls = $this->getApiUrls();
        $api_url = trim($this->meta['api_url'] ?? '');

        // The account only exists on the instance it was registered with, so the URL
        // must be one of the supported ones; the first of them is the default
        return (isset($api_urls[$api_url]) ? $api_url : array_key_first($api_urls));
    }

    /**
     * Gets the API for the new CoinPayments platform
     *
     * @return CoinPaymentsApi The API
     */
    private function getApi()
    {
        if (!isset($this->api)) {
            Loader::load(dirname(__FILE__) . DS . 'lib' . DS . 'coin_payments_api.php');

            $this->api = new CoinPaymentsApi(
                trim($this->meta['client_id'] ?? ''),
                trim($this->meta['client_secret'] ?? ''),
                $this->getApiUrl()
            );
        }

        return $this->api;
    }

    /**
     * Builds the URL CoinPayments sends payment notifications to
     *
     * @param int $client_id The ID of the client making the payment
     * @return string The notification URL
     */
    private function getCallbackUrl($client_id)
    {
        return Configure::get('Blesta.gw_callback_url')
            . Configure::get('Blesta.company_id')
            . '/coin_payments/?client_id=' . $client_id;
    }

    /**
     * Sets the currency code to be used for all subsequent payments
     *
     * @param string $currency The ISO 4217 currency code to be used for subsequent payments
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

    /**
     * Create and return the view content required to modify the settings of this gateway
     *
     * @param array $meta An array of meta (settings) data belonging to this gateway
     * @return string HTML content containing the fields to update the meta data for this gateway
     */
    public function getSettings(array $meta = null)
    {
        $this->view = $this->makeView('settings', 'default', str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS));

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        // A new installation defaults to the new API, an existing one has no API version
        // set and must keep using the legacy API
        $this->view->set('api_versions', $this->getApiVersions());
        $this->view->set('api_version', (empty($meta) ? 'new' : ($meta['api_version'] ?? 'legacy')));
        $this->view->set('api_urls', $this->getApiUrls());
        $this->view->set('api_url', $this->getApiUrl());
        $this->view->set('meta', $meta);

        return $this->view->fetch();
    }

    /**
     * Validates the given meta (settings) data to be updated for this gateway
     *
     * @param array $meta An array of meta (settings) data to be updated for this gateway
     * @return array The meta data to be updated in the database for this gateway, or reset into the form on failure
     */
    public function editSettings(array $meta)
    {
        // Verify meta data is valid
        $rules = [
            'api_version' => [
                'valid' => [
                    'if_set' => true,
                    'rule' => ['array_key_exists', $this->getApiVersions()],
                    'message' => Language::_('CoinPayments.!error.api_version.valid', true)
                ]
            ]
        ];

        // The legacy fields are left unvalidated so that existing installations can
        // continue to save their settings exactly as they did before
        if (($meta['api_version'] ?? 'legacy') == 'new') {
            $rules['client_id'] = [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('CoinPayments.!error.client_id.empty', true)
                ]
            ];
            $rules['client_secret'] = [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('CoinPayments.!error.client_secret.empty', true)
                ]
            ];
            $rules['api_url'] = [
                'valid' => [
                    'if_set' => true,
                    'rule' => ['array_key_exists', $this->getApiUrls()],
                    'message' => Language::_('CoinPayments.!error.api_url.valid', true)
                ]
            ];
        }

        $this->Input->setRules($rules);

        // Validate the given meta data to ensure it meets the requirements
        $this->Input->validates($meta);
        // Return the meta data, no changes required regardless of success or failure for this gateway
        return $meta;
    }

    /**
     * Returns an array of all fields to encrypt when storing in the database
     *
     * @return array An array of the field names to encrypt when storing in the database
     */
    public function encryptableFields()
    {
        return ['account_id', 'merchant_id', 'ipn_secret', 'client_id', 'client_secret'];
    }

    /**
     * Sets the meta data for this particular gateway
     *
     * @param array $meta An array of meta data to set for this gateway
     */
    public function setMeta(array $meta = null)
    {
        $this->meta = $meta;
    }

    /**
     * Returns all HTML markup required to render an authorization and capture payment form
     *
     * @param array $contact_info An array of contact info including:
     *  - id The contact ID
     *  - client_id The ID of the client this contact belongs to
     *  - user_id The user ID this contact belongs to (if any)
     *  - contact_type The type of contact
     *  - contact_type_id The ID of the contact type
     *  - first_name The first name on the contact
     *  - last_name The last name on the contact
     *  - title The title of the contact
     *  - company The company name of the contact
     *  - address1 The address 1 line of the contact
     *  - address2 The address 2 line of the contact
     *  - city The city of the contact
     *  - state An array of state info including:
     *      - code The 2 or 3-character state code
     *      - name The local name of the country
     *  - country An array of country info including:
     *      - alpha2 The 2-character country code
     *      - alpha3 The 3-cahracter country code
     *      - name The english name of the country
     *      - alt_name The local name of the country
     *  - zip The zip/postal code of the contact
     * @param float $amount The amount to charge this contact
     * @param array $invoice_amounts An array of invoices, each containing:
     *  - id The ID of the invoice being processed
     *  - amount The amount being processed for this invoice (which is included in $amount)
     * @param array $options An array of options including:
     *  - description The Description of the charge
     *  - return_url The URL to redirect users to after a successful payment
     *  - recur An array of recurring info including:
     *      - start_date The date/time in UTC that the recurring payment begins
     *      - amount The amount to recur
     *      - term The term to recur
     *      - period The recurring period (day, week, month, year, onetime) used in conjunction with term in
     *        order to determine the next recurring payment
     * @return mixed A string of HTML markup required to render an authorization and capture payment form, or an
     *  array of HTML markup
     */
    public function buildProcess(array $contact_info, $amount, array $invoice_amounts = null, array $options = null)
    {
        if (($this->meta['api_version'] ?? 'legacy') == 'new') {
            return $this->buildCheckout($contact_info, $amount, $invoice_amounts, $options);
        }

        return $this->buildLegacyProcess($contact_info, $amount, $invoice_amounts, $options);
    }

    /**
     * Returns the HTML markup for the legacy payment button
     *
     * @param array $contact_info An array of contact info
     * @param float $amount The amount to charge this contact
     * @param array $invoice_amounts An array of invoices, each containing:
     *  - id The ID of the invoice being processed
     *  - amount The amount being processed for this invoice (which is included in $amount)
     * @param array $options An array of options including:
     *  - description The Description of the charge
     *  - return_url The URL to redirect users to after a successful payment
     * @return mixed A string of HTML markup required to render an authorization and capture payment form, or an
     *  array of HTML markup
     */
    private function buildLegacyProcess(
        array $contact_info,
        $amount,
        array $invoice_amounts = null,
        array $options = null
    ) {
        // Force 8-decimal places only
        $amount = round($amount, 8);
        if (isset($options['recur']['amount'])) {
            $options['recur']['amount'] = round($options['recur']['amount'], 8);
        }

        $post_to = 'https://www.coinpayments.net/index.php';

        // An array of key/value hidden fields to set for the payment form
        $fields = [
            'cmd' => '_pay',
            'reset' => '1',
            'merchant' => (isset($this->meta['merchant_id']) ? $this->meta['merchant_id'] : null),
            'currency' => $this->currency,
            'amountf' => $amount,
            'item_name' => (isset($options['description']) ? $options['description'] : null),
            'ipn_url' => $this->getCallbackUrl(
                (isset($contact_info['client_id']) ? $contact_info['client_id'] : null)
            ),
            'success_url' => (isset($options['return_url']) ? $options['return_url'] : null),
            'allow_extra' => 0, // no buyer notes
            'want_shipping' => 0, // no buyer shipping info
            'first_name' => (isset($contact_info['first_name']) ? $contact_info['first_name'] : null),
            'last_name' => (isset($contact_info['last_name']) ? $contact_info['last_name'] : null),
            'email' => (isset($contact_info['email']) ? $contact_info['email'] : null),
            'address1' => (isset($contact_info['address1']) ? $contact_info['address1'] : null),
            'address2' => (isset($contact_info['address2']) ? $contact_info['address2'] : null),
            'city' => (isset($contact_info['city']) ? $contact_info['city'] : null),
            'country' => (isset($contact_info['country']['alpha2']) ? $contact_info['country']['alpha2'] : null),
            'zip' => (isset($contact_info['zip']) ? $contact_info['zip'] : null),
            'author' => $this->merchant_id
        ];

        // Set state if US
        if ((isset($contact_info['country']['alpha2']) ? $contact_info['country']['alpha2'] : null) == 'US') {
            $fields['state'] = (isset($contact_info['state']['code']) ? $contact_info['state']['code'] : null);
        }

        // Set all invoices to pay
        if (isset($invoice_amounts) && is_array($invoice_amounts)) {
            $fields['custom'] = $this->serializeInvoices($invoice_amounts);
        }

        // Build recurring payment fields
        /*
        $recurring_fields = array();
        if ((isset($options['recur']) ? $options['recur'] : null) && (isset($options['recur']['amount']) ? $options['recur']['amount'] : null) > 0) {
            $recurring_fields = $fields;
            unset($recurring_fields['amount']);

            $t3 = null;
            // PayPal calls 'term' 'period' and 'period' 'term'...
            switch ((isset($options['recur']['period']) ? $options['recur']['period'] : null)) {
                case "day":
                    $t3 = "D";
                    break;
                case "week":
                    $t3 = "W";
                    break;
                case "month":
                    $t3 = "M";
                    break;
                case "year";
                    $t3 = "Y";
                    break;
            }

            $recurring_fields['cmd'] = "_xclick-subscriptions";
            $recurring_fields['a1'] = $amount;

            // Calculate days until recurring payment beings. Set initial term
            // to differ from future term iff start_date is set and is set to
            // a future date
            $day_diff = 0;
            if ((isset($options['recur']['start_date']) ? $options['recur']['start_date'] : null) &&
                ($day_diff = floor((strtotime($options['recur']['start_date']) - time())/(60*60*24))) > 0) {

                $recurring_fields['p1'] = $day_diff;
                $recurring_fields['t1'] = "D";
            }
            else {
                $recurring_fields['p1'] = (isset($options['recur']['term']) ? $options['recur']['term'] : null);
                $recurring_fields['t1'] = $t3;
            }
            $recurring_fields['a3'] = (isset($options['recur']['amount']) ? $options['recur']['amount'] : null);
            $recurring_fields['p3'] = (isset($options['recur']['term']) ? $options['recur']['term'] : null);
            $recurring_fields['t3'] = $t3;
            $recurring_fields['custom'] = null;
            $recurring_fields['modify'] = (isset($this->meta['modify']) ? $this->meta['modify'] : null) == "true" ? 1 : 0;
            $recurring_fields['src'] = "1"; // recur payments


            // Can't allow recurring field if prorated term is more than 90 days out
            if ($day_diff > 90)
                $recurring_fields = array();
        }
        */

        $regular_btn = $this->buildForm($post_to, $fields, false);
        return $regular_btn;
        /*
        $recurring_btn = null;
        if (!empty($recurring_fields))
            $recurring_btn = $this->buildForm($post_to, $recurring_fields, true);

        switch ((isset($this->meta['pay_type']) ? $this->meta['pay_type'] : null)) {
            case "both":
                if ($recurring_btn)
                    return array($regular_btn, $recurring_btn);
                return $regular_btn;
            case "subscribe":
                return $recurring_btn;
            case "onetime":
                return $regular_btn;
        }
        return null;
            */
    }

    /**
     * Creates an invoice through the CoinPayments API and returns the markup to send
     * the client to the hosted checkout for it
     *
     * @param array $contact_info An array of contact info
     * @param float $amount The amount to charge this contact
     * @param array $invoice_amounts An array of invoices, each containing:
     *  - id The ID of the invoice being processed
     *  - amount The amount being processed for this invoice (which is included in $amount)
     * @param array $options An array of options including:
     *  - description The Description of the charge
     *  - return_url The URL to redirect users to after a successful payment
     * @return string A string of HTML markup linking to the hosted checkout
     */
    private function buildCheckout(
        array $contact_info,
        $amount,
        array $invoice_amounts = null,
        array $options = null
    ) {
        $api = $this->getApi();

        // Invoices are priced by currency ID rather than by ISO 4217 code
        $currency = $api->getCurrency($this->currency);

        if (empty($currency->id)) {
            // The currency is either not supported or could not be fetched, log the
            // lookup so the two can be told apart
            $request = $api->lastRequest();
            $this->log($request['url'], 'Unable to resolve currency ' . $this->currency, 'output', false);

            $this->Input->setErrors([
                'currency' => [
                    'unsupported' => Language::_('CoinPayments.!error.currency.unsupported', true, $this->currency)
                ]
            ]);

            return null;
        }

        $client_id = $contact_info['client_id'] ?? null;
        $description = $options['description'] ?? Language::_('CoinPayments.buildprocess.description', true);
        $total = number_format($amount, (int) ($currency->decimalPlaces ?? 2), '.', '');

        $params = [
            'currency' => (string) $currency->id,
            'clientId' => $this->meta['client_id'] ?? null,
            'invoiceId' => 'BLESTA-' . $client_id . '-' . time(),
            'items' => [
                [
                    'name' => $description,
                    'quantity' => ['value' => 1, 'type' => 2],
                    'amount' => $total
                ]
            ],
            'amount' => [
                'breakdown' => ['subtotal' => $total],
                'total' => $total
            ],
            'buyer' => $this->getBuyer($contact_info),
            // Custom data is returned untouched with every notification, the currency is
            // included because it is not available when a notification is validated
            'customData' => [
                'client_id' => (string) $client_id,
                'invoices' => $this->serializeInvoices($invoice_amounts ?? []),
                'currency' => $this->currency
            ],
            'notesToRecipient' => $description,
            'successUrl' => $options['return_url'] ?? null,
            'cancelUrl' => $options['return_url'] ?? null,
            'webhooks' => [
                [
                    'notificationsUrl' => $this->getCallbackUrl($client_id),
                    'notifications' => [
                        'invoicePending',
                        'invoicePaid',
                        'invoiceCompleted',
                        'invoiceCancelled',
                        'invoiceTimedOut'
                    ]
                ]
            ]
        ];

        $response = $api->createInvoice($params);
        $request = $api->lastRequest();
        $errors = $response->errors();

        $this->log($request['url'], $request['params'], 'input', true);
        $this->log($request['url'], $response->raw(), 'output', empty($errors));

        if (!empty($errors)) {
            $this->Input->setErrors($errors);

            return null;
        }

        $invoice = $response->response()->invoices[0] ?? null;
        $checkout_url = $invoice->checkoutLink ?? ($invoice->link ?? null);

        if (empty($checkout_url)) {
            $this->Input->setErrors($this->getCommonError('general'));

            return null;
        }

        return $this->buildCheckoutForm($checkout_url);
    }

    /**
     * Formats the given contact for the CoinPayments API, omitting any value not set
     *
     * @param array $contact_info An array of contact info
     * @return array The buyer to send to the API
     */
    private function getBuyer(array $contact_info)
    {
        $address = array_filter([
            'address1' => $contact_info['address1'] ?? null,
            'address2' => $contact_info['address2'] ?? null,
            'provinceOrState' => $contact_info['state']['code'] ?? null,
            'city' => $contact_info['city'] ?? null,
            'countryCode' => $contact_info['country']['alpha2'] ?? null,
            'postalCode' => $contact_info['zip'] ?? null
        ], [$this, 'isPresent']);

        $buyer = [
            'name' => array_filter([
                'firstName' => $contact_info['first_name'] ?? null,
                'lastName' => $contact_info['last_name'] ?? null
            ], [$this, 'isPresent'])
        ];

        if (!empty($address)) {
            $buyer['address'] = $address;
        }

        // The email address is not given with the contact info, fetch it from the contact
        if (!empty($contact_info['id'])) {
            Loader::loadModels($this, ['Contacts']);

            if (($contact = $this->Contacts->get($contact_info['id'])) && !empty($contact->email)) {
                $buyer['emailAddress'] = $contact->email;
            }
        }

        return $buyer;
    }

    /**
     * Determines whether the given value should be sent to the API
     *
     * @param mixed $value The value to check
     * @return bool True if the value is set, false otherwise
     */
    private function isPresent($value)
    {
        return $value !== null && $value !== '';
    }

    /**
     * Builds the markup linking to the hosted checkout
     *
     * @param string $post_to The URL of the hosted checkout
     * @return string The HTML markup
     */
    private function buildCheckoutForm($post_to)
    {
        $this->view = $this->makeView(
            'process_checkout',
            'default',
            str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS)
        );

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        $this->view->set('post_to', $post_to);

        return $this->view->fetch();
    }

    /**
     * Builds the HTML form
     *
     * @param string $post_to The URL to post to
     * @param array $fields An array of key/value input fields to set in the form
     * @param boolean $recurring True if this is a recurring payment request, false otherwise
     * @return string The HTML form
     */
    private function buildForm($post_to, $fields, $recurring = false)
    {
        $this->view = $this->makeView('process', 'default', str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS));

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        $this->view->set('post_to', $post_to);
        $this->view->set('fields', $fields);
        $this->view->set('recurring', $recurring);

        return $this->view->fetch();
    }

    private function checkIpnRequestIsValid($post)
    {
        $error_msg = null;

        if ((isset($post['ipn_mode']) ? $post['ipn_mode'] : null) == 'hmac') {
            if (isset($_SERVER['HTTP_HMAC']) && !empty($_SERVER['HTTP_HMAC'])) {
                $request = file_get_contents('php://input');
                if ($request !== false && !empty($request)) {
                    if ((isset($post['merchant']) ? $post['merchant'] : null) == trim($this->meta['merchant_id'] ?? '')) {
                        $hmac = hash_hmac('sha512', $request, trim($this->meta['ipn_secret'] ?? ''));
                        if ($hmac !== $_SERVER['HTTP_HMAC']) {
                            $error_msg = 'HMAC signature does not match';
                        }
                    } else {
                        $error_msg = 'No or incorrect Merchant ID passed';
                    }
                } else {
                    $error_msg = 'Error reading POST data';
                }
            } else {
                $error_msg = 'No HMAC signature sent.';
            }
        } elseif ((isset($post['ipn_mode']) ? $post['ipn_mode'] : null) == 'httpauth') {
            if (!isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])
                || !hash_equals(trim($this->meta['merchant_id'] ?? ''), $_SERVER['PHP_AUTH_USER'])
                || !hash_equals(trim($this->meta['ipn_secret'] ?? ''), $_SERVER['PHP_AUTH_PW'])
            ) {
                $error_msg = 'Invalid merchant id/ipn secret';
            }
        } else {
            $error_msg = 'Unknown IPN mode!';
        }

        return $error_msg;
    }

    /**
     * Validates the incoming POST/GET response from the gateway to ensure it is
     * legitimate and can be trusted.
     *
     * @param array $get The GET data for this request
     * @param array $post The POST data for this request
     * @return array An array of transaction data, sets any errors using Input if the data fails to validate
     *  - client_id The ID of the client that attempted the payment
     *  - amount The amount of the payment
     *  - currency The currency of the payment
     *  - invoices An array of invoices and the amount the payment should be applied to (if any) including:
     *      - id The ID of the invoice to apply to
     *      - amount The amount to apply to the invoice
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the gateway to identify this transaction
     *  - parent_transaction_id The ID returned by the gateway to identify this transaction's original
     *    transaction (in the case of refunds)
     */
    public function validate(array $get, array $post)
    {
        if (($this->meta['api_version'] ?? 'legacy') == 'new') {
            return $this->validateWebhook($get, $post);
        }

        return $this->validateIpn($get, $post);
    }

    /**
     * Validates an incoming webhook from the CoinPayments API
     *
     * @param array $get The GET data for this request
     * @param array $post The POST data for this request
     * @return array An array of transaction data, sets any errors using Input if the data fails to validate
     */
    private function validateWebhook(array $get, array $post)
    {
        $payload = file_get_contents('php://input');

        // The signature covers the URL the webhook was registered with, rebuild it rather
        // than reading it back from the request, which a proxy may have rewritten
        $url = $this->getCallbackUrl($get['client_id'] ?? null);

        // Log request received
        $this->log($url, $payload, 'output', true);

        if (($error_msg = $this->checkWebhookIsValid($url, $payload))) {
            $this->Input->setErrors($this->getCommonError('invalid'));

            // Log the reason the webhook could not be verified
            $this->log($url, 'Webhook Error: ' . $error_msg, 'output', false);

            return;
        }

        $webhook = json_decode($payload);
        $invoice = $webhook->invoice ?? null;
        $custom_data = $invoice->customData ?? null;

        return [
            'client_id' => $get['client_id'] ?? ($custom_data->client_id ?? null),
            'amount' => $invoice->amount->total ?? null,
            'currency' => $custom_data->currency ?? null,
            'status' => $this->getWebhookStatus($webhook->type ?? null),
            'reference_id' => null,
            'transaction_id' => $invoice->id ?? null,
            'parent_transaction_id' => '',
            'invoices' => $this->unserializeInvoices($custom_data->invoices ?? null)
        ];
    }

    /**
     * Verifies that the given webhook was sent by CoinPayments
     *
     * @param string $url The URL the webhook was registered with
     * @param string $payload The raw body of the webhook
     * @return string|null An error message, or null if the webhook is valid
     */
    private function checkWebhookIsValid($url, $payload)
    {
        $signature = $_SERVER['HTTP_X_COINPAYMENTS_SIGNATURE'] ?? null;
        $timestamp = $_SERVER['HTTP_X_COINPAYMENTS_TIMESTAMP'] ?? null;
        $client_id = $_SERVER['HTTP_X_COINPAYMENTS_CLIENT'] ?? null;

        if (empty($signature) || empty($timestamp) || empty($client_id)) {
            return 'No signature headers sent.';
        }

        if (!hash_equals(trim($this->meta['client_id'] ?? ''), $client_id)) {
            return 'No or incorrect Client ID passed';
        }

        if ($payload === false || $payload === '') {
            return 'Error reading POST data';
        }

        if (!hash_equals($this->getApi()->signature('POST', $url, $payload, $timestamp), $signature)) {
            return 'Signature does not match for ' . $url;
        }

        return null;
    }

    /**
     * Gets the transaction status for the given webhook type
     *
     * @param string $type The type of the webhook
     * @return string The status of the transaction
     */
    private function getWebhookStatus($type)
    {
        switch ($type) {
            case 'invoicePaid':
            case 'invoiceCompleted':
                return 'approved';
            case 'invoiceCancelled':
            case 'invoiceTimedOut':
                return 'declined';
            default:
                return 'pending';
        }
    }

    /**
     * Validates an incoming IPN from the legacy CoinPayments platform
     *
     * @param array $get The GET data for this request
     * @param array $post The POST data for this request
     * @return array An array of transaction data, sets any errors using Input if the data fails to validate
     */
    private function validateIpn(array $get, array $post)
    {
        // Log request received
        $this->log(($_SERVER['REQUEST_URI'] ?? null), serialize($post), 'output', true);

        // Ensure IPN is verified, and validate that the merchant ID is correct, to
        // prevent payments being recognized that were not sent by the gateway
        $error_msg = $this->checkIpnRequestIsValid($post);

        if ($error_msg) {
            $this->Input->setErrors($this->getCommonError('invalid'));

            // Log the reason the IPN could not be verified
            $this->log(
                ($_SERVER['REQUEST_URI'] ?? null),
                'IPN Error: ' . $error_msg . "\n\n" . json_encode($post, JSON_PRETTY_PRINT),
                'output',
                false
            );

            return;
        }

        // Only a completed payment, or one queued for payout, is settled
        $pmt_status = intval(($post['status'] ?? '0'));

        $status = 'pending';
        if ($pmt_status >= 100 || $pmt_status == 2) {
            $status = 'approved';
        } elseif ($pmt_status < 0) {
            $status = 'error';
        }

        return [
            'client_id' => (isset($get['client_id']) ? $get['client_id'] : null),
            'amount' => (isset($post['amount1']) ? $post['amount1'] : null),
            'currency' => (isset($post['currency1']) ? $post['currency1'] : null),
            'status' => $status,
            'reference_id' => null,
            'transaction_id' => (isset($post['txn_id']) ? $post['txn_id'] : null),
            'parent_transaction_id' => '',
            'invoices' => $this->unserializeInvoices((isset($post['custom']) ? $post['custom'] : null))
        ];
    }

    /**
     * Returns data regarding a success transaction. This method is invoked when
     * a client returns from the non-merchant gateway's web site back to Blesta.
     *
     * @param array $get The GET data for this request
     * @param array $post The POST data for this request
     * @return array An array of transaction data, may set errors using Input if the data appears invalid
     *  - client_id The ID of the client that attempted the payment
     *  - amount The amount of the payment
     *  - currency The currency of the payment
     *  - invoices An array of invoices and the amount the payment should be applied to (if any) including:
     *      - id The ID of the invoice to apply to
     *      - amount The amount to apply to the invoice
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - transaction_id The ID returned by the gateway to identify this transaction
     *  - parent_transaction_id The ID returned by the gateway to identify this transaction's original transaction
     */
    public function success(array $get, array $post)
    {
        return ['status' => 'pending'];
    }

    /**
     * Serializes an array of invoice info into a string
     *
     * @param array A numerically indexed array invoices info including:
     *  - id The ID of the invoice
     *  - amount The amount relating to the invoice
     * @return string A serialized string of invoice info in the format of key1=value1|key2=value2
     */
    private function serializeInvoices(array $invoices)
    {
        $str = '';
        foreach ($invoices as $i => $invoice) {
            $str .= ($i > 0 ? '|' : '') . $invoice['id'] . '=' . $invoice['amount'];
        }
        return $str;
    }

    /**
     * Unserializes a string of invoice info into an array
     *
     * @param string A serialized string of invoice info in the format of key1=value1|key2=value2
     * @return array A numerically indexed array invoices info including:
     *  - id The ID of the invoice
     *  - amount The amount relating to the invoice
     */
    private function unserializeInvoices($str)
    {
        $invoices = [];
        $temp = explode('|', $str);
        foreach ($temp as $pair) {
            $pairs = explode('=', $pair, 2);
            if (count($pairs) != 2) {
                continue;
            }
            $invoices[] = ['id' => $pairs[0], 'amount' => $pairs[1]];
        }
        return $invoices;
    }
}
