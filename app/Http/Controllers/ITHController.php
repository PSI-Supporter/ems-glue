<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ITHController extends Controller
{
    function getStockMultipleItem(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'part_code' => 'required|array',
            'warehouse' => 'required',
        ], [
            'part_code.required' => 'part_code param is required',
            'part_code.array' => 'array is required',
            'warehouse.required' => 'warehouse code is required'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->all(), 406);
        }

        $data = DB::table('XITRN_TBL')->whereIn('ITRN_ITMCD', $request->part_code)
            ->where('ITRN_LOCCD', $request->warehouse)
            ->select(DB::raw('UPPER(RTRIM(ITRN_ITMCD)) AS ITEMCODE'), DB::raw('SUM(IOQT) AS STOCK'))
            ->groupBy('ITRN_ITMCD')->get();
        return ['data' => $data];
    }
}
