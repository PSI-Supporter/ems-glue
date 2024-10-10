<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItemTracerController extends Controller
{
    function getOustandingScan(Request $request)
    {
        $WOOutput = DB::table('WMS_CLS_JOB')->where('CLS_PSNNO', $request->PSNDoc)
            ->sum('CLS_QTY');

        $data = DB::select("exec WMS_sp_get_sum_CLS ?, ?, 'OK'", [$request->PSNDoc, $WOOutput + $request->qty]);

        $items = [];
        foreach ($data as $r) {
            if (!in_array($r->Item_code, $items)) {
                $items[] = $r->Item_code;
            }
        }

        $DBItems = DB::table('MITM_TBL')->whereIn("MITM_ITMCD", $items)
            ->get([
                DB::raw(
                    'RTRIM(MITM_ITMCD) ITMCD',
                ),
                DB::raw(
                    'RTRIM(MITM_ITMD1) ITMD1',
                ),
                DB::raw(
                    'RTRIM(MITM_SPTNO) SPTNO',
                ),
                DB::raw(
                    'RTRIM(MITM_STKUOM) UOM',
                ),
            ]);

        foreach ($data as &$r) {
            foreach ($DBItems as $d) {
                if ($r->Item_code == $d->ITMCD) {
                    $r->ITMD1  = $d->ITMD1;
                    $r->SPTNO  = $d->SPTNO;
                    $r->UOM  = $d->UOM;
                    break;
                }
            }
        }
        unset($r);

        return ['data' => $data];
    }

    function getReportTraceLot(Request $request)
    {
        $data0 = DB::table('WMS_SWMP_HIS')->whereDate('SWMP_LUPDT', '>=', $request->dateFrom)
            ->whereDate('SWMP_LUPDT', '<=', $request->dateTo)
            ->select(
                DB::raw("RTRIM(SWMP_WONO) ENG_WO"),
                DB::raw("RTRIM(SWMP_PROCD) PROCD"),
                DB::raw("RTRIM(SWMP_LINENO) LINENOM"),
                DB::raw("RTRIM(SWMP_MCMCZITM) MCZ"),
                DB::raw("'' OLD_ITEM_CODE"),
                DB::raw("'' OLD_LOT_CODE"),
                DB::raw("0 OLD_QTY"),
                DB::raw("'' OLD_UNIQUE"),
                DB::raw("RTRIM(SWMP_ITMCD) NEW_ITEM_CODE"),
                DB::raw("RTRIM(SWMP_LOTNO) NEW_LOT_CODE"),
                DB::raw("SWMP_QTY NEW_QTY"),
                DB::raw("RTRIM(SWMP_UNQ) NEW_UNIQUE"),
                DB::raw("SWMP_LUPDT DATE_AT"),
                DB::raw("RTRIM(SWMP_LUPBY) NIK"),
                DB::raw("RTRIM(SWMP_REMARK) REMARK"),
            );
        $data1 = DB::table('WMS_SWPS_HIS')->whereDate('SWPS_LUPDT', '>=', $request->dateFrom)
            ->whereDate('SWPS_LUPDT', '<=', $request->dateTo)
            ->union($data0)
            ->select(
                DB::raw("RTRIM(SWPS_WONO) ENG_WO"),
                DB::raw("RTRIM(SWPS_PROCD) PROCD"),
                DB::raw("RTRIM(SWPS_LINENO) LINENOM"),
                DB::raw("RTRIM(SWPS_MCMCZITM) MCZ"),
                DB::raw("RTRIM(SWPS_ITMCD) OLD_ITEM_CODE"),
                DB::raw("RTRIM(SWPS_LOTNO) OLD_LOT_CODE"),
                DB::raw("QTY OLD_QTY"),
                DB::raw("RTRIM(SWPS_UNQ) OLD_UNIQUE"),
                DB::raw("RTRIM(SWPS_NITMCD) NEW_ITEM_CODE"),
                DB::raw("RTRIM(SWPS_NLOTNO) NEW_LOT_CODE"),
                DB::raw("NQTY NEW_QTY"),
                DB::raw("RTRIM(SWPS_NUNQ) NEW_UNIQUE"),
                DB::raw("SWPS_LUPDT DATE_AT"),
                DB::raw("RTRIM(SWPS_LUPBY) NIK"),
                DB::raw("RTRIM(SWPS_REMARK) REMARK"),
            );
        $data = DB::query()->fromSub($data1, "VX")
            ->orderBy('DATE_AT')
            ->orderby('ENG_WO')->get();
        return ['data' => $data];
    }
}
