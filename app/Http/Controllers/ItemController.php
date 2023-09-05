<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItemController extends Controller
{
    function loadById(Request $request)
    {
        $RS = DB::table("ITMLOC_TBL")->select(DB::raw("RTRIM(MITM_ITMCD) MITM_ITMCD,RTRIM(MITM_ITMD1) ITMD1,ITMLOC_LOC,RTRIM(MITM_SPTNO) SPTNO"))
            ->join("MITM_TBL", "ITMLOC_ITM", "=", "MITM_ITMCD")
            ->where("ITMLOC_ITM", base64_decode($request->id))
            ->get();
        $result[] = count($RS) ? ['cd' => '1', 'msg' => 'found'] : ['cd' => '0', 'msg' => 'not found'];
        return ['data' => $RS, 'status' => $result];
    }

    function loadItems(Request $request)
    {
        $searchValue = $request->itemName;
        $Items = DB::table('MITM_TBL')->select('MITM_ITMCD', 'MITM_ITMD1')
            ->where('MITM_ITMD1', 'LIKE', '%' . $searchValue . '%')
            ->get();
        return $Items;
    }

    function formItem(Request $request){
        $searchValue = $request->itemName;
        $Items = DB::table('MITM_TBL')->select('MITM_ITMCD', 'MITM_ITMD1')
            ->where('MITM_ITMD1', 'LIKE', '%'.$searchValue.'%')
            ->get();
        return view('form_item', ['items' => $Items]);
    }
}
