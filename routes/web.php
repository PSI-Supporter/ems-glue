<?php

use App\Http\Controllers\ItemController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/items', [ItemController::class, 'loadItems']);
Route::get('/form-item', [ItemController::class, 'formItem']);

Route::get('/trans', [ItemController::class, 'loadTrans']);
Route::get('/form-trans', [ItemController::class, 'formTrans']);

Route::get('/truk', [ItemController::class, 'loadTruk']);
Route::get('/form-truk', [ItemController::class, 'formTruk']);
