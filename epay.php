<?php
/**
 * EPay(易支付) Gateway
 *
 * Allows users to pay via Alipay, Wechat Pay, Union Pay, USDT, etc. (depends on remote EPay Gateway)
 *
 * @package blesta
 * @subpackage blesta.components.gateways.nonmerchant.epay
 * @author Anshi
 */
class Epay extends NonmerchantGateway
{
    /**
     * @var array An array of meta data for this gateway
     */
    private $meta;
    /**
     * @var array An array of EPay parameters
     */
    private array $ePayConfig;

    /**
     * Construct a new merchant gateway
     */
    public function __construct()
    {
        //TO-DO: replace to EPay API SDK
        // Load the EPay API SDK (Coming from v1.3)
        Loader::load(dirname(__FILE__) . DS . 'lib/epay_sdk' . DS . 'EpayCore.class.php');

        // Load configuration required by this gateway
        $this->loadConfig(dirname(__FILE__) . DS . 'config.json');

        // Load components required by this gateway
        Loader::loadComponents($this, ['Input']);

        // Load the language required by this gateway
        Language::loadLang('epay', null, dirname(__FILE__) . DS . 'language' . DS);
    }

    /**
     * Sets the meta data for this particular gateway
     *
     * @param array $meta An array of meta data to set for this gateway
     */
    public function setMeta(array $meta = null)
    {
        $this->meta = $meta;
        //Also set the EPay parameters
        $this->ePayConfig = [
            'pid' => $meta['pid'],
            'key' => $meta['key'],
            'apiurl' => $meta['apiurl']
        ];

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
        $this->view->setDefaultView('components' . DS . 'gateways' . DS . 'nonmerchant' . DS . 'epay' . DS);

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
        // Set rules
        $rules = [
            //Merchant ID
            'pid' => [
                //Check is pid is empty
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Epay.!error.pid.empty', true)
                ],
            ],
            //Merchant Key
            'key' => [
                //Check is key is empty
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Epay.!error.key.empty', true)
                ],
            ],
            //Gateway URL
            'apiurl' => [
                //Check is apiurl is empty
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Epay.!error.apiurl.empty', true)
                ],
                //Check is apiurl is valid
                'valid' => [
                    'rule' => [[$this, 'validateApiurl'], $meta['pid'], $meta['key']],
                    'message' => Language::_('Epay.!error.api.valid', true)
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
        
        //For debug, no need to encrypt now
        //return ['key'];
        //return empty array
        return [];
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
        // Force 1-decimal places only
        $amount = round($amount, 2);
        if (isset($options['recur']['amount'])) {
            $options['recur']['amount'] = round($options['recur']['amount'], 2);
        }

        //EPay only support RMB

        // At this line, we will load the view html file. It is the payment button.
        $this->view = $this->makeView('process', 'default', str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS));

        // Load the models and helpers required for this view
        Loader::loadModels($this, ['Companies']);
        Loader::loadHelpers($this, ['Form', 'Html']);

        // Get Client Information
        Loader::loadModels($this, ['Contacts']);

        //EPay only support one invoice for each transaction
        //Give error if more than one invoice
        if(count($invoice_amounts) > 1){
            $this->Input->setErrors(['api' => ['internal' => 'EPay only support one invoice for each transaction']]);
            return;
        }
        $out_trade_no = $invoice_amounts[0]['id'];



        // Initialize API
        $api = $this->getApi($this->ePayConfig);
        //2024-11-28 Found an issue that the return url is different for payment made from order page or invoice payment page.
        //Always use invoice payment page to make epay gateway api happy. Otherwise it will give parameter changed error.
        $callbackUrl = Configure::get('Blesta.gw_callback_url');
        preg_match('/^(https?:\/\/[^\/]+)/', $callbackUrl, $matches);
        $baseUrl = $matches[1];
        
        // For EPay, we need to don't need to create order first.
        // Just collect enough information and send to EPay directly, it will give us a payment link.
        // We will use the EPayCore class to do this.
        // ePayUrl is the link that we want to redirect the user to.
        $orderInfo = array(
            "pid" => $this->ePayConfig['pid'],
            //Type leave blank for now. We want to let user select payment method. (Alipay, WeChat Pay .etc)
            //TO-DO: Add a dropdown to let user select payment method. (No ETA)
            "type" => '',
            //Notify URL is the blesta websocket URL.
            "notify_url" => Configure::get('Blesta.gw_callback_url') . Configure::get('Blesta.company_id') . '/epay/',
            //Return URL is the URL that user will be redirected to after payment.
            //"return_url" => $options['return_url'],
            //TO-DO: May have issue if multi-company is used.
            "return_url" => $baseUrl . '/client/pay/received/epay/?client_id=' . $contact_info['client_id'],
            //out_trade_no is our blesta created order number(Invoice number)
            "out_trade_no" => $out_trade_no,
            //name is the product name e.g. "HK VPS Value Plan"
            "name" => $options['description'],
            //money is the price of the product in RMB!!!
            "money"	=> $amount,
            //use EPay API's param field to passing client_id
            "param" => "client_id=" . $contact_info['client_id']
        );
        //Log the api input
        $this->log('buildProcess', json_encode($orderInfo), 'input', true);
        //Get payment link
        try {
            $ePayUrl = $api->getPayLink($orderInfo);
        } catch (Exception $e) {
            $this->Input->setErrors(['api' => ['internal' => $e->getMessage()]]);
            return;
        }
        
        $this->view->set('epay_url', $ePayUrl);

        return $this->view->fetch();

    }

    /**
     * Handle Verified payment result information
     * This function will format the raw EPay API return information to somthing Blesta can understand.
     * @param array $get The GET data from EPay API requests
     * @return array The array of transaction data
     */
    private function handleEPayOrder(array $get){
        $out_trade_no = $get['out_trade_no'] ?? null;
        $trade_no = $get['trade_no'] ?? null;
        //I will use amount not money
        $amount = $get['money'] ?? null;

        // Start process the successful payment
        // Get Client ID from EPay API's param field
        preg_match('/client_id=(\d+)/', $get['param'], $matches);
        if (!empty($matches) && !empty($matches[1])) {
            $clientId = $matches[1];
        } else {
            // Handle the case where client_id is empty or not found
            $this->Input->setErrors(['api' => ['internal' => 'empty client id']]);
            return;
        }
        return [
            'client_id' => $clientId ?? null,
            'amount' => $amount,
            'currency' => 'CNY',
            //'invoices' => $this->unserializeInvoices($out_trade_no),
            'invoices' => [['id' => $out_trade_no, 'amount' => $amount]],
            'status' => 'approved',
            'reference_id' => null,
            'transaction_id' => $trade_no,
            'parent_transaction_id' => null
        ];
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
        $api = $this->getApi($this->ePayConfig);
        //From raw get data verify EPay sign
        $sign_result = $api->verifyReturnBlesta($get);
        if($sign_result == false) {
            //Throw error when sign validation failed
            $this->Input->setErrors([
                'event' => ['invalid_sign' => Language::_('Epay.!error.event.invalid_sign', true)]
            ]);
            return; 
        }
        // Discard all webhook events, except when the order is completed or approved
        if ($get['trade_status'] != 'TRADE_SUCCESS') {
            //Throw error event for unsuccessful payment result
            $this->Input->setErrors([
                'event' => ['unsupported' => Language::_('Epay.!error.event.unsupported', true)]
            ]);
            return;
        }

        // log the sucess payment in blesta logs
        $this->log('validate', json_encode($get), 'input', !empty($get));
        //Tell Epay API Gateway that we have received the payment
        echo 'success';


        return $this->handleEPayOrder($get);
    }

    /**
     * Returns data regarding a success transaction. This method is invoked when
     * a client returns from the non-merchant gateway's web site back to Blesta.
     * Most of the part of this function will be same as $this->validate()
     * however, we don't trust client since they might do the return attack.
     * Extra layer of security is done by requesting EPay API Gateway to confrim.
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
        $api = $this->getApi($this->ePayConfig);
        //From raw get data verify EPay sign
        $sign_result = $api->verifyReturnBlesta($get);
        if($sign_result == false) {
            //Throw error when sign validation failed
            $this->Input->setErrors([
                'event' => ['invalid_sign' => Language::_('Epay.!error.event.invalid_sign', true)]
            ]);
            return; 
        }
        // Discard all webhook events, except when the order is completed or approved
        if ($get['trade_status'] != 'TRADE_SUCCESS') {
            //Throw error event for unsuccessful payment result
            $this->Input->setErrors([
                'event' => ['unsupported' => Language::_('Epay.!error.event.unsupported', true)]
            ]);
            return;
        }
        //Send a extra request to API Gateway to make sure gateway really get the payment
        $isPaid = $api->orderStatus($get['trade_no'] ?? null);
        if(!$isPaid){
            //user return success but gateway not receive payment???
            //Suspicous! Not accept this request.
            $this->Input->setErrors([
                'event' => ['fake_success_payment' => Language::_('Epay.!error.event.fake_success_payment', true)]
            ]);
            return;
        }


        // log the sucess payment in blesta logs
        $this->log('validate', json_encode($get), 'input', !empty($get));
        return $this->handleEPayOrder($get);
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
        //TO-DO Add automatic refund feature
        // Method is unsupported for now
        if (isset($this->Input))
            $this->Input->setErrors($this->getCommonError("unsupported"));
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
        // Method is unsupported for now
        if (isset($this->Input))
            $this->Input->setErrors($this->getCommonError("unsupported"));
    }

    /**
     * Loads the given API if not already loaded
     *
     * @param array $ePayConfig The EPay configuration
     */
    private function getApi($config)
    {
        return new EpayCore($config);
    }

    /**
     * Validates if the provided API Key is valid
     *
     * @param string $pid The merchant ID
     * @param string $key The API key
     * @param string $apiurl the EPay gateway API URL
     * @return bool True if the API Key is valid, false otherwise
     */
    public function validateConnection($pid, $key, $apiurl)
    {
        try {
            $ePayConfigTemp = [
                'pid' => $pid,
                'key' => $key,
                'apiurl' => $apiurl
            ];
            // Initialize API
            $api = $this->getApi($ePayConfigTemp);
            $merchantInfo = $api->queryMerchant($pid, $key, $apiurl);

            if(!empty($merchantInfo) && !empty($merchantInfo['code'])){
                if($merchantInfo['code'] == 1){
                    return true;
                }elseif($merchantInfo['code'] == -3){
                    //API Credential is invalid.
                    $this->Input->setErrors(['create' => ['response' => 'EPay API Gateway return code ' . $merchantInfo['code'] . "\nPlease check you API Key and Merchant ID"]]);
                    return false;
                }else{
                    $this->Input->setErrors(['create' => ['response' => 'EPay API Gateway return code ' . $merchantInfo['code']]]);
                    return false;
                }
            }
            $this->Input->setErrors(['create' => ['response' => 'Failed to connect to EPay API Gateway']]);
            return false;

        } catch (Throwable $e) {
            $this->Input->setErrors(['create' => ['response' => $e->getMessage()]]);
            return false;
        }
    }

    /**
     * Validate pid. api url and key comes from meta data
     *
     * @param string $file The configuration file to load
     */
    public function validatePid($pid, $key, $apiurl){
        return $this->validateConnection($pid, $key, $apiurl);
    }
    /**
     * Validate api url. pid and key comes from meta data
     *
     * @param string $file The configuration file to load
     */
    public function validateApiurl($apiurl, $pid, $key){
        return $this->validateConnection($pid, $key, $apiurl);
    }
    /**
     * Validate key. pid and api url comes from meta data
     *
     * @param string $file The configuration file to load
     */
    public function validateKey($key, $pid, $apiurl){
        return $this->validateConnection($pid, $key, $apiurl);
    }

}
