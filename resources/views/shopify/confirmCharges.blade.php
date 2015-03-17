@extends('shopify.layoutIFrame')

@section('content')

    <br><br>
    <div class="panel panel-default">
        <div class="panel-body">

            <div class="col-md-8 col-md-offset-1">
                <h2>Manual Order</h2>

                <h4>Approve charge from product Manual Order</h4>

                <input type="hidden" id="confirmationURL" value="{{ $confirmationURL }}">
                <input type="hidden" id="useConfirmationURL" value="{{ $useConfirmationURL }}">

            </div>
        </div>
    </div>

@stop
