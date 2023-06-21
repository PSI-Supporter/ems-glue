<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReturnController extends Controller
{
    function getCountedPart(Request $request)
    {
        $RSSub = DB::table("SPL_TBL")
            ->select("SPL_DOC", "SPL_ITMCD", DB::raw("MAX(SPL_RACKNO) SPL_RACKNO"))
            ->where("SPL_DOC", $request->doc)
            ->groupBy(["SPL_DOC", "SPL_ITMCD"]);
        $RSCountedPart = DB::table("RETSCN_TBL AS a")
            ->select(DB::raw("a.*,b.*, RTRIM(MITM_SPTNO) MITM_SPTNO, RTRIM(ISNULL(RETSCN_HOLD,'0'))  FLG_HOLD,ISNULL(SPL_RACKNO,'') SPL_RACKNO"))
            ->join("MMADE_TBL AS b", "a.RETSCN_CNTRYID", "=", "MMADE_CD")
            ->join("MITM_TBL", "a.RETSCN_ITMCD", "=", "MITM_ITMCD")
            ->leftJoinSub($RSSub, "dt", function ($join) {
                $join->on("RETSCN_ITMCD", "=", "SPL_ITMCD")
                    ->on("RETSCN_SPLDOC", "=", "SPL_DOC");
            })
            ->where("RETSCN_SPLDOC", $request->doc)
            ->where("RETSCN_CAT", $request->category)
            ->where("RETSCN_LINE", $request->line)
            ->get();
        return ['data' => $RSCountedPart];
    }
}
