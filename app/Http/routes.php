<?php


Route::get('/', 'MyController@index');
Route::get('index', 'MyController@index');
Route::post('index', 'MyController@index');
Route::get('authorize', 'MyController@authorize');
Route::get('home', 'MyController@home');
Route::get('getShopName', 'MyController@getShopName');
// application charge
Route::get('confirmCharges', 'MyController@confirmCharges');

// webHook for uninstalling the app
Route::post('webhookAppUninstall', 'MyController@webhookAppUninstall');


