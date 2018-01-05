<?php
/**
 * CoinPayments.net, based on the PayPal Payments Standard plugin
 *
 * @package blesta
 * @subpackage blesta.components.gateways.coinpayments
 * @copyright Copyright (c) 2010, Phillips Data, Inc. Copyright (c) 2014 CoinPayments.net
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class CoinPayments extends NonmerchantGateway {
	/**
	 * @var string The version of this gateway
	 */
	private static $version = "1.0.0";
	/**
	 * @var string The authors of this gateway
	 */
	private static $authors = array(array('name'=>"CoinPayments.net",'url'=>"https://www.coinpayments.net"), array('name'=>"Phillips Data, Inc.",'url'=>"http://www.blesta.com"));
	/**
	 * @var array An array of meta data for this gateway
	 */
	private $meta;	
	
	/**
	 * Construct a new merchant gateway
	 */
	public function __construct() {
		
		// Load components required by this gateway
		Loader::loadComponents($this, array("Input"));
		
		// Load the language required by this gateway
		Language::loadLang("coin_payments", null, dirname(__FILE__) . DS . "language" . DS);
	}
	
	/**
	 * Returns the name of this gateway
	 *
	 * @return string The common name of this gateway
	 */
	public function getName() {
		return Language::_("CoinPayments.name", true);
	}
	
	/**
	 * Returns the version of this gateway
	 *
	 * @return string The current version of this gateway
	 */
	public function getVersion() {
		return self::$version;
	}

	/**
	 * Returns the name and URL for the authors of this gateway
	 *
	 * @return array The name and URL of the authors of this gateway
	 */
	public function getAuthors() {
		return self::$authors;
	}
	
	/**
	 * Return all currencies supported by this gateway
	 *
	 * @return array A numerically indexed array containing all currency codes (ISO 4217 format) this gateway supports
	 */
	public function getCurrencies() {
		$ret = array("BTC", "LTC", "USD", "EUR", "AUD", "BRL", "CAD", "CHF", "CNY", "CZK", "GBP", "HKD", "JPY", "KRW", "LAK", "MXN", "NZD", "PLN", "RUB", "SEK", "SGD", "TWD", "THB", "ZAR");
		sort($ret);
		return $ret;
	}
	
	/**
	 * Sets the currency code to be used for all subsequent payments
	 *
	 * @param string $currency The ISO 4217 currency code to be used for subsequent payments
	 */
	public function setCurrency($currency) {
		$this->currency = $currency;
	}
	
	/**
	 * Create and return the view content required to modify the settings of this gateway
	 *
	 * @param array $meta An array of meta (settings) data belonging to this gateway
	 * @return string HTML content containing the fields to update the meta data for this gateway
	 */
	public function getSettings(array $meta=null) {
		$this->view = $this->makeView("settings", "default", str_replace(ROOTWEBDIR, "", dirname(__FILE__) . DS));

		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));

		$this->view->set("meta", $meta);
		
		return $this->view->fetch();
	}
	
	/**
	 * Validates the given meta (settings) data to be updated for this gateway
	 *
	 * @param array $meta An array of meta (settings) data to be updated for this gateway
	 * @return array The meta data to be updated in the database for this gateway, or reset into the form on failure
	 */
	public function editSettings(array $meta) {
		// Verify meta data is valid
		$rules = array();

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
	public function encryptableFields() {
		return array("account_id", "merchant_id", "ipn_secret");
	}
	
	/**
	 * Sets the meta data for this particular gateway
	 *
	 * @param array $meta An array of meta data to set for this gateway
	 */
	public function setMeta(array $meta=null) {
		$this->meta = $meta;
	}
	
	/**
	 * Returns all HTML markup required to render an authorization and capture payment form
	 *
	 * @param array $contact_info An array of contact info including:
	 * 	- id The contact ID
	 * 	- client_id The ID of the client this contact belongs to
	 * 	- user_id The user ID this contact belongs to (if any)
	 * 	- contact_type The type of contact
	 * 	- contact_type_id The ID of the contact type
	 * 	- first_name The first name on the contact
	 * 	- last_name The last name on the contact
	 * 	- title The title of the contact
	 * 	- company The company name of the contact
	 * 	- address1 The address 1 line of the contact
	 * 	- address2 The address 2 line of the contact
	 * 	- city The city of the contact
	 * 	- state An array of state info including:
	 * 		- code The 2 or 3-character state code
	 * 		- name The local name of the country
	 * 	- country An array of country info including:
	 * 		- alpha2 The 2-character country code
	 * 		- alpha3 The 3-cahracter country code
	 * 		- name The english name of the country
	 * 		- alt_name The local name of the country
	 * 	- zip The zip/postal code of the contact
	 * @param float $amount The amount to charge this contact
	 * @param array $invoice_amounts An array of invoices, each containing:
	 * 	- id The ID of the invoice being processed
	 * 	- amount The amount being processed for this invoice (which is included in $amount)
	 * @param array $options An array of options including:
	 * 	- description The Description of the charge
	 * 	- return_url The URL to redirect users to after a successful payment
	 * 	- recur An array of recurring info including:
	 * 		- start_date The date/time in UTC that the recurring payment begins
	 * 		- amount The amount to recur
	 * 		- term The term to recur
	 * 		- period The recurring period (day, week, month, year, onetime) used in conjunction with term in order to determine the next recurring payment
	 * @return mixed A string of HTML markup required to render an authorization and capture payment form, or an array of HTML markup
	 */
	public function buildProcess(array $contact_info, $amount, array $invoice_amounts=null, array $options=null) {
		
		$amount = round($amount, 8);
		if (isset($options['recur']['amount']))
			$options['recur']['amount'] = round($options['recur']['amount'], 8);
		
		$post_to = 'https://www.coinpayments.net/index.php';

		// An array of key/value hidden fields to set for the payment form
		$fields = array(
			'cmd' => "_pay",
			'reset' => '1',
			'merchant' => $this->ifSet($this->meta['merchant_id']),
			'item_name' => $this->ifSet($options['description']),
			'amountf' => $amount,
			'currency' => $this->currency,
			'ipn_url' => Configure::get("Blesta.gw_callback_url") . Configure::get("Blesta.company_id") ."/coin_payments/?client_id=" . $this->ifSet($contact_info['client_id']),
			'success_url' => $this->ifSet($options['return_url']),
			'allow_extra' => 0, // no buyer notes
			'want_shipping' => 0, // no buyer shipping info
			'first_name' => $this->ifSet($contact_info['first_name']),
			'last_name' => $this->ifSet($contact_info['last_name']),
			'address1' => $this->ifSet($contact_info['address1']),
			'address2' => $this->ifSet($contact_info['address2']),
			'city' => $this->ifSet($contact_info['city']),
			'country' => $this->ifSet($contact_info['country']['alpha2']),
			'zip' => $this->ifSet($contact_info['zip']),
		);
		
		// Set state if US
		if ($this->ifSet($contact_info['country']['alpha2']) == "US")
			$fields['state'] = $this->ifSet($contact_info['state']['code']);

		// Set all invoices to pay
		if (isset($invoice_amounts) && is_array($invoice_amounts))
			$fields['custom'] = $this->serializeInvoices($invoice_amounts);
		
		// Build recurring payment fields
		/*
		$recurring_fields = array();
		if ($this->ifSet($options['recur']) && $this->ifSet($options['recur']['amount']) > 0) {
			$recurring_fields = $fields;
			unset($recurring_fields['amount']);
			
			$t3 = null;
			// PayPal calls 'term' 'period' and 'period' 'term'...
			switch ($this->ifSet($options['recur']['period'])) {
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
			if ($this->ifSet($options['recur']['start_date']) &&
				($day_diff = floor((strtotime($options['recur']['start_date']) - time())/(60*60*24))) > 0) {
				
				$recurring_fields['p1'] = $day_diff;
				$recurring_fields['t1'] = "D";
			}
			else {
				$recurring_fields['p1'] = $this->ifSet($options['recur']['term']);
				$recurring_fields['t1'] = $t3;
			}
			$recurring_fields['a3'] = $this->ifSet($options['recur']['amount']);
			$recurring_fields['p3'] = $this->ifSet($options['recur']['term']);
			$recurring_fields['t3'] = $t3;
			$recurring_fields['custom'] = null;
			$recurring_fields['modify'] = $this->ifSet($this->meta['modify']) == "true" ? 1 : 0;
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

		switch ($this->ifSet($this->meta['pay_type'])) {
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
	 * Builds the HTML form
	 *
	 * @param string $post_to The URL to post to
	 * @param array $fields An array of key/value input fields to set in the form
	 * @param boolean $recurring True if this is a recurring payment request, false otherwise
	 * @return string The HTML form
	 */
	private function buildForm($post_to, $fields, $recurring=false) {
		$this->view = $this->makeView("process", "default", str_replace(ROOTWEBDIR, "", dirname(__FILE__) . DS));

		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));

		$this->view->set("post_to", $post_to);
		$this->view->set("fields", $fields);
		$this->view->set("recurring", $recurring);
		
		return $this->view->fetch();
	}
	
	function errorAndDie($error_msg, $post) {
		$report = "IPN Error: ".$error_msg."\n\n";
		$report .= "POST Variables\n\n";
		foreach ($post as $k => $v) {
			$report .= "|$k| = |$v|\n";
		}
		$report .= "HTTP Auth User = |".$_SERVER['PHP_AUTH_USER']."|\n";
		$report .= "HTTP Auth Pass = |".$_SERVER['PHP_AUTH_PW']."|\n";
		$this->log($this->ifSet($_SERVER['REQUEST_URI']), serialize($report), "output", true);		
		die('IPN Error: '.$error_msg);
	}
	
	function check_ipn_request_is_valid($post) {
		$error_msg = "Unknown error";
		$auth_ok = false;
		
		if ($this->ifSet($post['ipn_mode']) == 'hmac') {
			if (isset($_SERVER['HTTP_HMAC']) && !empty($_SERVER['HTTP_HMAC'])) {
				$request = file_get_contents('php://input');
				if ($request !== FALSE && !empty($request)) {
					if ($this->ifSet($post['merchant']) == trim($this->ifSet($this->meta['merchant_id']))) {
						$hmac = hash_hmac("sha512", $request, trim($this->ifSet($this->meta['ipn_secret'])));
						if ($hmac == $_SERVER['HTTP_HMAC']) {
							$auth_ok = true;
						} else {
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
		} else if ($this->ifSet($post['ipn_mode']) == 'httpauth') {
			if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW']) && 
				$_SERVER['PHP_AUTH_USER'] == trim($this->ifSet($this->meta['merchant_id'])) && 
				$_SERVER['PHP_AUTH_PW'] == trim($this->ifSet($this->meta['ipn_secret']))) {
				$auth_ok = true;
			} else {
				$error_msg = "Invalid merchant id/ipn secret";
			}
		} else {
			$error_msg = "Unknown IPN mode!";
		}
		if (!$auth_ok) {
			$this->errorAndDie($error_msg, $post);
		}
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
	 *  	- id The ID of the invoice to apply to
	 *  	- amount The amount to apply to the invoice
	 * 	- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
	 * 	- reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	- transaction_id The ID returned by the gateway to identify this transaction
	 * 	- parent_transaction_id The ID returned by the gateway to identify this transaction's original transaction (in the case of refunds)
	 */
	public function validate(array $get, array $post) {			
		// Log request received
		$this->log($this->ifSet($_SERVER['REQUEST_URI']), serialize($post), "output", true);
		
		// Ensure IPN is verified, and validate that the merchant ID is correct
		$this->check_ipn_request_is_valid($post);
			
		$pmt_status = intval($this->ifSet($post['status'], '0'));
		
		if ($pmt_status >= 100 || $pmt_status == 1 || $pmt_status == 2) {
			$status = "approved";
		} else if ($pmt_status < 0) {
			die('Payment Timed Out or Error');
		} else {
			$status = "pending";
		}

		print "IPN OK: $status / $pmt_status";
		
		return array(
			'client_id' => $this->ifSet($get['client_id']),
			'amount' => $this->ifSet($post['amount1']),
			'currency' => $this->ifSet($post['currency1']),
			'status' => $status,
			'reference_id' => null,
			'transaction_id' => $this->ifSet($post['txn_id']),
			'parent_transaction_id' => '',
			'invoices' => $this->unserializeInvoices($this->ifSet($post['custom']))
		);
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
	 *  	- id The ID of the invoice to apply to
	 *  	- amount The amount to apply to the invoice
	 * 	- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
	 * 	- transaction_id The ID returned by the gateway to identify this transaction
	 * 	- parent_transaction_id The ID returned by the gateway to identify this transaction's original transaction
	 */
	public function success(array $get, array $post) {
		return array('status' => 'pending');
	}
	
	/**
	 * Refund a payment
	 *
	 * @param string $reference_id The reference ID for the previously submitted transaction
	 * @param string $transaction_id The transaction ID for the previously submitted transaction
	 * @param float $amount The amount to refund this transaction
	 * @param string $notes Notes about the refund that may be sent to the client by the gateway
	 * @return array An array of transaction data including:
	 * 	- status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
	 * 	- reference_id The reference ID for gateway-only use with this transaction (optional)
	 * 	- transaction_id The ID returned by the remote gateway to identify this transaction
	 * 	- message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
	 */
	public function refund($reference_id, $transaction_id, $amount, $notes=null) {
		$this->Input->setErrors($this->getCommonError("unsupported"));
		return array(
			'status' => "declined",
			'message' => 'CoinPayments does not support refunds.',
		);
	}
	
	/**
	 * Serializes an array of invoice info into a string
	 * 
	 * @param array A numerically indexed array invoices info including:
	 *  - id The ID of the invoice
	 *  - amount The amount relating to the invoice
	 * @return string A serialized string of invoice info in the format of key1=value1|key2=value2
	 */
	private function serializeInvoices(array $invoices) {
		$str = "";
		foreach ($invoices as $i => $invoice)
			$str .= ($i > 0 ? "|" : "") . $invoice['id'] . "=" . $invoice['amount'];
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
	private function unserializeInvoices($str) {
		$invoices = array();
		$temp = explode("|", $str);
		foreach ($temp as $pair) {
			$pairs = explode("=", $pair, 2);
			if (count($pairs) != 2)
				continue;
			$invoices[] = array('id' => $pairs[0], 'amount' => $pairs[1]);
		}
		return $invoices;
	}
}
?>