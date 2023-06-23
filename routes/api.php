<?php

use App\Http\Controllers\CountryController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\SupplyController;
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
Route::get('supply/validate-document', [SupplyController::class, 'isDocumentExist']);
Route::get('supply/validate-item', [SupplyController::class, 'isPartInDocumentExist']);
Route::post('supply/validate-supplied-item', [SupplyController::class, 'isPartAlreadySuppliedInDocument']);

# Terkait Return Part
Route::get('return/counted', [ReturnController::class, 'getCountedPart']);
Route::post('return', [ReturnController::class, 'save']);
Route::put('return/status/{id}', [ReturnController::class, 'setPartStatus']);
Route::delete('return/items/{id}', [ReturnController::class, 'delete']);
Route::post('return/alternative-saving', [ReturnController::class, 'saveAlternative']);
Route::post('return/combine', [ReturnController::class, 'saveByCombining']);
