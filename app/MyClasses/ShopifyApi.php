<?php namespace App\MyClasses;

use App\Models\ApplicationCharge;
use App\Models\Authorization;
use Input;
use Log;
use Redirect;
use Session;
use View;


/**
 * This class has all shopify calls and authorization methods
 * User: elmarhepp
 * Date: 27/11/14
 */
class ShopifyApi
{

    private $shopifyApiKey;
    private $shopifySecret;
    private $shopifyScope;
    private $shopifyClient;
    private $shopifyModel;
    private $shopifyHelper;
    private $shopifyUseWebhooks;
    private $lastCallTimestamp;
    private $shopLocations;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->shopifyApiKey = env('SHOPIFY_API_KEY');
        $this->shopifySecret = env('SHOPIFY_SECRET');
        $this->shopifyScope = env('SHOPIFY_SCOPE');
        $this->shopifyUseWebhooks = env('SHOPIFY_USE_WEBHOOKS');
        $this->shopifyClient = new ShopifyClient(null, null, null, null);
        $this->shopifyHelper = new ShopifyHelper();
        $this->lastCallTimestamp = 0;
        $this->shopLocations = null;
    }


    /**
     * Install app for shop
     * ## STEP 1
     */
    public function installApp()
    {
        $shop = Input::get('shop');
        Log::info("ShopifyApi.installApp for shop $shop #####");

        if (empty($shop)) {
            // TODO not for installations from shopify
            $message = "ShopifyApi.installApp: Error because shop is empty: $shop";
            return View::make('errors.errors')->with('message', $message);
        }

        // ################################
        // STEP 1: install order

        $this->shopifyClient = new ShopifyClient($shop, "", $this->shopifyApiKey, $this->shopifySecret);

        // get the URL of the current page
        $redirectUrl = secure_url('authorize');
        Log::debug("ShopifyApi.installApp: redirectUrl=$redirectUrl");

        $authorizeUrl = $this->shopifyClient->getAuthorizeUrl($this->shopifyScope, $redirectUrl);

        // redirect to authorize url
        return Redirect::away($authorizeUrl);
    }


    /**
     * Get access token from shopify
     * ## STEP 2
     */
    public function getAccessToken()
    {
        $shop = Input::get('shop');
        $code = Input::get('code');

        Log::debug("ShopifyApi.getAccessToken for shop = $shop");

        $this->shopifyClient = new ShopifyClient($shop, "", $this->shopifyApiKey, $this->shopifySecret);

        // validate signature
        if (!$this->shopifyClient->validateSignature($_GET)) {
            $message = "ShopifyApi.getAccessToken:  Not valid Shopify request! Input = " . print_r(Input::all(), true);
            Log::error($message);
            return View::make('errors.errors')->with('message', $message);
        }

        // get the access token from shopify
        $token = $this->shopifyClient->getAccessToken($code);

        // token is empty
        if (empty($token)) {
            $message = "ShopifyApi.getAccessToken:  Shopify token is empty! Token = $token, Input = " . print_r(Input::all(), true);
            Log::error($message);
            return View::make('errors.errors')->with('message', $message);
        }

        // save token to DB
        $this->saveShop($shop, $token);

        // unset and set session
        Session::set('token', $token);
        Session::set('shop', $shop);

        // check webhooks
        $this->checkWebhooks();

        // create application charge
        $this->createApplicationCharge();

        // show orders
        return Redirect::action('MyController@index');
    }


    /**
     * check if Webhooks are installed
     */
    private function checkWebhooks()
    {
        if (!$this->getWebhooksList()) {
            Log::info("ShopifyApi.checkWebhooks: register webhooks");

            // create webhooks for uninstalling app
            $this->createAppWebhooks();

        } else {
            Log::info("ShopifyApi.checkWebhooks: webhooks are already registered");
        }
    }


    /**
     * get a list of all webhooks
     * @return bool
     */
    private function getWebhooksList()
    {
        Log::debug('AppController.getWebhooksList');
        $result = false;

        // create webhook for creating products
        $data = $this->call('GET', '/admin/webhooks.json',
            array('webhook' => array('format' => 'json')));

//        Log::debug('AppController.getWebhooksList: data = ' . print_r($data, true));

        foreach ($data as $webhook) {
            $topic = $webhook['topic'];
            $created = $webhook['created_at'];

            if ($topic == 'app/uninstalled') {
                $result = true;
                Log::debug('ShopifyApi.getWebhooksList: are already installed');
            }
            Log::debug("ShopifyApi.getWebhooksList: topic = $topic, created = $created");
        }
        return $result;
    }


    /**
     * Create webhooks for product modifications to update the database
     */
    private function createAppWebhooks()
    {
        if ($this->shopifyUseWebhooks == 'true') {
            Log::info('ShopifyApi.createAppWebhooks: register webhooks');

            // create webhook for uninstalling
            $result = $this->call('POST', '/admin/webhooks.json',
                array('webhook' => array(
                    'topic' => 'app/uninstalled',
                    'address' => secure_url('webhookAppUninstall'),
                    'format' => 'json')));
            Log::info('ShopifyApi.createProductWebhooks: topic = app/uninstalled');

        } else {
            Log::debug('ShopifyApi.createProductWebhooks: do NOT register webhooks');
        }
    }


    public function deleteApplicationChargeTables($shopName)
    {
        $shopId = $this->shopifyHelper->getShopId('ShopifyModel.deleteApplicationChargeTables', $shopName);
        if ($shopId == -1) {
            Log::error("ShopifyModel.deleteApplicationChargeTables: found no shopId for shopName = $shopName");
        } else {

            //delete settings
            $appCharge = ApplicationCharge::whereRaw('shop_id = ?', array($shopId));
            if ($appCharge != null) {
                $appCharge->delete();
                Log::info("ShopifyModel.deleteApplicationChargeTables 1: for shopName = $shopName");
            }

            Log::info("ShopifyModel.deleteApplicationChargeTables 2: for shopName = $shopName");
        }
    }

    /**
     * Delete shop and token to database
     * @param $shop
     */
    public function deleteShop($shop)
    {
        Log::debug("ShopifyModel.deleteShop 1: for shop = $shop");

        // check shop in DB
        $count = Authorization::whereRaw('shop = ?', array($shop))->count();

        if ($count > 0) {
            Log::info("ShopifyModel.deleteShop 2: for shop = $shop");
            $authorization = Authorization::whereRaw('shop = ?', array($shop));
            if ($authorization != null) {
                $authorization->delete();
            }
        }

        // check insert in DB
        $count2 = Authorization::whereRaw('shop = ?', array($shop))->count();
        Log::debug("ShopifyModel.deleteShop 3: count1=$count, count2=$count2 for shop = $shop");
    }


    /**
     * Create application charge
     */
    private function createApplicationCharge()
    {
        Log::debug('ShopifyApi.createApplicationCharge');

        $appChargeName = env('APPLICATION_CHARGE_NAME', 'Manual Order Plan');
        $appChargePrice = env('APPLICATION_CHARGE_PRICE', 9.99);
        $appChargeTrialDays = env('APPLICATION_CHARGE_TRIAL_DAYS', 15);
        $appChargeTest = env('APPLICATION_CHARGE_TEST', false);
        $shopId = $this->shopifyHelper->getShopId('ShopifyApi.createApplicationCharge');

        // call details
        $details = array('recurring_application_charge' =>
            array(
                'name' => $appChargeName,
                'price' => $appChargePrice,
                'return_url' => url('confirmCharges'),
                'test' => $appChargeTest,
                'trial_days' => $appChargeTrialDays));

        // shopify call
        $appCharge = $this->call('POST', '/admin/recurring_application_charges.json', $details);
        Log::debug('ShopifyApi.createApplicationCharge shopify result = ' . json_encode($appCharge));
//        $appCharge = $result['recurring_application_charge'];
        $confirmationURL = $appCharge['confirmation_url'];

        // save the application charge
        $newCharge = new ApplicationCharge();
        $newCharge->shop_id = $shopId;
        $newCharge->charge_id = $appCharge['id'];
        $newCharge->activated_on = $appCharge['activated_on'];
        $newCharge->billing_on = $appCharge['billing_on'];
        $newCharge->cancelled_on = $appCharge['cancelled_on'];
        $newCharge->confirmation_url = $appCharge['confirmation_url'];
        $newCharge->name = $appCharge['name'];
        $newCharge->price = $appCharge['price'];
        $newCharge->return_url = $appCharge['return_url'];
        $newCharge->status = $appCharge['status'];
        $newCharge->test = $appCharge['test'];
        $newCharge->save();

        // redirect to confirmation_url
//        return redirect($confirmationURL);
    }


    /**
     * check if application charge is accepted
     * @return Redirect|string
     */
    public function checkApplicationCharge()
    {
        Log::debug('ShopifyApi.checkApplicationCharge');
        $status = 'do not know';
        $activated_on = null;
        $shopId = $this->shopifyHelper->getShopId('ShopifyApi.checkApplicationCharge');
        $appChargeDB = ApplicationCharge::whereRaw('shop_id = ?', array($shopId))->get()->first();
        if ($appChargeDB != null) {
            $charge_id = $appChargeDB->charge_id;
            $statusDB = $appChargeDB->status;

            Log::debug("ShopifyApi.checkApplicationCharge for shopId=$shopId, charge_id=$charge_id, statusDB=$statusDB");

            $url = "/admin/recurring_application_charges/$charge_id.json";

            // shopify call
            $appCharge = $this->call('GET', $url);
            Log::debug("ShopifyApi.checkApplicationCharge shopify result = " . json_encode($appCharge));
//            $appCharge = $result['recurring_application_charge'];

            $status = $appCharge['status'];
            $activated_on = $appCharge['activated_on'];
            $cancelled_on = $appCharge['cancelled_on'];
            Log::debug("ShopifyApi.checkApplicationCharge for shopId=$shopId, charge_id=$charge_id, status=$status, activated_on=$activated_on, cancelled_on=$cancelled_on");

            // update DB
            $appChargeDB->status = $status;
            $appChargeDB->activated_on = $activated_on;
            $appChargeDB->cancelled_on = $cancelled_on;
            $appChargeDB->save();
        }
        return $status;
    }


    /**
     * confirm application charge
     * @return string
     * @throws ShopifyAuthenticationException
     * @throws ShopifyException
     */
    public function confirmApplicationCharge()
    {
        Log::debug('ShopifyApi.confirmApplicationCharge');
        $status2 = 'do not know';
        $shopId = $this->shopifyHelper->getShopId('ShopifyApi.checkApplicationCharge');
        $appCharge = ApplicationCharge::whereRaw('shop_id = ?', array($shopId))->get()->first();
        if ($appCharge != null) {
            $confirmationURL = $appCharge['confirmation_url'];

            // redirect to confirmation_url
            return redirect($confirmationURL);
        }
    }


    /**
     * activate application charge
     * @throws ShopifyAuthenticationException
     * @throws ShopifyException
     */
    public function activateApplicationCharge()
    {
        Log::debug('ShopifyApi.activateApplicationCharge');
        $shopId = $this->shopifyHelper->getShopId('ShopifyApi.checkApplicationCharge');
        $appCharge = ApplicationCharge::whereRaw('shop_id = ?', array($shopId))->get()->first();
        if ($appCharge != null) {
            $charge_id = $appCharge->charge_id;
            // call details
            $details = array('recurring_application_charge' =>
                array(
                    'id' => $charge_id));
            $url = "/admin/recurring_application_charges/$charge_id/activate.json";

            //shopify call
            $activated = $this->call('POST', $url, $details);
            Log::debug("ShopifyApi.activateApplicationCharge shopify result = " . json_encode($activated));
        }
    }


    /**
     * Save shop and token to database
     * @param $shop
     * @param $token
     */
    private function saveShop($shop, $token)
    {
        Log::debug("ShopifyApi.saveShop 1: for shop = $shop and token = $token");

        // check shop in DB
        $count = Authorization::whereRaw('shop = ?', array($shop))->count();

        if ($count == 0) {
            Log::info("ShopifyApi.saveShop 2: for shop = $shop and token = $token");
            $authorization = new Authorization;
            $authorization->shop = $shop;
            $authorization->token = $token;
            $authorization->save();
        }

        // check insert in DB
        $count2 = Authorization::whereRaw('shop = ?', array($shop))->count();
        Log::debug("ShopifyApi.saveShop 3: count1=$count, count2=$count2 for shop = $shop and token = $token");
    }


    /**
     * Call the shopify call method
     * @param $method
     * @param $url
     * @param array $array
     * @return array|mixed
     * @throws ShopifyApiException
     * @throws ShopifyAuthenticationException
     * @throws ShopifyException
     */
    public function call($method, $url, $array = array())
    {
        Log::debug('ShopifyApi.call for ' . $url);
        $this->checkAuthorization('ShopifyApi.call');

        list($resultJson, $responseHeaders) = $this->shopifyClient->call($method, $url, $array);
//        Log::debug('ShopifyApi.call xx resultJson = ' . json_encode($resultJson));
//        Log::debug('ShopifyApi.call xx responseHeaders = ' . json_encode($responseHeaders));
        $result = json_decode($resultJson, true);

        // check errors
        if (isset($result['errors']) or ($responseHeaders['http_status_code'] >= 400)) {

//            Log::info("ShopifyApi.call for url=$url, responseHeaders=" . print_r($responseHeaders, true));
            Log::info("ShopifyApi.call for url=$url, resultJson=$resultJson.");

            Log::error("ShopifyApi.call: ERROR: " . json_encode($result['errors']) . ', responseHeaders = ' . print_r($responseHeaders, true));

            if (isset($result['errors']) && '[API] Invalid API key or access token (unrecognized login or wrong password)' == $result['errors']) {
                Log::error('ShopifyApi.call: Invalid API key');

                if (Session::has('shop')) {
                    $shop = Session::get('shop');
                    $this->deleteShopInDB($shop);
                }

                throw new ShopifyAuthenticationException($result['errors']);
            }
            Log::info("ShopifyApi.call: throw ShopifyException");
            throw new ShopifyException(json_encode($result['errors']));
        }

        // check call limits
        $this->limitCalls();

        $resultShift = (is_array($result) and (count($result) > 0)) ? array_shift($result) : $result;
        return $resultShift;
    }


    public function deleteShopInDB($shopName)
    {
        if (!empty($shopName)) {
            $this->deleteApplicationChargeTables($shopName);
            $this->deleteShop($shopName);

            if (Session::has('shop')) {
                Session::remove('shop');
            }
            if (Session::has('token')) {
                Session::remove('token');
            }
            if (Session::has('shopId')) {
                Session::remove('shopId');
            }
            if (Session::has('chargeStatus')) {
                Session::remove('chargeStatus');
            }
            if (!Session::has('shop')) {
                Log::info('ShopifyApi.deleteShopInDB: shop is deleted and removed from session');
            }
            Log::debug("ShopifyApi.deleteShopInDB: session = " . json_encode(Session::all()));
        }
    }

    /**
     * Check if request has authorization with session or shop-domain in DB
     * and set shopifyClient and session if necessary
     * @param $source
     * @return $this
     */
    public function checkAuthorization($source = '')
    {
        $data = array();
//        $session = Session::all();
//        $input = Input::all();
//        Log::debug("ShopifyApi:checkAuthorization ($source): session=" . json_encode($session) . ", input=" . json_encode($input));

        if (Session::has('shop') && Session::has('token')) {
            $shop = Session::get('shop');
            $token = Session::get('token');
            $this->shopifyClient = new ShopifyClient($shop, $token, $this->shopifyApiKey, $this->shopifySecret);
            $data['apiKey'] = $this->shopifyApiKey;
            $data['shop'] = $this->getShopUrl($shop);
            Log::debug("ShopifyApi:checkAuthorization: ($source) with session for shop $shop");
        } else {
            // has shop
            if (Input::has(array('shop', 'timestamp', 'signature'))) {
                $shop = Input::get('shop');
                $shopifyClient = new ShopifyClient($shop, "", $this->shopifyApiKey, $this->shopifySecret);

                //check validation
                if (!$shopifyClient->validateSignature($_GET)) {
                    $message = "ShopifyApi:checkAuthorization: ($source) validation failed for shop $shop";
                    Log::error($message);
                    return View::make('errors.errors')->with('message', $message);
                }

                // get shop token from DB
                $dbShop = Authorization::whereRaw('shop = ?', array($shop))->get()->first();
//                Log::debug("ShopifyApi:checkAuthorization: ($source) DB query for shopname: dbShop = " . print_r($dbShop, true));

                if (!empty($dbShop)) {

                    $shopDomain = $dbShop->shop;
                    $shopToken = $dbShop->token;
                    Log::debug("ShopifyApi:checkAuthorization: ($source) get shop from DB: shop=$shopDomain, token=$shopToken");
                    if (!empty($shopDomain) && !empty($shopToken)) {
                        // unset and set session
                        Session::set('token', $shopToken);
                        Session::set('shop', $shopDomain);

                        $this->shopifyClient = new ShopifyClient($shop, $shopToken, $this->shopifyApiKey, $this->shopifySecret);
                        $data['apiKey'] = $this->shopifyApiKey;
                        $data['shop'] = $this->getShopUrl($shopDomain);
                    }
                } else {
                    $message = "ShopifyApi:checkAuthorization: ($source) validation failed for shop $shop";
                    Log::error($message);
                    return View::make('errors.errors')->with('message', $message);
                }
            } // AJAX check
            elseif (Input::has('shopURL') && Session::has('_token')) {
                $sessionCsrf = Session::get('_token');
                $localCsrf = csrf_token();

                if ($sessionCsrf == $localCsrf) {
                    Log::debug("ShopifyApi:checkAuthorization with csrf check true");
                    $shop = Input::get('shopURL');

                    // get shop token from DB
                    $dbShop = Authorization::whereRaw('shop = ?', array($shop))->get()->first();
//                Log::debug("ShopifyApi:checkAuthorization: ($source) DB query for shopname: dbShop = " . print_r($dbShop, true));

                    if (!empty($dbShop)) {

                        $shopDomain = $dbShop->shop;
                        $shopToken = $dbShop->token;
                        Log::debug("ShopifyApi:checkAuthorization: ($source) get shop from DB: shop=$shopDomain, token=$shopToken");
                        if (!empty($shopDomain) && !empty($shopToken)) {
                            // unset and set session
                            Session::set('token', $shopToken);
                            Session::set('shop', $shopDomain);

                            $this->shopifyClient = new ShopifyClient($shop, $shopToken, $this->shopifyApiKey, $this->shopifySecret);
                            $data['apiKey'] = $this->shopifyApiKey;
                            $data['shop'] = $this->getShopUrl($shopDomain);
                        }
                    } else {
                        $message = "ShopifyApi:checkAuthorization: ($source) validation failed for shop $shop";
                        Log::error($message);
                        return View::make('errors.errors')->with('message', $message);
                    }
                } else {
                    Log::error("ShopifyApi:checkAuthorization with csrf check false: sessionCsrf=$sessionCsrf, localCsrf=$localCsrf");
                }

            } else {
                $message = "ShopifyApi:checkAuthorization: no session and no shop";
                Log::info($message);
                return View::make('errors.errors')->with('message', $message);
            }
        }
        Log::debug("ShopifyApi.checkAuthorization data = " . json_encode($data));
        return $data;
    }


    /**
     * Get URL with https for the shop
     * @param $aShop
     * @return string
     */
    private function getShopUrl($aShop)
    {
        $shop = $aShop;
        if (!strpos($aShop, "https://")) {
            $shop = "https://" . $aShop;
        }
        return $shop;
    }


    /**
     * limit the shopify calls
     */
    private function limitCalls()
    {
        if ($this->lastCallTimestamp > 0) {
            $callsLimit = $this->shopifyClient->callLimit();
            $callsMade = $this->shopifyClient->callsMade();
            $callsLeft = $this->shopifyClient->callsLeft();

            Log::debug("ShopifyApi.limitCalls: callsLimit=$callsLimit, callsMade=$callsMade, callsLeft=$callsLeft");
            $currentTimestamp = microtime(true);
            $deltaTimestamp = ($this->lastCallTimestamp > 0) ? $currentTimestamp - $this->lastCallTimestamp : 0;
            Log::debug("ShopifyApi.limitCalls: deltaTimestamp=$deltaTimestamp");

            if ($callsLeft < 10) {
                Log::debug("ShopifyApi.limitCalls: DELTA < 10:  wait 0.5 seconds");
                usleep(500000);
            }
        } else {
            Log::debug("ShopifyApi.limitCalls: first call");
        }
        $this->lastCallTimestamp = microtime(true);
    }


    /**
     * Initialize this class with a valid shopifyClient
     * @param $shop
     * @return bool
     */
    public function initialize($shop)
    {
        $result = false;
        Log::debug("ShopifyApi.initialize for shop = $shop");

        if (!empty($shop)) {
            $dbShop = Authorization::whereRaw('shop = ?', array($shop))->get()->first();
            if ($dbShop != null) {
                $token = $dbShop->token;
                $this->shopifyClient = new ShopifyClient($shop, $token, $this->shopifyApiKey, $this->shopifySecret);
                Log::info("ShopifyApi.initialize create ShopifyClient instance");
                $result = true;
            } else {
                Log::error("ShopifyApi.initialize: dbShop is null for shop = $shop");
            }
        } else {
            Log::error("ShopifyApi.initialize: shop is empty");
        }
        return $result;
    }

    /**
     * Check if the shop has session and is in DB
     * @param string $source
     * @return bool
     */
    public function checkSessionAndInstallation($source = '')
    {
        $result = false;
        $shop = '';
        if (Session::has('shop') && Session::has('token')) {
            $shop = Session::get('shop');
//            $token = Session::get('token');

            $dbShop = Authorization::whereRaw('shop = ?', array($shop))->get()->first();
            if (!empty($dbShop)) {
                $result = true;
            } else {
                Log::info("ShopifyApi.checkSessionAndInstallation ($source): shop $shop is not in DB");
            }
        }
        Log::debug("ShopifyApi.checkSessionAndInstallation ($source): shop $shop is in DB: result=$result");
        return $result;
    }


    /**
     * Verify webhook comes from shopify
     * @param $source
     * @return bool
     */
    public function verifyWebhook($source)
    {
        $hMac_header = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'];
        $data = file_get_contents('php://input');
        $calculated_hMac = base64_encode(hash_hmac('sha256', $data, $this->shopifySecret, true));
        $result = ($hMac_header == $calculated_hMac);
        if (!$result) {
            Log::error("ShopifyApi.verifyWebhook is FALSE for $source, hMac_header = $hMac_header,
                Input = $data");
        }
        return $result;
    }


}

