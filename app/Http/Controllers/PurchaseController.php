<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PurchaseController extends Controller
{
    function remove(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'document' => 'required',
            ],
            [
                'document.required' => ':attribute is required',
            ]
        );

        if ($validator->fails()) {
            return response()->json($validator->errors()->all(), 406);
        }

        $purhases = DB::table('PO_TBL')
            ->select('PO_ITMNM', 'PO_ITMCD')
            ->where('PO_NO', $request->document)->get();

        $countReceivedPurchase = 0;

        foreach ($purhases as $r) {
            if ($r->PO_ITMNM) {
                $countReceivedPurchase = DB::table('RCVNI_TBL')->where('RCVNI_PO', $request->document)->count();
            } else {
                $countReceivedPurchase = DB::table('RCV_TBL')->where('RCV_PO', $request->document)->count();
            }
        }

        if ($countReceivedPurchase > 0) {
            return response()->json([
                'message' => 'Could not be removed, because the PO was already received',
            ], 400);
        } else {
            DB::table('PO_TBL')->where('PO_NO', $request->document)->delete();
            DB::table('PO0_TBL')->where('PO0_NO', $request->document)->delete();
            return ['message' => 'Removed'];
        }
    }
}
