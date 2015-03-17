<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateApplicationChargesTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('application_charge', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('shop_id')->unsigned();
            $table->foreign('shop_id')->references('id')->on('authorizations');
            $table->string('charge_id')->nullable();
            $table->string('status')->nullable();
            $table->string('activated_on')->nullable();
            $table->string('billing_on')->nullable();
            $table->string('cancelled_on')->nullable();
            $table->string('confirmation_url')->nullable();
            $table->string('name')->nullable();
            $table->string('price')->nullable();
            $table->string('return_url')->nullable();
            $table->string('test')->nullable();
            $table->string('trial_days')->nullable();
            $table->string('trial_ends_on')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('application_charge');
    }

}
