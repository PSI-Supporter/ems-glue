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
}
