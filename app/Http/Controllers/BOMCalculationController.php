<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BOMCalculationController extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
    }

    function updateByDelivery(Request $request)
    {
        $DLVSers = DB::table('DLV_TBL')->where('DLV_ID', $request->doc)
            ->get(['DLV_SER'])
            ->map(function ($value, $key) {
                return collect($value)->values()->toArray();
            })->collapse();

        $data = DB::table('SERD2_TBL')->whereIn('SERD2_SER', $DLVSers)
            ->where('SERD2_ITMCD', $request->part_code_before)
            ->get(['SERD2_ITMCD', 'SERD2_REMARK', 'SERD2_USRID']);

        return ['message' => 'Saved successfully', 'data' => $data];
    }
}
