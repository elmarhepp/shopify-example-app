<?php namespace App\MyClasses;

use Exception;

class ShopifyClient
{
    public $shop_domain;
    private $token;
    private $api_key;
    private $secret;
    private $last_response_headers = null;

    public function __construct($shop_domain, $token, $api_key, $secret)
    {
        $this->name = "ShopifyClient";
        $this->shop_domain = $shop_domain;
        $this->token = $token;
        $this->api_key = $api_key;
        $this->secret = $secret;
    }

    // Get the URL required to request authorization
    public function getAuthorizeUrl($scope, $redirect_url = '')
    {
        $url = "https://{$this->shop_domain}/admin/oauth/authorize?client_id={$this->api_key}&scope="
            . urlencode($scope);
        if ($redirect_url != '') {
            $url .= "&redirect_uri=" . urlencode($redirect_url);
        }
        return $url;
    }

    // Once the User has authorized the order, call this with the code to get the access token
    public function getAccessToken($code)
    {
        // POST to  POST https://SHOP_NAME.myshopify.com/admin/oauth/access_token
        $url = "https://{$this->shop_domain}/admin/oauth/access_token";
        $payload = "client_id={$this->api_key}&client_secret={$this->secret}&code=$code";
        $response = $this->curlHttpApiRequest('POST', $url, '', $payload, array());
        $response = json_decode($response, true);
        if (isset($response['access_token']))
            return $response['access_token'];
        return '';
    }

    /**
     * @param $method GET, POST
     * @param $url
     * @param string $query Parameter array for GET
     * @param string $payload
     * @param array $request_headers
     * @return mixed
     * @throws Exception
     */
    public function curlHttpApiRequest($method, $url, $query = '', $payload = '', $request_headers = array())
    {
        $url = $this->curlAppendQuery($url, $query);
        $ch = curl_init($url);
        $this->curlSetopts($ch, $method, $payload, $request_headers);
        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($errno) throw new Exception($error, $errno);
        list($message_headers, $message_body) = preg_split("/\r\n\r\n|\n\n|\r\r/", $response, 2);
        $this->last_response_headers = $this->curlParseHeaders($message_headers);

        return $message_body;
    }

    /**
     * @param $method GET, POST
     * @param $url
     * @param string $query Parameter array for GET
     * @param string $payload
     * @param array $request_headers
     * @return mixed
     * @throws Exception
     */
    public function callWithHeader($method, $url, $query = '', $payload = '', $request_headers = array())
    {
        $url = $this->curlAppendQuery($url, $query);
        $ch = curl_init($url);
        $this->curlSetopts($ch, $method, $payload, $request_headers);
        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($errno) throw new Exception($error, $errno);
        list($message_headers, $message_body) = preg_split("/\r\n\r\n|\n\n|\r\r/", $response, 2);
        $this->last_response_headers = $this->curlParseHeaders($message_headers);

        return array($message_body, $this->last_response_headers);
    }

    private function curlAppendQuery($url, $query)
    {
        if (empty($query)) return $url;
        if (is_array($query)) return "$url?" . http_build_query($query);
        else return "$url?$query";
    }

    private function curlSetopts($ch, $method, $payload, $request_headers)
    {
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_USERAGENT, 'ohShopify-php-api-client');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if (!empty($request_headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);

        if ($method != 'GET' && !empty($payload)) {
            if (is_array($payload)) $payload = http_build_query($payload);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
    }

    private function curlParseHeaders($message_headers)
    {
        $header_lines = preg_split("/\r\n|\n|\r/", $message_headers);
        $headers = array();
        list(, $headers['http_status_code'], $headers['http_status_message']) = explode(' ', trim(array_shift($header_lines)), 3);
        foreach ($header_lines as $header_line) {
            list($name, $value) = explode(':', $header_line, 2);
            $name = strtolower($name);
            $headers[$name] = trim($value);
        }

        return $headers;
    }

    public function callsLeft($response_headers = '')
    {
        return $this->callLimit() - $this->callsMade();
    }

    public function callLimit()
    {
        return $this->shopApiCallLimitParam(1);
    }

    private function shopApiCallLimitParam($index)
    {
        if ($this->last_response_headers == null) {
            throw new Exception('Cannot be called before an API call.');
        }
        $params = explode('/', $this->last_response_headers['http_x_shopify_shop_api_call_limit']);
        return (int)$params[$index];
    }

    public function callsMade()
    {
        return $this->shopApiCallLimitParam(0);
    }

    public function call($method, $path, $params = array())
    {
        $baseurl = "https://{$this->shop_domain}/";

        $url = $baseurl . ltrim($path, '/');
        $query = in_array($method, array('GET', 'DELETE')) ? $params : array();
        $payload = in_array($method, array('POST', 'PUT')) ? stripslashes(json_encode($params)) : array();
        $request_headers = in_array($method, array('POST', 'PUT')) ? array("Content-Type: application/json; charset=utf-8", 'Expect:') : array();

        // add auth headers
        $request_headers[] = 'X-Shopify-Access-Token: ' . $this->token;

        $response1 = $this->curlHttpApiRequest($method, $url, $query, $payload, $request_headers);
//        $response2 = json_decode($response1, true);

        return array($response1, $this->last_response_headers);

//		if (isset($response['errors']) or ($this->last_response_headers['http_status_code'] >= 400)){
//            Log::error("ShopifyClient:call Error: " . print_r($response2, true));
//            throw new App\MyClasses\ShopifyApiException($method, $path, $params, $this->last_response_headers, $response2);
//        }
//
//		return (is_array($response2) and (count($response2) > 0)) ? array_shift($response2) : $response2;
    }

    public function validateSignature($query)
    {
        $seconds_in_a_day = 24 * 60 * 60;
        $older_than_a_day = $query['timestamp'] < (time() - $seconds_in_a_day);
        if ($older_than_a_day) return false;

        if (!is_array($query) || empty($query['signature']) || !is_string($query['signature']))
            return false;

        foreach ($query as $k => $v) {
            if ($k == 'signature') continue;
            $signature[] = $k . '=' . $v;
        }

        sort($signature);
        $signature = md5($this->secret . implode('', $signature));

        return $query['signature'] == $signature;
    }
}

class ShopifyCurlException extends Exception
{
}

class ShopifyApiException extends Exception
{
    protected $method;
    protected $path;
    protected $params;
    protected $response_headers;
    protected $response;

    function __construct($method, $path, $params, $response_headers, $response)
    {
        $this->method = $method;
        $this->path = $path;
        $this->params = $params;
        $this->response_headers = $response_headers;
        $this->response = $response;

        parent::__construct($response_headers['http_status_message'], $response_headers['http_status_code']);
    }

    function getMethod()
    {
        return $this->method;
    }

    function getPath()
    {
        return $this->path;
    }

    function getParams()
    {
        return $this->params;
    }

    function getResponseHeaders()
    {
        return $this->response_headers;
    }

    function getResponse()
    {
        return $this->response;
    }
}

?>
