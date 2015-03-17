@extends('shopify.layoutInstall')

@section('content')

    <div class="panel panel-default">
        <div class="panel-body">


            <div class="col-md-8 col-md-offset-1">
                <h2>Local shop installation</h2>

                <p>Install this app for your shop</p>

                @if (!empty($message))
                    <p class="message">{{ $message }}</p>
                @endif

                <form method="POST" action="index" accept-charset="UTF-8">
                    <div class="form-group">
                        <label for="shop">The URL of the Shop</label>
                        <span class="hint">(enter it like this: yourshop.myshopify.com)</span>
                        <input class="form-control" placeholder="myshop.myshopify.com" name="shop" type="text"
                               id="shop">
                    </div>
                    <input class="btn btn-primary" type="submit" value="Install or Run App">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                </form>
            </div>
        </div>
    </div>

@stop
