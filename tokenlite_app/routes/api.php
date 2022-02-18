<?php

use Illuminate\Http\Request;
use App\Http\Controllers\User\TokenController;

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

Route::get('/stage', 'APIController@stage')->name('stage');
Route::get('/stage/full', 'APIController@stage_full')->name('stage.full');

Route::get('/bonus', 'APIController@bonuses')->name('bonus');
Route::get('/price', 'APIController@prices')->name('price');

Route::post('/kyc', 'APIController@kyc')->name('kyc');

Route::get('/updateTransaction', 'APIController@updateTransaction')->name('updateTransaction');

Route::get('/backup', 'APIController@backup')->name('backup');

//Route::get('/tokenPrice', 'TokenController@tokenPrice')->name('tokenPrice');
//Route::get('/tokenPrice', 'User\TokenController@tokenPrice')->name('tokenPrice');
//Route::post('/addWallet', 'User\TokenController@addWallet')->name('addWallet');
//Route::post('/createTransaction', 'User\TokenController@createTransaction')->name('createTransaction');

// Route::post('/wallet', 'APIController@createTransaction')->name('createTransaction');
// Route::post('/create-transaction', 'APIController@createTransaction')->name('createTransaction');
// Route::post('/update-transaction', 'APIController@updateTransaction')->name('updateTransaction');

Route::any('/{any?}', function () {
    throw new App\Exceptions\APIException("Enter a valid endpoint", 400);
})->where('any', '.*');
