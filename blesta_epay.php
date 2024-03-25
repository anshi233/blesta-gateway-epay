<?php
/**
 * PayPal Checkout Gateway
 *
 * Allows users to pay via PayPal and 10+ local payment methods
 *
 * @package blesta
 * @subpackage blesta.components.gateways.nonmerchant.paypal_checkout
 * @author Phillips Data, Inc.
 * @copyright Copyright (c) 2023, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class BlestaEPay extends NonmerchantGateway
{
    /**
     * @var array An array of meta data for this gateway
     */
    private $meta;

    /**
     * Construct a new merchant gateway
     */
    public function __construct()
    {
        //TO-DO: replace to EPay API SDK
        // Load the PayPal Checkout API
        Loader::load(dirname(__FILE__) . DS . 'lib/epay_sdk' . DS . 'EpayCore.class.php');

        // Load configuration required by this gateway
        $this->loadConfig(dirname(__FILE__) . DS . 'config.json');

        // Load components required by this gateway
        Loader::loadComponents($this, ['Input']);

        // Load the language required by this gateway
        Language::loadLang('paypal_checkout', null, dirname(__FILE__) . DS . 'language' . DS);
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
     * Create and return the view content required to modify the settings of this gateway
     *
     * @param array $meta An array of meta (settings) data belonging to this gateway
     * @return string HTML content containing the fields to update the meta data for this gateway
     */
    public function getSettings(array $meta = null)
    {
        // Load the view into this object, so helpers can be automatically add to the view
        $this->view = new View('settings', 'default');
        $this->view->setDefaultView('components' . DS . 'gateways' . DS . 'nonmerchant' . DS . 'blesta_epay' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

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
        // Set unset checkboxes
        $checkbox_fields = ['sandbox'];
        foreach ($checkbox_fields as $checkbox_field) {
            if (!isset($meta[$checkbox_field])) {
                $meta[$checkbox_field] = 'false';
            }
        }
//Starting from here
        // Set rules
        $rules = [
            'client_id' => [
                'valid' => [
                    'rule' => [[$this, 'validateConnection'], $meta['client_secret'], $meta['sandbox']],
                    'message' => Language::_('PaypalCheckout.!error.client_id.valid', true)
                ]
            ],
            'client_secret' => [
                'valid' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('PaypalCheckout.!error.client_secret.valid', true)
                ]
            ]
        ];
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
        return ['client_secret'];
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
     *      - amount The amount to recur
     *      - term The term to recur
     *      - period The recurring period (day, week, month, year, onetime) used in conjunction
     *          with term in order to determine the next recurring payment
     * @return string HTML markup required to render an authorization and capture payment form
     */
    public function buildProcess(array $contact_info, $amount, array $invoice_amounts = null, array $options = null)
    {
        // Force 2-decimal places only
        $amount = round($amount, 2);
        if (isset($options['recur']['amount'])) {
            $options['recur']['amount'] = round($options['recur']['amount'], 2);
        }

        // Remove decimals on unsupported currencies
        if (in_array($this->currency, ['HUF', 'JPY', 'TWD'])) {
            $amount = round($amount, 0);
            if (isset($options['recur']['amount'])) {
                $options['recur']['amount'] = round($options['recur']['amount'], 0);
            }
        }

        $this->view = $this->makeView('process', 'default', str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS));

        // Load the models and helpers required for this view
        Loader::loadModels($this, ['Companies']);
        Loader::loadHelpers($this, ['Form', 'Html']);

        // Get company information
        $company = $this->Companies->get(Configure::get('Blesta.company_id'));

        // Initialize API
        $api = $this->getApi($this->meta['client_id'], $this->meta['client_secret'], $this->meta['sandbox']);
        $orders = new PaypalCheckoutOrders($api);

        // Generate order
        $params = [
            'purchase_units' => [
                [
                    'description' => $options['description'] ?? '',
                    'soft_descriptor' => substr(preg_replace('/[^a-z1-9\ \-\*\.]/i', '', $company->name ?? ''), 0, 22),
                    'amount' => [
                        'currency_code' => $this->currency,
                        'value' => $amount
                    ],
                    'reference_id' => $this->serializeInvoices($invoice_amounts),
                    'custom_id' => $contact_info['client_id'] ?? null
                ]

            ],
            'intent' => 'CAPTURE',
            'application_context' => [
                'return_url' => $options['return_url'] ?? null,
                'cancel_url' => $options['return_url'] ?? null
            ]
        ];
        $order = $orders->create($params);
        $response = $order->response();

        $this->log('buildProcess', json_encode($params), 'input', true);
        $this->log('buildProcess', json_encode($response), 'output', empty($order->errors()));

        // Get payment url
        $post_to = '#';
        foreach ($response->links as $link) {
            if ($link->rel == 'approve') {
                $post_to = $link->href;
            }
        }

        $this->view->set('client_id', $this->meta['client_id']);
        $this->view->set('post_to', $post_to);

        return $this->view->fetch();
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
     *  - parent_transaction_id The ID returned by the gateway to identify this
     *      transaction's original transaction (in the case of refunds)
     */
    public function validate(array $get, array $post)
    {
        // Initialize API
        $api = $this->getApi($this->meta['client_id'], $this->meta['client_secret'], $this->meta['sandbox']);
        $payments = new PaypalCheckoutPayments($api);

        // Fetch webhook payload
        $payload = file_get_contents('php://input');
        $webhook = json_decode($payload);

        // Discard all webhook events, except when the order is completed or approved
        $events = ['CHECKOUT.ORDER.APPROVED', 'PAYMENT.CAPTURE.COMPLETED'];
        if (!in_array($webhook->event_type ?? '', $events)) {
            $this->Input->setErrors([
                'event' => ['unsupported' => Language::_('PaypalCheckout.!error.event.unsupported', true)]
            ]);
            return;
        }

        $this->log('validate', json_encode($webhook), 'input', !empty($webhook));

        // Capture payment
        if ($webhook->event_type == 'CHECKOUT.ORDER.APPROVED') {
            $orders = new PaypalCheckoutOrders($api);
            $response = $orders->capture(['id' => $webhook->resource->id]);

            $this->log('capture', json_encode($response->response()), 'output', empty($response->errors()));

            // Output errors
            if (($errors = $response->errors())) {
                $this->Input->setErrors($errors);
                return;
            }

            return [
                'client_id' => $webhook->resource->purchase_units[0]->custom_id ?? null,
                'amount' => $webhook->resource->purchase_units[0]->amount->value ?? null,
                'currency' => $webhook->resource->purchase_units[0]->amount->currency_code ?? null,
                'invoices' => $this->unserializeInvoices($webhook->resource->purchase_units[0]->reference_id ?? ''),
                'status' => 'pending',
                'reference_id' => null,
                'transaction_id' => $webhook->resource->id ?? null,
                'parent_transaction_id' => null
            ];
        }

        // Set the payment
        $payment = $webhook->resource ?? (object) [];

        // Fetch the transaction
        $order_response = (object) [];
        $order = (object) [];
        $transaction = (object) [];
        if (isset($payment->supplementary_data->related_ids->order_id)) {
            $orders = new PaypalCheckoutOrders($api);
            $order_response = $orders->get(['id' => $payment->supplementary_data->related_ids->order_id]) ?? (object) [];
            $order = $order_response->response();
            $transaction = $order->purchase_units[0] ?? (object) [];
        }

        $this->log('validate', json_encode($transaction), 'output', !empty($transaction));

        if (empty($transaction)) {
            $this->Input->setErrors([
                'transaction' => ['missing' => Language::_('PaypalCheckout.!error.transaction.missing', true)]
            ]);
            return;
        }

        // Set status
        $status = 'error';
        $success = false;
        switch ($payment->status ?? 'ERROR') {
            case 'COMPLETED':
                $status = 'approved';
                $success = true;
                break;
            case 'APPROVED':
                $status = 'pending';
                $success = true;
                break;
            case 'VOIDED':
                $status = 'void';
                $success = true;
                break;
        }

        if (!$success) {
            $this->Input->setErrors($this->getCommonError('general'));
            return;
        }

        // Output errors
        if (($errors = $order_response->errors())) {
            $this->Input->setErrors($errors);
            return;
        }

        return [
            'client_id' => $transaction->custom_id ?? null,
            'amount' => $payment->amount->value ?? null,
            'currency' => $payment->amount->currency_code ?? null,
            'invoices' => $this->unserializeInvoices($transaction->reference_id ?? ''),
            'status' => $status,
            'reference_id' => $payment->id ?? null,
            'transaction_id' => $order->id ?? null,
            'parent_transaction_id' => null
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
        // Initialize API
        $api = $this->getApi($this->meta['client_id'], $this->meta['client_secret'], $this->meta['sandbox']);
        $orders = new PaypalCheckoutOrders($api);

        $this->log('success', json_encode($get), 'output', !empty($get));

        // Fetch the order, if a token is provided
        if (!empty($get['token'])) {
            $order = $orders->get(['id' => $get['token']]);
            $response = $order->response();
        }

        // Set transaction
        $transaction = (object) [];
        if (!empty($response->purchase_units)) {
            $transaction = $response->purchase_units[0] ?? (object) [];
        }

        $params = [
            'client_id' => $transaction->custom_id ?? null,
            'amount' => $transaction->amount->value ?? null,
            'currency' => $transaction->amount->currency_code ?? null,
            'invoices' => $this->unserializeInvoices($transaction->reference_id ?? ''),
            'status' => 'approved',
            'transaction_id' => $get['token'] ?? null,
            'parent_transaction_id' => null
        ];

        return $params;
    }

    /**
     * Refund a payment
     *
     * @param string $reference_id The reference ID for the previously submitted transaction
     * @param string $transaction_id The transaction ID for the previously submitted transaction
     * @param float $amount The amount to refund this transaction
     * @param string $notes Notes about the refund that may be sent to the client by the gateway
     * @return array An array of transaction data including:
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the remote gateway to identify this transaction
     *  - message The message to be displayed in the interface in addition to the standard
     *      message for this transaction status (optional)
     */
    public function refund($reference_id, $transaction_id, $amount, $notes = null)
    {
        // Initialize API
        $api = $this->getApi($this->meta['client_id'], $this->meta['client_secret'], $this->meta['sandbox']);
        $payments = new PaypalCheckoutPayments($api);

        $this->log('getpayment', json_encode(compact('reference_id', 'transaction_id')), 'input', !empty($get));

        // Fetch the payment
        $payment = $payments->get(['id' => $reference_id]);
        $response = $payment->response();
        $this->log('getpayment', json_encode($response), 'output', empty($payment->errors() ?? []));

        if (empty($response) || !isset($response->status)) {
            $this->Input->setErrors($this->getCommonError('general'));
            return;
        }

        // Attempt a refund
        try {
            $params = [
                'capture_id' => $reference_id,
                'amount' => (object)['value' => $amount, 'currency_code' => $response->amount->currency_code]
            ];
            $this->log('refund', json_encode($params), 'input', true);
            $refund = $payments->refund($params);
            $this->log('refund', $refund->raw(), 'output', $refund->status() == '200');
        } catch (Throwable $e) {
            $this->Input->setErrors(['internal' => ['internal' => $e->getMessage()]]);
            return;
        }

        // Output errors
        if (($errors = $refund->errors())) {
            $this->Input->setErrors(['internal' => $errors]);
            return;
        }

        return [
            'status' => 'refunded',
            'transaction_id' => $transaction_id
        ];
    }

    /**
     * Void a payment or authorization.
     *
     * @param string $reference_id The reference ID for the previously submitted transaction
     * @param string $transaction_id The transaction ID for the previously submitted transaction
     * @param string $notes Notes about the void that may be sent to the client by the gateway
     * @return array An array of transaction data including:
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the remote gateway to identify this transaction
     *  - message The message to be displayed in the interface in addition to the standard
     *      message for this transaction status (optional)
     */
    public function void($reference_id, $transaction_id, $notes = null)
    {
        // Initialize API
        $api = $this->getApi($this->meta['client_id'], $this->meta['client_secret'], $this->meta['sandbox']);
        $payments = new PaypalCheckoutPayments($api);

        $this->log('void', json_encode(compact('reference_id', 'transaction_id')), 'output', !empty($get));

        // Fetch the payment
        $payment = $payments->get(['id' => $reference_id]);
        $response = $payment->response();

        if (empty($response) || !isset($response->status)) {
            $this->Input->setErrors($this->getCommonError('general'));
            return;
        }

        // Attempt void
        try {
            $void = $payments->void(['capture_id' => $reference_id]);
            $this->log('void', $void->raw(), 'output', $void->status() == '200');
        } catch (Throwable $e) {
            return;
        }

        // Output errors
        if (($errors = $void->errors())) {
            $this->Input->setErrors(['internal' => $errors]);
            return;
        }

        return [
            'status' => 'void',
            'transaction_id' => $transaction_id
        ];
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

    /**
     * Loads the given API if not already loaded
     *
     * @param string $client_id The client ID of PayPal Checkout
     * @param string $client_secret The client secret key
     * @param string $sandbox Whether or not to use the sandbox environment
     */
    private function getApi(string $client_id, string $client_secret, $sandbox = 'false')
    {
        $environment = ($sandbox == 'false' ? 'live' : 'sandbox');

        return new PaypalCheckoutApi($client_id, $client_secret, $environment);
    }

    /**
     * Validates if the provided API Key is valid
     *
     * @param string $client_id The client ID of PayPal Checkout
     * @param string $client_secret The client secret key
     * @param string $sandbox Whether or not to use the sandbox environment
     * @return bool True if the API Key is valid, false otherwise
     */
    public function validateConnection($client_id, $client_secret, $sandbox = 'false')
    {
        try {
            // Initialize API
            $api = $this->getApi($client_id, $client_secret, $sandbox);
            $orders = new PaypalCheckoutOrders($api);

            $params = [
                'purchase_units' => [
                    [
                        'description' => 'Blesta',
                        'soft_descriptor' => 'Blesta',
                        'amount' => [
                            'currency_code' => 'USD',
                            'value' => '0.99'
                        ]
                    ]
                ],
                'intent' => 'AUTHORIZE'
            ];
            $order = $orders->create($params);
            $response = $order->response();

            return !empty($response->links);
        } catch (Throwable $e) {
            $this->Input->setErrors(['create' => ['response' => $e->getMessage()]]);

            return false;
        }
    }
}
