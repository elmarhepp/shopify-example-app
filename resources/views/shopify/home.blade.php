@extends('shopify.layoutInstall')

@section('content')
    <div class="container_">
        <div class="panel panel-default">
            <div class="panel-heading_">
            </div>

            <div class="panel-body">
                <h3>Shopify Example App</h3>

                <div>
                    <h4>Features</h4>

                    <p>This is a simple example of a Shopify App using Laravel 5.</p>

                    <p>A detailed description about how to build a Shopify App see the official
                        <a href="http://docs.shopify.com/api/introduction/getting-started">Shopify documentation</a></p>

                    <p>This example demonstrates how to:</p>
                    <ul>
                        <li>install a Shopify App</li>
                        <li>make a simple Shopify App call</li>
                        <li>use the Shopify <a href="http://docs.shopify.com/api/billings/billings-api">Billing Api</a>
                        </li>
                    </ul>

                    <h4>Installation</h4>

                    <p>To install this app download it from <a href="www.github.com">github</a>. Then use
                        the composer to install the dependencies. </p>

                    <p>For more information about Laravel and how to install composer see
                        the <a href="http://laravel.com/docs/5.0">Laravel documentation</a></p>

                    <h4>Configuration in Shopify</h4>

                    <p>First you have to create a Shopify <a href="http://www.shopify.com/partners/apps">partner
                            account</a>.</p>
                    <h5>Create Shopify App</h5>

                    <p>In the partner page go to Apps and click on the <strong>create app</strong> button.</p>

                    <p>Here set the name of the app, enable <strong>Embedded settings</strong> and enter the
                        <strong>Application Callback URL</strong>.
                        If you have created a Shopify App successfully you can get the <strong>API key</strong> and
                        the <strong>Credentials</strong>.</p>
                    <h5>Create Shopify Development store</h5>

                    <p>To test the example app you need a Shopify Development store. You can use this store to install
                        this app.</p>

                    <h4>Configuration of this app</h4>

                    <p>The configurations of this app are in the environment file .env. Please rename the file
                        .env.example
                        and edit this items:</p>
                    <ul>
                        <li>SHOPIFY_API_KEY for the Api key</li>
                        <li>SHOPIFY_SECRET for the credentials of the app</li>
                        <li>APPLICATION_CHARGE_NAME for the name of the billing plan</li>
                        <li>APPLICATION_CHARGE_PRICE for the price of the billing plan</li>
                        <li>APPLICATION_CHARGE_TRIAL_DAYS for trial days</li>
                        <li>APPLICATION_CHARGE_TEST flag to indicate that this is a test system</li>
                    </ul>

                    <h4>Run the example</h4>

                    <p>To go to the start page enter the URL (like
                        https://localhost:8888/shopify-example-app/public/).</p>

                    <p>In the menu item <strong>Install App locally</strong> enter a Shopify shop name (e.g.
                        example-shop.shopify.com).</p>

                </div>
            </div>
        </div>
    </div>
@endsection
