<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/order', "OrderController@api_create");
Route::post('/order/status', "OrderController@api_status");
Route::post('/order/stop', "OrderController@api_stop");
Route::post('/order/resume', "OrderController@api_resume");
Route::post('/order/cancel', "OrderController@api_cancel");
