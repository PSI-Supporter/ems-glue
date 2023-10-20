<?php

use App\Http\Controllers\InventoryController;
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
Route::get('/tambahtruk', [ItemController::class, 'tambahtruk']);
Route::post('/simpanTruk', [ItemController::class, 'simpanTruk']);
Route::get('/ubahTruk/{MSTTRANS_ID}', [ItemController::class, 'ubahTruk']);
Route::post('/updateTruk', [ItemController::class, 'updateTruk']);

#Untuk Inventory
Route::get('/Inv', [InventoryController::class, 'loadInventory']);
Route::get('/form-inv', [InventoryController::class, 'formInventory']);
Route::get('/export/inventory-fg', [InventoryController::class, 'exportInv']);
