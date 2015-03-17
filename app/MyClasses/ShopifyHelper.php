<?php namespace App\MyClasses;

use App\Models\Authorization;
use Exception;
use Input;
use Log;
use Mail;
use Session;


/**
 * Created by PhpStorm.
 * User: elmarhepp
 * Date: 20/12/14
 * Time: 07:51
 */
class ShopifyHelper
{
    private $shopifyClient;

    public function __construct()
    {
        $this->shopifyClient = new ShopifyClient(null, null, null, null);
    }

    /**
     * Get the DB shopId
     * @param string $origin
     * @param string $shopName
     * @return int|mixed
     */
    public function getShopId($origin, $shopName = '')
    {
        $shopId = -1;
        if (Session::has('shop')) {
            $shopName = Session::get('shop');
        }
        if (Input::has('shopURL')) {
            $shopName = Input::get('shopURL');
        }

        if (!empty($shopName)) {
            $shop = Authorization::whereRaw('shop = ?', array($shopName))->first();
//                Log::debug("ShopifyHelper.getShopId: shop = $shopName, shopDB =" . print_r($shop, true) . '.');
            if ($shop != null) {
                $shopId = $shop->id;
            }
        }

        if ($shopId == -1) {
            Log::error("ShopifyHelper.getShopId ($origin): Do not find shopId for shop = $shopName");
            Log::debug("ShopifyHelper.getShopId session parameters are " . json_encode(Session::all()));
        }
        Log::debug("ShopifyHelper.getShopId ($origin): shop = $shopName, shopId=$shopId" . '.');
        return $shopId;
    }


    /**
     * get shop name
     * @return mixed|string
     */
    public function findShopName()
    {
        $shopDomain = '';
        if (Input::has('shop')) {
            $shopDomain = Input::get('shop');
        }
        if (Session::has('shop')) {
            $shopDomain = Session::get('shop');
        }
        if (Input::has('shopURL')) {
            $shopDomain = Input::get('shopURL');
        }
        return $shopDomain;
    }


    /**
     * send an email
     * @param $text
     * @param $title
     * @param $shopDomain
     */
    public function sendEmail($text, $title = 'Shopify Example App', $shopDomain = '')
    {
        if (Session::has('shop')) {
            $shopDomain = Session::get('shop');
        }
        $data = array(
            'text' => $text,
            'title' => $title,
            'shop' => $shopDomain
        );
        Mail::send('shopify.email', $data, function ($message) {
            $message
                ->to(env('MAIL_RECEIVER'), env('MAIL_RECEIVER_NAME'))
                ->from('shopify.message.123@web.com', 'Shopify Example App')
                ->subject('Shopify Example App');
        });
    }


    /**
     * call url
     * @param $method
     * @param $url
     * @param array $params
     * @param $request_headers
     * @return mixed
     * @throws Exception
     */
    public function callUrl($method, $url, $params = array(), $request_headers = "")
    {
        $query = in_array($method, array('GET', 'DELETE')) ? $params : array();
        $payload = in_array($method, array('POST', 'PUT')) ? stripslashes(json_encode($params)) : array();
        $request_headers2 = in_array($method, array('POST', 'PUT')) ? array("Content-Type: application/json; charset=utf-8", 'Expect:') : array();

        if (!empty($request_headers)) {
            $request_headers2[] = $request_headers;
        }

        list($response1, $headers) = $this->shopifyClient->callWithHeader($method, $url, $query, $payload, $request_headers2);

        // check header
        if ($headers['http_status_code'] >= 400) {
            Log::error("ShopifyHelper.callUrl http_status_code = " . $headers['http_status_code']);
        }

        Log::debug("ShopifyHelper.callUrl method=$method, url=$url, param=" . json_encode($params));
        Log::debug("ShopifyHelper.callUrl headers=" . json_encode($headers));
        Log::debug("ShopifyHelper.callUrl response1=$response1");

        return array($response1, $headers);
    }

}
