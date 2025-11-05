<?php

use App\Http\Controllers\BenchMarkController;
use App\Http\Controllers\BOMCalculationController;
use App\Http\Controllers\BusinessGroupController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ItemTracerController;
use App\Http\Controllers\ITHController;
use App\Http\Controllers\KursController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\ProcessMasterController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReceiveController;
use App\Http\Controllers\RedmineController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\SimulationController;
use App\Http\Controllers\SupplyController;
use App\Http\Controllers\TransferLocationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WOController;
use App\Http\Controllers\WorkingTimeController;
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
Route::prefix('supply')->group(function () {
    Route::get('part-category', [SupplyController::class, 'getCategoryByPSN']);
    Route::get('part-line', [SupplyController::class, 'getLineByPSNandCategory']);
    Route::get('outstanding-upload', [SupplyController::class, 'getOutstandingUpload']);
    Route::get('outstanding-scan', [SupplyController::class, 'getOutstandingScan']);
    Route::get('validate-document', [SupplyController::class, 'isDocumentExist']);
    Route::get('validate-item', [SupplyController::class, 'isPartInDocumentExist']);
    Route::post('validate-supplied-item', [SupplyController::class, 'isPartAlreadySuppliedInDocument']);
    Route::post('fix-transaction', [SupplyController::class, 'fixTransactionBySuppplyNumber']);
    Route::put('revise', [SupplyController::class, 'reviseLine']);
    Route::get('supply-pdf', [SupplyController::class, 'toPickingInstruction']);
    Route::get('report', [SupplyController::class, 'reportPSNJOBPeriod']);
    Route::get('document-delivery', [SupplyController::class, 'getDocumentByDelivery']);
    Route::get('validate-label', [SupplyController::class, 'validateUniquekeyVsDoc']);
    Route::post('join-reel', [SupplyController::class, 'joinReels']);
    Route::get('join-reel-report', [SupplyController::class, 'reportJoinReels']);
    Route::post('change-reel', [SupplyController::class, 'changeSuppliedReel']);
});
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
    Route::post('split', [ItemController::class, 'splitC3']);
    Route::post('register-label', [ItemController::class, 'registerC3']);
    Route::get('upload-composite', [ItemController::class, 'uploadComposite']);
    Route::get('xray-sync-rack', [ItemController::class, 'updateLocationXray']);
    Route::get('category', [ItemController::class, 'getCategories']);
    Route::get('rank-list', [ItemController::class, 'getItemRank']);
});

#Untuk Inventory
Route::get('/Inv', [InventoryController::class, 'loadInventory']);
Route::get('/form-inv', [InventoryController::class, 'formInventory']);
Route::get('/export/inventory-fg', [InventoryController::class, 'exportInv']);
Route::prefix('inventory')->group(function () {
    Route::delete("keys/{id}", [InventoryController::class, 'removeLine']);
    Route::get("warehouse", [InventoryController::class, 'getWarehouse']);
    Route::get("warehouse-rm", [InventoryController::class, 'getWarehouseRM']);
    Route::get("iso-report", [InventoryController::class, 'generateReportInventoryISOFormat']);
});


# Terkait Label Raw Material
Route::prefix('label')->group(function () {
    Route::post('combine-raw-material', [LabelController::class, 'combineRMLabel']);
    Route::post('c3-reprint', [LabelController::class, 'getRawMaterialLabelsHelper']);
    Route::get('c3', [LabelController::class, 'getLabel']);
    Route::post('c3', [LabelController::class, 'registerLabel']);
    Route::post('c3-emergency', [LabelController::class, 'registerLabelWithoutReference']);
    Route::delete('c3', [LabelController::class, 'delete']);
    Route::get('c3-active', [LabelController::class, 'getActiveLabel']);
    Route::post('log', [LabelController::class, 'logAction']);
    Route::put('', [LabelController::class, 'updateLabelValue']);
});

# Terkait laporan
Route::prefix('report')->group(function () {
    Route::get("return-without-psn", [ReturnController::class, 'reportReturnWithoutPSN']);
    Route::post("stock", [ITHController::class, 'getStockMultipleItem']);
    Route::post("stock-wms", [ITHController::class, 'getStockMultipleItemWMS']);
    Route::get("fg-ng-customer", [ReceiveController::class, 'getReportFGNGCustomer']);
    Route::get("konversi-bahan-baku", [DeliveryController::class, 'reportKonversiBahanBaku']);
    Route::get("accounting-mutasi-barang-jadi", [InventoryController::class, 'accountingMutasiBarangJadiReport']);
    Route::get("accounting-mutasi-barang-bahan-baku", [InventoryController::class, 'accountingMutasiBahanBakuReport']);
    Route::get("accounting-mutasi-in-out-barang-jadi", [InventoryController::class, 'accountingMutasiInOutBarangJadiReport']);
    Route::get("accounting-mutasi-in-out-barang-bahan-baku", [InventoryController::class, 'accountingMutasiInOutBarangBahanBakuReport']);
    Route::get('lot-tracer', [ItemTracerController::class, 'getReportTraceLotAsSpreadsheet']);
    Route::get('join-reels', [LabelController::class, 'getReportJoinReels']);
    Route::get('gate-out', [DeliveryController::class, 'reportGateOut']);
    Route::get('value-checking', [LabelController::class, 'reportValueChecking']);
    Route::get('keikaku', [WOController::class, 'getKeikakuReport']);
    Route::get('production-output', [WOController::class, 'getProductionOutputReport']);
});

# Terkait Bom Calculation
Route::prefix('bom-calculation')->group(function () {
    Route::put("delivery", [BOMCalculationController::class, 'updateByDelivery']);
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
    Route::get('manual-conform', [TransferLocationController::class, 'manualConformXdocument']);
});

Route::prefix('delivery')->group(function () {
    Route::post('limbah', [DeliveryController::class, 'saveDetailLimbah']);
    Route::get('limbah/{id}', [DeliveryController::class, 'getDetailLimbah']);
    Route::get('regular/{doc}', [DeliveryController::class, 'getDeliveryResume']);
    Route::put('vehicle/{doc}', [DeliveryController::class, 'setActualPlatNumber']);
    Route::get('gate-out', [DeliveryController::class, 'getNotGateOut']);
    Route::get('gate-out/{doc}', [DeliveryController::class, 'getNotGateOutDetail']);
    Route::post('gate-out', [DeliveryController::class, 'setGateOut']);
    Route::get('checking/{doc}', [DeliveryController::class, 'getDeliveryCheckingDetail']);
    Route::delete('checking', [DeliveryController::class, 'deleteDeliveryChecking']);
});

Route::prefix('redmine-wrapper')->group(function () {
    Route::get('', [RedmineController::class, 'wrapGetIssue']);
    Route::get('coba-input', [RedmineController::class, 'wrapPostIssue']);
    Route::get('coba-update', [RedmineController::class, 'wrapUpdateIssue']);
});

Route::prefix('receive')->group(function () {
    Route::get('synchronize', [ReceiveController::class, 'synchronize_from_MEGAEMS']);
    Route::post('synchronize-for-labeling', [ReceiveController::class, 'syncFromOtherSource']);
    Route::get('download-template', [ReceiveController::class, 'downloadTemplateUpload']);
    Route::get('upload-massively', [ReceiveController::class, 'uploadMassive']);
    Route::get('rtn-fg-report', [ReceiveController::class, 'reportRTNFG']);
    Route::get('update-rtn-fg', [ReceiveController::class, 'updateRTNFGBG']);
    Route::post('parse-image', [ReceiveController::class, 'parseImage']);
    Route::get('document', [ReceiveController::class, 'getDocument']);
    Route::get('document/{doc}', [ReceiveController::class, 'getDocumentDetail']);
    Route::get('document/{doc}/{item}', [ReceiveController::class, 'getItemByDoc']);
    Route::get('document-emergency/{doc}', [ReceiveController::class, 'getItemByDocEmergency']);
    Route::get('search-by-item-name', [ReceiveController::class, 'searchByItemName']);
});

Route::prefix('production-plan')->group(function () {
    Route::post('import', [WOController::class, 'importProdPlan']);
    Route::get('revisions', [WOController::class, 'getProdPlanRevisions']);
    Route::get('revisions/{revision}', [WOController::class, 'getProdPlan']);
});

Route::prefix('work-order')->group(function () {
    Route::get('outstanding', [WOController::class, 'getOutstanding']);
    Route::get('process', [WOController::class, 'getProcess']);
    Route::post('', [WOController::class, 'saveOutput']);
    Route::get('resume', [WOController::class, 'resume']);
    Route::get('output', [WOController::class, 'getOutput']);
    Route::post('downtime', [WOController::class, 'saveDowntime']);
    Route::get('downtime', [WOController::class, 'getDownTime']);
    Route::get('production-time', [WOController::class, 'getProductionTime']);
    Route::get('input', [WOController::class, 'getInput']);
    Route::get('export', [WOController::class, 'exportDailyOutput']);
    Route::get('export-cost', [WOController::class, 'exportCost']);
    Route::get('', [WOController::class, 'getWO']);
    Route::post('output-wip', [WOController::class, 'saveWIP']);
    Route::get('output-wip', [WOController::class, 'getWIP']);
    Route::post('item-description', [WOController::class, 'getItemDescription']);
    Route::post('item-outstanding-lotsize', [WOController::class, 'getOutstandingLotsize']);
});

Route::prefix('process-master')->group(function () {
    Route::get('cycle-time', [ProcessMasterController::class, 'getCycleTime']);
    Route::get('history', [ProcessMasterController::class, 'getHistory']);
    Route::post('', [ProcessMasterController::class, 'save']);
    Route::post('search', [ProcessMasterController::class, 'search']);
    Route::get('line-code', [ProcessMasterController::class, 'getLine']);
    Route::get('cycle-time-by-line-code', [ProcessMasterController::class, 'getByLine']);
    Route::put('process/{id}', [ProcessMasterController::class, 'update']);
    Route::delete('process/{id}', [ProcessMasterController::class, 'delete']);
});

Route::prefix('kurs')->group(function () {
    Route::get('', [KursController::class, 'getKurs']);
    Route::get('daily-download', [KursController::class, 'downloadKursDaily']);
});

Route::prefix('purchase')->group(function () {
    Route::delete('remove', [PurchaseController::class, 'remove']);
});

Route::prefix('keikaku')->group(function () {
    Route::post('', [WOController::class, 'saveKeikaku']);
    Route::get('', [WOController::class, 'getKeikakuData']);
    Route::post('calculation', [WOController::class, 'saveKeikakuCalculation']);
    Route::get('calculation', [WOController::class, 'getKeikakuCalculation']);
    Route::post('from-balance', [WOController::class, 'saveKeikakuFromPreviousBalance']);
    Route::get('production-plan', [WOController::class, 'getProdPlanSimulation']);
    Route::post('output', [WOController::class, 'saveKeikakuOutput']);
    Route::post('downtime', [WOController::class, 'saveKeikakuDownTime']);
    Route::get('downtime', [WOController::class, 'getKeikakuDownTime']);
    Route::post('model-changes', [WOController::class, 'saveKeikakuModelChanges']);
    Route::get('wo-run', [WOController::class, 'getWOHistory']);
    Route::post('working-time', [WorkingTimeController::class, 'saveCalculation']);
    Route::get('working-time-name', [WorkingTimeController::class, 'getName']);
    Route::get('working-time', [WorkingTimeController::class, 'getTemplate']);
    Route::put('working-time/{name}', [WorkingTimeController::class, 'activateTemplate']);
    Route::post('input-hw', [WOController::class, 'saveKeikakuInputHW']);
    Route::post('output-hw', [WOController::class, 'saveKeikakuOutputHW']);
    Route::post('input2-hw', [WOController::class, 'saveKeikakuInput2HW']);
    Route::post('comment', [WOController::class, 'keikakuSaveComment']);
    Route::get('line-by-user', [WOController::class, 'getLineAccessByUser']);
    Route::post('permission', [WOController::class, 'savePermission']);
    Route::post('release', [WOController::class, 'setRelease']);
});

Route::prefix('transaction')->group(function () {
    Route::post('shortage-part-report', [ITHController::class, 'generateShortagePartReport']);
});

# Terkait Users
Route::prefix('users')->group(function () {
    Route::get('{id}', [UserController::class, 'getName']);
    Route::get('group', [UserController::class, 'getByGroup']);
});

Route::prefix('users-group')->group(function () {
    Route::get('active', [UserController::class, 'getActiveUserGroup']);
});

Route::prefix('user')->group(function () {
    Route::get('group', [UserController::class, 'getByGroup']);
    Route::get('active', [UserController::class, 'getActiveByGroup']);
});

Route::prefix('item-tracer')->group(function () {
    Route::get('outstanding-scan', [ItemTracerController::class, 'getOustandingScan']);
    Route::get('outstanding-scan-detail', [ItemTracerController::class, 'getOutstandingScanDetail']);
    Route::post('adjust-detail', [ItemTracerController::class, 'adjustDetail']);
    Route::get('lot', [ItemTracerController::class, 'getReportTraceLot']);
});

Route::prefix('testing')->group(function () {
    Route::get('with-opcache', [BenchMarkController::class, 'benchmarkWithOpCache']);
    Route::get('without-opcache', [BenchMarkController::class, 'benchmarkWithoutOpCache']);
});
