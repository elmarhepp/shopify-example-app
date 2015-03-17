<?php namespace App\Http\Controllers;

use App\Models\ApplicationCharge;
use App\Models\Authorization;
use App\MyClasses\ShopifyApi;
use App\MyClasses\ShopifyAuthenticationException;
use App\MyClasses\ShopifyException;
use App\MyClasses\ShopifyHelper;
use Exception;
use Input;
use Log;
use Request;
use Session;
use View;

/*
|--------------------------------------------------------------------------
| Controller of the Shopify Example App
|--------------------------------------------------------------------------
*/
class MyController extends Controller
{

    private $shopifyApi;
    private $shopifyHelper;
    private $data;
    private $countryTaxes;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->shopifyApi = new ShopifyApi();
        $this->shopifyHelper = new ShopifyHelper();
        $this->data = array();
        $this->countryTaxes = null;
    }


    /**
     * Index
     * @return \Illuminate\View\View
     * @throws Exception
     * @throws \Exception
     */
    public function index()
    {
//        Log::debug("MyController.index");
        $allInput = \Input::all();
        Log::debug("MyController.index: ### allInput = " . json_encode($allInput));

        try {
            // #### STEP 3a
            // # click on app
            // Check for session parameter shop and token
            if (Session::has('shop') && Session::has('token')) {
                $checkDB = $this->shopifyApi->checkSessionAndInstallation("MyController.index");
                if (!$checkDB) {
                    // remove session
                    Session::remove('shop');
                    Session::remove('token');
                    Session::remove('shopId');
                    Session::remove('chargeStatus');
                    Log::info("MyController.index: remove session and restart again");
                    return $this->index();
                }

//                $shopName = Session::get('shop');
                if (Input::has('signature')) {
                    Log::debug('MyController.index 1: start');
                    return $this->products();
                } else {
//                Log::debug('MyController.index 2: remove session and restart');
                    Log::debug('MyController.index 2: no signature');
                    return $this->products();
                }
            }


            // #### STEP 2
            // app installation part 2
            // check if the code parameter has been sent from Shopify
            if (Input::has('code') && Input::has('shop')) {
                Log::debug('MyController.index 3: has code and shop: ' . Input::get('shop'));
                // ready for step 2: access token
//                $this->shopifyHelper->sendEmail("Install App Manual Order: run getAccessToken for shopName = " . Input::get('shop'),
//                    'Shopify Manual Order', Input::get('shop'));

                return $this->shopifyApi->getAccessToken();
            }


            // check if the shop parameter has been sent
            if (Input::has('shop')) {

                // check if shop is already in DB
                $shop = Input::get('shop');
//              $dbShop = DB::table('authorizations')->where('shop', $shop)->first();
                $dbShop = Authorization::whereRaw('shop = ?', array($shop))->get()->first();
//              Log::debug('MyController.index 4: DB query for shop name: dbShop = ' . print_r($dbShop, true));

                if (!empty($dbShop)) {

                    $shopDomain = $dbShop->shop;
                    $shopToken = $dbShop->token;
                    Log::debug("MyController.index 5: get shop from DB: shop=$shopDomain, token=$shopToken");
                    if (!empty($shopDomain) && !empty($shopToken)) {
                        // set session
                        Session::set('token', $shopToken);
                        Session::set('shop', $shopDomain);

                        Log::debug('MyController.index 6: products');
                        return $this->products();
                    }
                }

                Log::debug('MyController.index 7: has no shop: installApp #####');
                // #### STEP 1
                // app installation part 1
                return $this->shopifyApi->installApp();
            }
        } catch (ShopifyAuthenticationException $sae) {
            $shopDomain = $this->shopifyHelper->findShopName();
            $message = "MyController.index: catch ShopifyAuthenticationException: redirect to index";
            Log::error($message);
            $this->shopifyHelper->sendEmail($message, 'ShopifyAuthenticationException', $shopDomain);
            // remove session
            Session::remove('shop');
            Session::remove('token');
            Session::remove('shopId');
            Session::remove('chargeStatus');
            Log::info("MyController.index: remove session and restart again");
            return $this->index();

        } catch (ShopifyException $se) {
            $shopDomain = $this->shopifyHelper->findShopName();
            $message = "MyController.index: catch ShopifyException: " . $se->getMessage();
            Log::error($message);
            $this->shopifyHelper->sendEmail($message, 'ShopifyException', $shopDomain);
            return $this->index();
        } catch (Exception $e) {
            $shopDomain = $this->shopifyHelper->findShopName();
            $message = "MyController.index: catch Exception: " . $e->getMessage();
            Log::error($message);
            $this->shopifyHelper->sendEmail($message, 'Exception', $shopDomain);
            throw $e;
        }

        Log::debug('MyController.index 8: has no shop, goto home');
        // ask for shop name to start installation: ## STEP 0
        return $this->home();
    }


    /**
     * authorize, goto index
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function authorize()
    {
        $shop = Input::get('shop');
        Log::info("MyController.authorize for shop $shop");

        return $this->index();
    }

    /**
     * home
     * @return \Illuminate\View\View
     */
    public function home()
    {
        Log::debug('MyController.home');
        return view('shopify.home');
    }


    /**
     * get shop url
     * @return View
     */
    public function getShopName()
    {
        Log::debug('MyController.getShopName');
        return view('shopify.getShopName');
    }


    /**
     * Show products
     * @return $this
     */
    public function products()
    {
        Log::debug('MyController.products ########################');

        $this->data = $this->shopifyApi->checkAuthorization('MyController.products');

        // check charges
        list($chargeStatus, $result) = $this->processApplicationCharge();
        if ($chargeStatus != 'active') {
            Log::debug("MyController.start chargeStatus = $chargeStatus");
            return $result;
        }

        $productList = $this->shopifyApi->call('GET', '/admin/products.json', array('published_status' => 'published', 'limit' => 50));
//        Log::debug('MyController.start productList = ' . json_encode($productList));

        // shopURL
        $shopURL = $this->shopifyHelper->findShopName();
        Log::debug("MyController.products shopURL = $shopURL");

        return View::make('shopify.products')
            ->with('data', $this->data)
            ->with('shopURL', $shopURL)
            ->with('productList', $productList);
    }


    /**
     * process the application charge
     * @return $this|MyController
     */
    private function processApplicationCharge()
    {
        Log::debug("MyController.processApplicationCharge");
        $result = '';

        // get app charge status
        list($chargeStatus, $confirmationURL) = $this->checkApplicationCharge();
        Log::debug("MyController.processApplicationCharge: chargeStatus=$chargeStatus");
        $shopId = $this->shopifyHelper->getShopId('MyController.processApplicationCharge');

        // redirect to application charge confirmation url
        if ($chargeStatus == 'pending') {
            Log::debug("MyController.processApplicationCharge redirect to url = $confirmationURL");

            $result = View::make('shopify.confirmCharges')
                ->with('data', $this->data)
                ->with('confirmationURL', $confirmationURL)
                ->with('useConfirmationURL', 'true');
//            return redirect($confirmationURL);
        }

        if ($chargeStatus == 'accepted') {
            Log::debug("MyController.processApplicationCharge accepted for shopId=$shopId => start");
            $this->shopifyApi->activateApplicationCharge();
            $result = $this->products();
        }

        if ($chargeStatus == 'declined') {
            Log::debug("MyController.confirmCharges declined for shopId=$shopId => frozen");
            $result = $this->frozen();
        }

        if ($chargeStatus == 'active') {
            Log::debug("MyController.confirmCharges active for shopId=$shopId");
        }
        return array($chargeStatus, $result);
    }


    /**
     * check application charge
     */
    private function checkApplicationCharge()
    {
        Log::debug("MyController.checkApplicationCharge");
//        Log::debug("MyController.checkApplicationCharge: session = " . json_encode(Session::all()));
        $confirmationURL = '';
        $shopId = $this->shopifyHelper->getShopId('MyController.checkApplicationCharge');

        // check session
        if (Session::has('chargeStatus') && Session::get('chargeStatus') == 'active') {
            $chargeStatus = 'active';
        } else {
            $chargeStatus = $this->shopifyApi->checkApplicationCharge();
        }

        if ($chargeStatus == 'pending') {
            $appCharge = ApplicationCharge::whereRaw('shop_id = ?', array($shopId))->get()->first();
            if ($appCharge != null) {
                $confirmationURL = $appCharge['confirmation_url'];
            }
        }

        if ($chargeStatus == 'active' && !Session::has('chargeStatus')) {
            Session::set('chargeStatus', 'active');
        }

        Log::debug("MyController.checkApplicationCharge chargeStatus=$chargeStatus, url=$confirmationURL for shopId=$shopId");
        return array($chargeStatus, $confirmationURL);
    }


    /**
     * Return url from confirmation of app billing charges
     * @return $this
     */
    public function confirmCharges()
    {
        Log::debug("MyController.confirmCharges");
        $this->data = $this->shopifyApi->checkAuthorization('MyController.confirmCharges');

        // check application charge
        list($chargeStatus, $result) = $this->processApplicationCharge();
        if ($chargeStatus != 'active') {
            Log::debug("MyController.confirmCharges chargeStatus = $chargeStatus");
            return $result;
        }

        $shopURL = $this->shopifyHelper->findShopName();
        Log::debug("MyController.confirmCharges shopURL = $shopURL");

        return $this->products();
    }


    /**
     * frozen application charge
     * @return $this
     */
    public function frozen()
    {
        $this->data = $this->shopifyApi->checkAuthorization('MyController.frozen');

        return View::make('home.frozen')
            ->with('data', $this->data);
    }


    /**
     * Webhook for uninstalling the app
     */
    public function webhookAppUninstall()
    {
        $this->shopifyApi->checkAuthorization('MyController.webhookAppUninstall');
        if ($this->shopifyApi->verifyWebhook('webhookAppUninstall')) {
            $shopName = Request::header('X-Shopify-Shop-Domain');
            $topic = Request::header('X-Shopify-Topic');
            Log::info("AppController.webhookAppUninstall for shopName = $shopName, topic = $topic");
//          Log::debug("AppController.webhookAppUninstall with data = " . print_r(Input::all(), true));

            $this->shopifyApi->deleteShopInDB($shopName);
        }
    }

}


