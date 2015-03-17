@extends('shopify.layoutIFrame')

@include('shopify.bar')

@section('content')

    <h2>Welcome to Shopify Example App</h2>

    <br>
    <div class="panel panel-default">
        <div class="panel-heading">
            <h4 class="panel-title">Product List</h4>
        </div>

        <div class="panel-body">

            <!-- Product List -->
            <table class="table table-striped" id="my-product-list">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Product</th>
                    <th>Type</th>
                    <th>Vendor</th>
                </tr>
                </thead>
                <tbody>
                @foreach($productList as $product)
                    <tr>
                        <td>{{ $product['id'] }}</td>
                        <td><a href="{{$product['id']}}">{{$product['title']}}</a></td>
                        <td>{{ $product['product_type'] }}</td>
                        <td>{{ $product['vendor'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>

        </div>
    </div>
    <input type="hidden" name="shopURL" value="{{ $shopURL }}">


@stop
