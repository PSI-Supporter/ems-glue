<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReceiveController extends Controller
{
    function search(Request $request)
    {
        $columnMap = [
            'tr_pch_rcv_det.item_code',
            'item_name',
            'tr_pch_rcv_head.vendor_code',
        ];

        $data = DB::table('tr_pch_rcv_head')
            ->leftJoin('tr_pch_rcv_det', 'tr_pch_rcv_head.trans_no', '=', 'tr_pch_rcv_det.trans_no')
            ->leftJoin('ms_item', 'tr_pch_rcv_det.item_code', '=', 'ms_item.item_code')
            ->where('trans_date', '>=', $request->date0)
            ->where('trans_date', '<=', $request->date1)
            ->select(
                'tr_pch_rcv_head.trans_no',
                'trans_date',
                'location_to',
                'tr_pch_rcv_head.vendor_code',
                'delivery_no',
                'po_no',
                'tr_pch_rcv_det.item_code',
                'item_name',
                'item_group_code',
                'item_type_code',
                'rcv_qty',
                'unit_code',
                'curr_code',
                'net_price',
                DB::raw("net_price*rcv_qty AS amount"),
                'nopen',
                'custom_no',
                'custom_doc',
            )
            ->where($columnMap[$request->searchBy], 'like', '%' . $request->searchValue . '%')
            ->orderBy('trans_date')
            ->orderBy('tr_pch_rcv_head.trans_no')
            ->get();
        return ['data' => $data];
    }
}
