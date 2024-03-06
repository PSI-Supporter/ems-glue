<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SimulationController extends Controller
{
    function getReportLinePerDocument(Request $request)
    {
        $data = DB::table('XPIS1')->groupBy('PIS1_PROCD', 'PIS1_LINENO')
            ->where('PIS1_DOCNO', $request->id)
            ->selectRaw('RTRIM(PIS1_PROCD) AS PIS1_PROCD, RTRIM(PIS1_LINENO) AS PIS1_LINENO')
            ->orderBy('PIS1_PROCD')
            ->get();
        return ['data' => $data];
    }

    function getReportSimulationChecker(Request $request)
    {
        $UnPSNSIM = DB::table('XSIM_CHECKER')->get()->toArray();
        $PemutihanSIM = DB::table('XSIM_CHECKER2')->get()->toArray();
        $data = array_merge($UnPSNSIM, $PemutihanSIM);
        usort($data, function($a, $b) {
            return $b->WONO <=> $a->WONO;
        });
        return ['data' => $data];
    }
}
