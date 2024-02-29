<?php

use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\RedmineController;
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
Route::get('/items/report/PDF', [ItemController::class, 'SpreadsheetToPdf']);
Route::get('/items/form-report', [ItemController::class, 'formItemReport']);

Route::get('/trans', [ItemController::class, 'loadTrans']);
Route::get('/form-trans', [ItemController::class, 'formTrans']);
Route::get('/form-ict', [RedmineController::class, 'formICT']);
Route::prefix('redmine')->group(function () {
    Route::get('/projects', [RedmineController::class, 'getProject']);
    Route::get('/issues', [RedmineController::class, 'getIssue']);
    Route::get('/export-issue', [RedmineController::class, 'exportIssue']);
});


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
Route::get('/export/testExport', [InventoryController::class, 'Export']);
