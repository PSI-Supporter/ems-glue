<?php

use App\Http\Controllers\BusinessGroupController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ITHController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\ReceiveController;
use App\Http\Controllers\RedmineController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\SimulationController;
use App\Http\Controllers\SupplyController;
use App\Http\Controllers\TransferLocationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
# Terkait Country Master
Route::get('country', [CountryController::class, 'getAll']);

# Terkait Supply Part
Route::get('supply/part-category', [SupplyController::class, 'getCategoryByPSN']);
Route::get('supply/part-line', [SupplyController::class, 'getLineByPSNandCategory']);
Route::get('supply/outstanding-upload', [SupplyController::class, 'getOutstandingUpload']);
Route::get('supply/outstanding-scan', [SupplyController::class, 'getOutstandingScan']);
Route::get('supply/validate-document', [SupplyController::class, 'isDocumentExist']);
Route::get('supply/validate-item', [SupplyController::class, 'isPartInDocumentExist']);
Route::post('supply/validate-supplied-item', [SupplyController::class, 'isPartAlreadySuppliedInDocument']);
Route::post('supply/fix-transaction', [SupplyController::class, 'fixTransactionBySuppplyNumber']);

# Terkait Return Part
Route::prefix('return')->group(function () {
    Route::get('counted', [ReturnController::class, 'getCountedPart']);
    Route::post('', [ReturnController::class, 'save']);
    Route::post('by-xray', [ReturnController::class, 'saveFromXray']);
    Route::get('resume', [ReturnController::class, 'resume']);
    Route::put('status/{id}', [ReturnController::class, 'setPartStatus']);
    Route::delete('items/{id}', [ReturnController::class, 'delete']);
    Route::post('alternative-saving', [ReturnController::class, 'saveAlternative']);
    Route::post('combine', [ReturnController::class, 'saveByCombining']);
    Route::post('confirm', [ReturnController::class, 'confirm']);
    Route::post('without-psn', [ReturnController::class, 'returnWithoutPSN']);
    Route::delete('without-psn/{id}', [ReturnController::class, 'cancelReturnWithoutPSN']);
});


# Terkait Item Master
Route::prefix('item')->group(function () {
    Route::get('{id}/location', [ItemController::class, 'loadById']);
    Route::get('search', [ItemController::class, 'search']);
    Route::get('searchItmLoc', [ItemController::class, 'searchItemLocation']);
    Route::get('searchFG', [ItemController::class, 'searchFG']);
    Route::get('searchFGExim', [ItemController::class, 'searchFGExim']);
    Route::get('searchRMExim', [ItemController::class, 'searchRMExim']);
    Route::get('searchRMXls', [ItemController::class, 'searchRMEximXls']);
    Route::get('searchFGXls', [ItemController::class, 'searchFGEximXls']);
    Route::get('downloadsa', [ItemController::class, 'downloadsa']);
    Route::get('xray', [ItemController::class, 'toXRAYItem']);
});

#Untuk Inventory
Route::get('/Inv', [InventoryController::class, 'loadInventory']);
Route::get('/form-inv', [InventoryController::class, 'formInventory']);
Route::get('/export/inventory-fg', [InventoryController::class, 'exportInv']);

# Terkait Label Raw Material
Route::post('label/combine-raw-material', [LabelController::class, 'combineRMLabel']);

# Terkait laporan
Route::prefix('report')->group(function () {
    Route::get("return-without-psn", [ReturnController::class, 'reportReturnWithoutPSN']);
    Route::post("stock", [ITHController::class, 'getStockMultipleItem']);
    Route::post("stock-wms", [ITHController::class, 'getStockMultipleItemWMS']);
});

# Terkait Business Group
Route::get("business-group", [BusinessGroupController::class, 'getAll']);

# Terkait data Sistem sebelumnya
Route::prefix('ics')->group(function () {
    Route::get('receive', [ReceiveController::class, 'search']);
});

# Terkait Simulasi Work Order
Route::prefix('simulation')->group(function () {
    Route::get('document/{id}', [SimulationController::class, 'getReportLinePerDocument']);
    Route::get('checker', [SimulationController::class, 'getReportSimulationChecker']);
});

Route::prefix('transfer-indirect-rm')->group(function () {
    Route::post('form', [TransferLocationController::class, 'saveDraftTransferIndirectRM']);
    Route::get('form', [TransferLocationController::class, 'search']);
    Route::get('form/{id}', [TransferLocationController::class, 'detailsByDocument']);
    Route::put('form/{id}', [TransferLocationController::class, 'updateByDocument']);
    Route::delete('form/{id}', [TransferLocationController::class, 'deleteByItem']);
    Route::get('export/{id}', [TransferLocationController::class, 'toSpreadsheet']);
});


Route::prefix('x-transfer')->group(function () {
    Route::get('document', [TransferLocationController::class, 'xGetDocument']);
    Route::post('document', [TransferLocationController::class, 'saveXdocument']);
    Route::get('document/{id}', [TransferLocationController::class, 'xGetDocumentDetail']);
    Route::get('auto-conform', [TransferLocationController::class, 'autoConformXdocument']);
});

Route::prefix('delivery')->group(function () {
    Route::post('limbah', [DeliveryController::class, 'saveDetailLimbah']);
    Route::get('limbah/{id}', [DeliveryController::class, 'getDetailLimbah']);
});

Route::prefix('redmine-wrapper')->group(function () {
    Route::get('', [RedmineController::class, 'wrapGetIssue']);
    Route::get('coba-input', [RedmineController::class, 'wrapPostIssue']);
    Route::get('coba-update', [RedmineController::class, 'wrapUpdateIssue']);
});
