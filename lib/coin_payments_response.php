<?php
/**
 * CoinPayments API Response
 *
 * @package blesta
 * @subpackage blesta.components.gateways.coin_payments.lib
 * @copyright Copyright (c) 2026, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class CoinPaymentsResponse
{
    /**
     * @var string The HTTP status code of this response
     */
    private $status;
    /**
     * @var string The raw data from this response
     */
    private $raw;
    /**
     * @var mixed The formatted data from this response
     */
    private $response;
    /**
     * @var array A list of errors from the response data
     */
    private $errors = [];

    /**
     * Initializes the response
     *
     * @param array $api_response A list of response data including:
     *
     *  - status The HTTP status code returned by the API
     *  - content The data returned in the API response
     */
    public function __construct(array $api_response)
    {
        $this->status = $api_response['status'] ?? 0;
        $this->raw = $api_response['content'] ?? '';
        $this->response = json_decode($this->raw);

        if ($this->status < 200 || $this->status > 299) {
            $this->errors = ['api' => ['response' => $this->errorMessage()]];
        }
    }

    /**
     * Get the HTTP status code of this response
     *
     * @return string The status of this response
     */
    public function status()
    {
        return $this->status;
    }

    /**
     * Get the raw data from this response
     *
     * @return string The raw data from this response
     */
    public function raw()
    {
        return $this->raw;
    }

    /**
     * Get the formatted data from this response
     *
     * @return mixed The formatted data from this response
     */
    public function response()
    {
        return $this->response;
    }

    /**
     * Get any errors from this response
     *
     * @return array The errors from this response
     */
    public function errors()
    {
        return $this->errors;
    }

    /**
     * Builds an error message from the response data
     *
     * @return string The error message returned by the API
     */
    private function errorMessage()
    {
        // The API reports errors under a handful of keys depending on the failure
        foreach (['message', 'detail', 'title', 'error'] as $field) {
            if (!empty($this->response->{$field}) && is_scalar($this->response->{$field})) {
                return $this->response->{$field};
            }
        }

        return Language::_('CoinPayments.!error.api.internal', true, $this->status);
    }
}
