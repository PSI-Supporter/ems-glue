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
            if (!in_array($r->itm, $items)) {
                $items[] = $r->itm;
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
            ]);

        foreach ($data as &$r) {
            foreach ($DBItems as $d) {
                if ($r->itm == $d->ITMCD) {
                    $r->ITMD1  = $d->ITMD1;
                    $r->SPTNO  = $d->SPTNO;
                    break;
                }
            }
        }
        unset($r);

        return ['data' => $data];
    }
}
