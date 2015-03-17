@section('bar')

    <script type="text/javascript">

        ShopifyApp.ready(function () {
            ShopifyApp.Bar.initialize({
                buttons: {
                    secondary: [
                        {
                            label: "Example App",
                            callback: function () {
                                redirectToExampleApp();
                            }
                        }
                    ]
                },
                title: 'Shopify Example App'
            });
        });

    </script>

@stop
