<?php

namespace EEA_MC;

use Exception;

/**
 * Super-simple, minimum abstraction MailChimp API v3 wrapper
 * MailChimp API v3: http://developer.mailchimp.com
 * This wrapper: https://github.com/drewm/mailchimp-api
 *
 * @author  Drew McLellan <drew.mclellan@gmail.com>
 * @version 2.2
 */
class MailChimp
{
    /**
     * @var string
     */
    private $api_endpoint = 'https://<dc>.api.mailchimp.com/3.0';

    /**
     * @var string
     */
    private $api_key;

    /**
     * @var string
     */
    private $last_error = '';

    /**
     * @var array
     */
    private $last_response = [];

    /**
     * @var array
     */
    private $last_request = [];

    /**
     * @var bool
     */
    private $request_successful = false;

    /**
     * SSL Verification
     * Read before disabling:
     * http://snippets.webaware.com.au/howto/stop-turning-off-curlopt_ssl_verifypeer-and-fix-your-php-config/
     *
     * @var bool
     */
    public $verify_ssl = false;


    /**
     * Create a new instance
     *
     * @param string $api_key Your MailChimp API key
     * @throws Exception
     */
    public function __construct($api_key)
    {
        $this->api_key    = $api_key;
        $this->verify_ssl = apply_filters('FHEE__MailChimp__construct__verify_ssl', $this->verify_ssl);
        if (strpos($this->api_key, '-') === false) {
            throw new Exception("Invalid MailChimp API key `{$api_key}` supplied.");
        }
        list(, $data_center) = explode('-', $this->api_key);
        $this->api_endpoint = str_replace('<dc>', $data_center, $this->api_endpoint);
        $this->last_response = ['headers' => null, 'body' => null];
    }


    /**
     * Create a new instance of a Batch request. Optionally with the ID of an existing batch.
     *
     * @param string $batch_id Optional ID of an existing batch, if you need to check its status for example.
     * @return Batch            New Batch object.
     */
    public function new_batch($batch_id = null)
    {
        return new Batch($this, $batch_id);
    }


    /**
     * Convert an email address into a 'subscriber hash' for identifying the subscriber in a method URL
     *
     * @param string $email The subscriber's email address
     * @return  string          Hashed version of the input
     */
    public function subscriberHash($email)
    {
        return md5(strtolower($email));
    }


    /**
     * Was the last request successful?
     *
     * @return bool  True for success, false for failure
     */
    public function success()
    {
        return $this->request_successful;
    }


    /**
     * Get the last error returned by either the network transport, or by the API.
     * If something didn't work, this should contain the string describing the problem.
     *
     * @return  array|false  describing the error
     */
    public function getLastError()
    {
        return $this->last_error ? : false;
    }


    /**
     * Get an array containing the HTTP headers and the body of the API response.
     *
     * @return array  Assoc array with keys 'headers' and 'body'
     */
    public function getLastResponse()
    {
        return $this->last_response;
    }


    /**
     * Get an array containing the HTTP headers and the body of the API request.
     *
     * @return array  Assoc array
     */
    public function getLastRequest()
    {
        return $this->last_request;
    }


    /**
     * Make an HTTP DELETE request - for deleting data
     *
     * @param string $api_method  URL of the API request method
     * @param array  $args    Assoc array of arguments (if any)
     * @param int    $timeout Timeout limit for request in seconds
     * @return  array|false   Assoc array of API response, decoded from JSON
     * @throws Exception
     */
    public function delete($api_method, $args = [], $timeout = 10)
    {
        return $this->makeRequest('delete', $api_method, $args, $timeout);
    }


    /**
     * Make an HTTP GET request - for retrieving data
     *
     * @param string $api_method  URL of the API request method
     * @param array  $args    Assoc array of arguments (usually your data)
     * @param int    $timeout Timeout limit for request in seconds
     * @return  array|false   Assoc array of API response, decoded from JSON
     * @throws Exception
     */
    public function get($api_method, $args = [], $timeout = 10)
    {
        return $this->makeRequest('get', $api_method, $args, $timeout);
    }


    /**
     * Make an HTTP PATCH request - for performing partial updates
     *
     * @param string $api_method  URL of the API request method
     * @param array  $args    Assoc array of arguments (usually your data)
     * @param int    $timeout Timeout limit for request in seconds
     * @return  array|false   Assoc array of API response, decoded from JSON
     * @throws Exception
     */
    public function patch($api_method, $args = [], $timeout = 10)
    {
        return $this->makeRequest('patch', $api_method, $args, $timeout);
    }


    /**
     * Make an HTTP POST request - for creating and updating items
     *
     * @param string $api_method  URL of the API request method
     * @param array  $args    Assoc array of arguments (usually your data)
     * @param int    $timeout Timeout limit for request in seconds
     * @return  array|false   Assoc array of API response, decoded from JSON
     * @throws Exception
     */
    public function post($api_method, $args = [], $timeout = 10)
    {
        return $this->makeRequest('post', $api_method, $args, $timeout);
    }


    /**
     * Make an HTTP PUT request - for creating new items
     *
     * @param string $api_method  URL of the API request method
     * @param array  $args    Assoc array of arguments (usually your data)
     * @param int    $timeout Timeout limit for request in seconds
     * @return  array|false   Assoc array of API response, decoded from JSON
     * @throws Exception
     */
    public function put($api_method, $args = [], $timeout = 10)
    {
        return $this->makeRequest('put', $api_method, $args, $timeout);
    }


    /**
     * Performs the underlying HTTP request. Not very exciting.
     *
     * @param string $request_method The HTTP verb to use: get, post, put, patch, delete
     * @param string $api_method       The API method to be called
     * @param array  $args         Assoc array of parameters to be passed
     * @param int    $timeout
     * @return array|false         Assoc array of decoded result
     * @throws Exception
     */
    private function makeRequest($request_method, $api_method, $args = [], $timeout = 10)
    {
        if (! function_exists('curl_init') || ! function_exists('curl_setopt')) {
            throw new Exception("cURL support is required, but can't be found.");
        }
        $endpoint                 = $this->api_endpoint . '/' . $api_method;
        $this->last_error         = '';
        $this->request_successful = false;
        // Form the request.
        $request_parameters = [
            'method'      => $request_method,
            'timeout'     => $timeout,
            'redirection' => 5,
            'blocking'    => true,
            'sslverify'   => $this->verify_ssl
        ];
        $request_parameters['headers'] = [
            'User-Agent'    => 'Event Espresso Integration/MailChimp-API/3.0 (https://eventespresso.com/forum/mailchimp-integration/)',
            'Accept'        => 'application/vnd.api+json',
            'Content-Type'  => 'application/vnd.api+json',
            'Authorization' => 'Bearer ' . $this->api_key,
        ];
        // Add body if this is a POST request.
        if ($args && ($request_method === 'post' || $request_method === 'put' || $request_method === 'patch')) {
            $request_parameters['body'] = json_encode($args);
        }
        if ($args && $request_method === 'get') {
            $endpoint .= '?' . http_build_query($args, '', '&');
        }
        $this->last_request = $request_parameters;
        // Sent the API request.
        $response = wp_remote_request($endpoint, $request_parameters);
        if (isset($response['body']) && is_string($response['body'])) {
            $response['body'] = json_decode($response['body'], true);
        }
        $is_valid = $this->validateResponse($response);
        if (isset($response['headers']['request_header'])) {
            $this->last_request['headers'] = $response['headers']['request_header'];
        }
        return $response['body'];
    }


    /**
     * @param $response
     * @return bool
     */
    public function validateResponse($response): bool
    {
        if (is_wp_error($response)) {
            $this->last_error = sprintf(
                esc_html__('Response error. Message: %1$s.', 'event_espresso'),
                $response->get_error_messages()
            );
            return false;
        }
        // Do we have a response body ?
        if (! isset($response['body'])) {
            $this->last_error = esc_html__('No response body provided.', 'event_espresso');
            return false;
        }
        if (isset($response['body']['detail'])) {
            $this->last_error = sprintf('%d: %s', $response['body']['status'], $response['body']['detail']);
            return false;
        }
        $status = $this->findHTTPStatus($response);
        if ($status >= 200 && $status <= 299) {
            $this->request_successful = true;
            return true;
        }
        $this->last_error = 'Unknown error, call getLastResponse() to find out what happened.';
        return false;
    }


    /**
     * Find the HTTP status code from the headers or API response body
     *
     * @param array $response The response from the curl request
     * @return int  HTTP status code
     */
    private function findHTTPStatus($response): int
    {
        if (! empty($response['response']) && isset($response['response']['code'])) {
            return (int) $response['response']['code'];
        }
        if (! empty($response['body']) && isset($response['body']['status'])) {
            return (int) $response['body']['status'];
        }
        return 418;
    }
}
