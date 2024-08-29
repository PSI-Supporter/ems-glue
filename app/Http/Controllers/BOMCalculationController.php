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

        $CountPostedDoc = DB::table('ZRPSAL_BCSTOCK')->where('RPSTOCK_REMARK', $request->doc)->count();

        if ($CountPostedDoc === 0) {
            $data = DB::table('SERD2_TBL')->whereIn('SERD2_SER', $DLVSers)
                ->where('SERD2_ITMCD', $request->part_code_before)
                ->update([
                    'SERD2_ITMCD' => $request->part_code_after,
                    'SERD2_REMARK' => 'MANUAL',
                    'SERD2_USRID' => $request->user_id,
                    'SERD2_LUPDT' => date('Y-m-d H:i:s')
                ]);
        } else {
            return response()->json(['message' => 'Could not update because already posted'], 406);
        }

        return ['message' => 'Saved successfully, please try posting again', 'data' => $data];
    }
}
