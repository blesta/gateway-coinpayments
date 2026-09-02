<?php
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'coin_payments_response.php';

/**
 * CoinPayments API
 *
 * Implements the CoinPayments API, documented at https://docs.coinpayments.net/api/
 *
 * @package blesta
 * @subpackage blesta.components.gateways.coin_payments.lib
 * @copyright Copyright (c) 2026, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class CoinPaymentsApi
{
    /**
     * @var string The default URL of the CoinPayments API
     */
    const API_URL = 'https://a-api.coinpayments.net';

    /**
     * @var string The byte order mark the API prefixes to every signed message
     */
    const SIGNATURE_BOM = "\xEF\xBB\xBF";

    /**
     * @var string The ID of the API integration
     */
    private $client_id;

    /**
     * @var string The secret of the API integration
     */
    private $client_secret;

    /**
     * @var string The URL of the CoinPayments API
     */
    private $api_url;

    /**
     * @var array The last request made through this API
     */
    private $last_request = ['url' => null, 'params' => null];

    /**
     * @var array A cache of the currencies fetched from the API, keyed by symbol
     */
    private $currencies = [];

    /**
     * Initializes the API
     *
     * @param string $client_id The ID of the API integration
     * @param string $client_secret The secret of the API integration
     * @param string|null $api_url The URL of the CoinPayments API, defaults to the
     *  URL of instance A when not given
     */
    public function __construct($client_id, $client_secret, $api_url = null)
    {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->api_url = (empty($api_url) ? self::API_URL : $api_url);

        Loader::loadComponents($this, ['Net']);
    }

    /**
     * Creates a new invoice
     *
     * @param array $params A list of parameters for the invoice
     * @return CoinPaymentsResponse The API response
     */
    public function createInvoice(array $params)
    {
        return $this->apiRequest('POST', '/api/v2/merchant/invoices', $params);
    }

    /**
     * Fetches the currency matching the given ISO 4217 currency code
     *
     * @param string $symbol The ISO 4217 currency code to fetch
     * @return stdClass|null The currency, or null if the currency is not supported
     */
    public function getCurrency($symbol)
    {
        if (array_key_exists($symbol, $this->currencies)) {
            return $this->currencies[$symbol];
        }

        $this->currencies[$symbol] = null;

        // Currencies may be fetched anonymously, the request is not signed
        $response = $this->apiRequest(
            'GET',
            '/api/v1/currencies?' . http_build_query(['types' => 'fiat', 'q' => $symbol]),
            null,
            false
        );
        $currencies = $response->response();

        if (is_array($currencies)) {
            foreach ($currencies as $currency) {
                if (isset($currency->symbol) && $currency->symbol === $symbol) {
                    $this->currencies[$symbol] = $currency;
                    break;
                }
            }
        }

        return $this->currencies[$symbol];
    }

    /**
     * Generates the signature for the given request
     *
     * @param string $method The HTTP method of the request
     * @param string $url The full URL of the request
     * @param string $payload The raw body of the request
     * @param string $timestamp The timestamp of the request
     * @return string The signature of the request
     */
    public function signature($method, $url, $payload, $timestamp)
    {
        $message = self::SIGNATURE_BOM . $method . $url . $this->client_id . $timestamp . $payload;

        return base64_encode(hash_hmac('sha256', $message, $this->client_secret, true));
    }

    /**
     * Generates a timestamp in the format expected by the API
     *
     * @return string The current UTC time
     */
    public function timestamp()
    {
        return gmdate('Y-m-d\TH:i:s');
    }

    /**
     * Gets the last request made through this API
     *
     * @return array The last request, including:
     *
     *  - url The URL of the request
     *  - params The body of the request
     */
    public function lastRequest()
    {
        return $this->last_request;
    }

    /**
     * Sends a request to the API
     *
     * @param string $method The HTTP method of the request
     * @param string $endpoint The endpoint to send the request to
     * @param array $params A list of parameters to send with the request
     * @param bool $authenticated Whether the request must be signed
     * @return CoinPaymentsResponse The API response
     */
    private function apiRequest($method, $endpoint, array $params = null, $authenticated = true)
    {
        $url = $this->api_url . $endpoint;

        // The signature covers the body exactly as it is sent, it must only be encoded once
        $payload = ($params === null ? '' : json_encode($params, JSON_UNESCAPED_SLASHES));

        $this->last_request = ['url' => $url, 'params' => $payload];

        $headers = ['Accept: application/json'];
        if ($payload !== '') {
            $headers[] = 'Content-Type: application/json';
        }

        if ($authenticated) {
            $timestamp = $this->timestamp();
            $headers[] = 'X-CoinPayments-Client: ' . $this->client_id;
            $headers[] = 'X-CoinPayments-Timestamp: ' . $timestamp;
            $headers[] = 'X-CoinPayments-Signature: ' . $this->signature($method, $url, $payload, $timestamp);
        }

        // A new connection is used for every request, options set by one request would
        // otherwise carry over to the next
        $http = $this->Net->create('Http');
        $http->setHeaders($headers);

        $content = (string) $http->request($method, $url, ($payload === '' ? null : $payload));

        return new CoinPaymentsResponse(['status' => $http->responseCode(), 'content' => $content]);
    }
}
