<?php

namespace App\Http\Controllers;

use App\Models\PartSupply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SupplyController extends Controller
{
    function OutstandingUpload($data)
    {
        $whereExtension = [];
        if (isset($data['category'])) {
            $whereExtension[] = ['SPLSCN_CAT', '=', $data['category']];
        }
        if (isset($data['line'])) {
            $whereExtension[] = ['SPLSCN_LINE', '=', $data['line']];
        }
        $RSPartSupply = PartSupply::select(DB::raw("CONCAT('1',convert(varchar(30), SPLSCN_LUPDT,12),RIGHT(SPLSCN_ID,4) ) AS SPLSCN_ID"), DB::raw("RTRIM(SPLSCN_FEDR) SPLSCN_FEDR"), 'SPLSCN_ORDERNO', 'SPLSCN_LINE', DB::raw('UPPER(RTRIM(SPLSCN_ITMCD)) SPLSCN_ITMCD'), 'SPLSCN_USRID', 'SPLSCN_QTY', DB::raw('(convert(varchar(30), SPLSCN_LUPDT,21)) SPLSCN_LUPDT'), 'SPLSCN_LOTNO')
            ->where("SPLSCN_DOC", $data['doc'])
            ->where("SPLSCN_SAVED", '1')
            ->where($whereExtension)
            ->get()->toArray();
        $RSBase = DB::table('XPPSN2')->select(
            'PPSN2_FR',
            DB::raw('RTRIM(PPSN2_MC) PPSN2_MC, RTRIM(PPSN2_MCZ) PPSN2_MCZ, UPPER(RTRIM(PPSN2_SUBPN)) PPSN2_SUBPN, 0 TTLSCN,RTRIM(PPSN2_PROCD) PPSN2_PROCD'),
            'PPSN2_REQQT',
            'PPSN2_DATANO',
            'PPSN2_PACKSZ1',
            'PPSN2_PICKQT1',
            'PPSN2_PACKSZ2',
            'PPSN2_PICKQT2',
            'PPSN2_PACKSZ3',
            'PPSN2_PICKQT3',
            'PPSN2_PACKSZ4',
            'PPSN2_PICKQT4',
            'PPSN2_PACKSZ5',
            'PPSN2_PICKQT5',
            'PPSN2_PACKSZ6',
            'PPSN2_PICKQT6',
            'PPSN2_PACKSZ7',
            'PPSN2_PICKQT7',
            'PPSN2_PACKSZ8',
            'PPSN2_PICKQT8',
        )->where("PPSN2_PSNNO", $data['doc'])
            ->get()
            ->toArray();
        $RSBase = json_decode(json_encode($RSBase), true);
        foreach ($RSPartSupply as &$d) {
            if (!array_key_exists("USED", $d)) {
                $d["USED"] = false;
            }
        }
        unset($d);

        $RSFinal = [];
        #try 1st time
        foreach ($RSBase as &$r) {
            $think = true;
            while ($think) {
                $grasp = false;
                foreach ($RSPartSupply as $d) {
                    if (($r['PPSN2_FR'] == $d['SPLSCN_FEDR']) && ($r['PPSN2_MCZ'] == $d['SPLSCN_ORDERNO']) && ($r['PPSN2_SUBPN'] == $d['SPLSCN_ITMCD']) && $d['USED'] == false) {
                        $grasp = true;
                        break;
                    }
                }
                if ($grasp) {
                    foreach ($RSPartSupply as &$d) {
                        if (($r['PPSN2_MCZ'] == $d['SPLSCN_ORDERNO']) && ($r['PPSN2_SUBPN'] == $d['SPLSCN_ITMCD']) && $d['USED'] == false and ($r['PPSN2_FR'] == $d['SPLSCN_FEDR'])) {
                            $think2 = true;
                            while ($think2) {
                                if ($r['PPSN2_REQQT'] > $r['TTLSCN']) {
                                    if ($d['USED'] == false) {
                                        if (count($RSFinal) == 0) {
                                            $RSFinal[] = [
                                                "SPLSCN_ID" => $d["SPLSCN_ID"], "SPLSCN_USRID" => $d["SPLSCN_USRID"], "PPSN2_DATANO" => $r["PPSN2_DATANO"], "SPLSCN_FEDR" => $d["SPLSCN_FEDR"], "SPLSCN_ITMCD" => $d["SPLSCN_ITMCD"], "SPLSCN_QTY" => $d["SPLSCN_QTY"], "SPLSCN_LUPDT" => substr($d['SPLSCN_LUPDT'], 0, 16), "SPLSCN_LOTNO" => $d["SPLSCN_LOTNO"], "SPLSCN_ORDERNO" => $d["SPLSCN_ORDERNO"], "SPLSCN_LINE" => $d["SPLSCN_LINE"], "PPSN2_MC" => $r["PPSN2_MC"], "PPSN2_PROCD" => $r["PPSN2_PROCD"], "ISOK" => 1,
                                            ];
                                            $r['TTLSCN'] += $d['SPLSCN_QTY'];
                                            $d['USED'] = true;
                                        } else {
                                            $isfound = false;
                                            foreach ($RSFinal as &$t) {
                                                if (($t["PPSN2_MC"] == $r["PPSN2_MC"]) && ($t["SPLSCN_ORDERNO"] == $r["PPSN2_MCZ"])
                                                    && ($t["SPLSCN_ITMCD"] == $r["PPSN2_SUBPN"]) && ($t["PPSN2_PROCD"] == $r["PPSN2_PROCD"])
                                                ) {
                                                    $r['TTLSCN'] += $d['SPLSCN_QTY'];
                                                    $RSFinal[] =
                                                        [
                                                            "SPLSCN_ID" => $d["SPLSCN_ID"], "SPLSCN_USRID" => $d["SPLSCN_USRID"], "PPSN2_DATANO" => $r["PPSN2_DATANO"], "SPLSCN_FEDR" => $d["SPLSCN_FEDR"], "SPLSCN_ITMCD" => $d["SPLSCN_ITMCD"], "SPLSCN_QTY" => $d["SPLSCN_QTY"], "SPLSCN_LUPDT" => substr($d['SPLSCN_LUPDT'], 0, 16), "SPLSCN_LOTNO" => $d["SPLSCN_LOTNO"], "SPLSCN_ORDERNO" => $d["SPLSCN_ORDERNO"], "SPLSCN_LINE" => $d["SPLSCN_LINE"], "PPSN2_MC" => $r["PPSN2_MC"], "PPSN2_PROCD" => $r["PPSN2_PROCD"], "ISOK" => 1,
                                                        ];
                                                    $isfound = true;
                                                    $d['USED'] = true;
                                                    break;
                                                }
                                            }
                                            unset($t);
                                            if (!$isfound) {
                                                $RSFinal[] = [
                                                    "SPLSCN_ID" => $d["SPLSCN_ID"], "SPLSCN_USRID" => $d["SPLSCN_USRID"], "PPSN2_DATANO" => $r["PPSN2_DATANO"], "SPLSCN_FEDR" => $d["SPLSCN_FEDR"], "SPLSCN_ITMCD" => $d["SPLSCN_ITMCD"], "SPLSCN_QTY" => $d["SPLSCN_QTY"], "SPLSCN_LUPDT" => substr($d['SPLSCN_LUPDT'], 0, 16), "SPLSCN_LOTNO" => $d["SPLSCN_LOTNO"], "SPLSCN_ORDERNO" => $d["SPLSCN_ORDERNO"], "SPLSCN_LINE" => $d["SPLSCN_LINE"], "PPSN2_MC" => $r["PPSN2_MC"], "PPSN2_PROCD" => $r["PPSN2_PROCD"], "ISOK" => 1,
                                                ];
                                                $r['TTLSCN'] += $d['SPLSCN_QTY'];
                                                $d['USED'] = true;
                                            }
                                        }
                                    } else {
                                        $think2 = false;
                                    }
                                } else {
                                    $think2 = false;
                                    $think = false;
                                }
                            }
                        }
                    }
                    unset($d);
                } else {
                    $think = false;
                }
            }
        }
        unset($r);
        #end try

        #try 2nd time
        foreach ($RSBase as &$r) {
            $think = true;
            while ($think) {
                $grasp = false;
                foreach ($RSPartSupply as $d) {
                    if (($r['PPSN2_FR'] == $d['SPLSCN_FEDR']) && ($r['PPSN2_MCZ'] == $d['SPLSCN_ORDERNO']) && ($r['PPSN2_SUBPN'] == $d['SPLSCN_ITMCD']) && $d['USED'] == false) {
                        $grasp = true;
                        break;
                    }
                }
                if ($grasp) {
                    foreach ($RSPartSupply as &$d) {
                        if (($r['PPSN2_MCZ'] == $d['SPLSCN_ORDERNO']) && ($r['PPSN2_SUBPN'] == $d['SPLSCN_ITMCD']) && $d['USED'] == false and ($r['PPSN2_FR'] == $d['SPLSCN_FEDR'])) {
                            $think2 = true;
                            while ($think2) {
                                if ($r['PPSN2_REQQT'] > $r['TTLSCN']) {
                                    if ($d['USED'] == false) {
                                        if (count($RSFinal) == 0) {
                                            $RSFinal[] = [
                                                "SPLSCN_ID" => $d["SPLSCN_ID"], "SPLSCN_USRID" => $d["SPLSCN_USRID"], "PPSN2_DATANO" => $r["PPSN2_DATANO"], "SPLSCN_FEDR" => $d["SPLSCN_FEDR"], "SPLSCN_ITMCD" => $d["SPLSCN_ITMCD"], "SPLSCN_QTY" => $d["SPLSCN_QTY"], "SPLSCN_LUPDT" => substr($d['SPLSCN_LUPDT'], 0, 16), "SPLSCN_LOTNO" => $d["SPLSCN_LOTNO"], "SPLSCN_ORDERNO" => $d["SPLSCN_ORDERNO"], "SPLSCN_LINE" => $d["SPLSCN_LINE"], "PPSN2_MC" => $r["PPSN2_MC"], "PPSN2_PROCD" => $r["PPSN2_PROCD"], "ISOK" => 1,
                                            ];
                                            $r['TTLSCN'] += $d['SPLSCN_QTY'];
                                            $d['USED'] = true;
                                        } else {
                                            $isfound = false;
                                            foreach ($RSFinal as &$t) {
                                                if (($t["PPSN2_MC"] == $r["PPSN2_MC"]) && ($t["SPLSCN_ORDERNO"] == $r["PPSN2_MCZ"])
                                                    && ($t["SPLSCN_ITMCD"] == $r["PPSN2_SUBPN"]) && ($t["PPSN2_PROCD"] == $r["PPSN2_PROCD"])
                                                ) {
                                                    $r['TTLSCN'] += $d['SPLSCN_QTY'];
                                                    $RSFinal[] =
                                                        [
                                                            "SPLSCN_ID" => $d["SPLSCN_ID"], "SPLSCN_USRID" => $d["SPLSCN_USRID"], "PPSN2_DATANO" => $r["PPSN2_DATANO"], "SPLSCN_FEDR" => $d["SPLSCN_FEDR"], "SPLSCN_ITMCD" => $d["SPLSCN_ITMCD"], "SPLSCN_QTY" => $d["SPLSCN_QTY"], "SPLSCN_LUPDT" => substr($d['SPLSCN_LUPDT'], 0, 16), "SPLSCN_LOTNO" => $d["SPLSCN_LOTNO"], "SPLSCN_ORDERNO" => $d["SPLSCN_ORDERNO"], "SPLSCN_LINE" => $d["SPLSCN_LINE"], "PPSN2_MC" => $r["PPSN2_MC"], "PPSN2_PROCD" => $r["PPSN2_PROCD"], "ISOK" => 1,
                                                        ];
                                                    $isfound = true;
                                                    $d['USED'] = true;
                                                    break;
                                                }
                                            }
                                            unset($t);
                                            if (!$isfound) {
                                                $RSFinal[] = [
                                                    "SPLSCN_ID" => $d["SPLSCN_ID"], "SPLSCN_USRID" => $d["SPLSCN_USRID"], "PPSN2_DATANO" => $r["PPSN2_DATANO"], "SPLSCN_FEDR" => $d["SPLSCN_FEDR"], "SPLSCN_ITMCD" => $d["SPLSCN_ITMCD"], "SPLSCN_QTY" => $d["SPLSCN_QTY"], "SPLSCN_LUPDT" => substr($d['SPLSCN_LUPDT'], 0, 16), "SPLSCN_LOTNO" => $d["SPLSCN_LOTNO"], "SPLSCN_ORDERNO" => $d["SPLSCN_ORDERNO"], "SPLSCN_LINE" => $d["SPLSCN_LINE"], "PPSN2_MC" => $r["PPSN2_MC"], "PPSN2_PROCD" => $r["PPSN2_PROCD"], "ISOK" => 1,
                                                ];
                                                $r['TTLSCN'] += $d['SPLSCN_QTY'];
                                                $d['USED'] = true;
                                            }
                                        }
                                    } else {
                                        $think2 = false;
                                    }
                                } else {
                                    $think2 = false;
                                    $think = false;
                                }
                            }
                        }
                    }
                    unset($d);
                } else {
                    $think = false;
                }
            }
        }
        unset($r);
        #end try

        foreach ($RSFinal as &$d) {
            foreach ($RSBase as &$r) {
                if ($r['PPSN2_DATANO'] == $d['PPSN2_DATANO']) {
                    if ($d['SPLSCN_QTY'] == $r['PPSN2_PACKSZ1'] && $r['PPSN2_PICKQT1'] > 0) {
                        $d['ISOK'] = 0;
                        $r['PPSN2_PICKQT1'] -= 1;
                        break;
                    } elseif ($d['SPLSCN_QTY'] == $r['PPSN2_PACKSZ2'] && $r['PPSN2_PICKQT2'] > 0) {
                        $d['ISOK'] = 0;
                        $r['PPSN2_PICKQT2'] -= 1;
                        break;
                    } elseif ($d['SPLSCN_QTY'] == $r['PPSN2_PACKSZ3'] && $r['PPSN2_PICKQT3'] > 0) {
                        $d['ISOK'] = 0;
                        $r['PPSN2_PICKQT3'] -= 1;
                        break;
                    } elseif ($d['SPLSCN_QTY'] == $r['PPSN2_PACKSZ4'] && $r['PPSN2_PICKQT4'] > 0) {
                        $d['ISOK'] = 0;
                        $r['PPSN2_PICKQT4'] -= 1;
                        break;
                    } elseif ($d['SPLSCN_QTY'] == $r['PPSN2_PACKSZ5'] && $r['PPSN2_PICKQT5'] > 0) {
                        $d['ISOK'] = 0;
                        $r['PPSN2_PICKQT5'] -= 1;
                        break;
                    } elseif ($d['SPLSCN_QTY'] == $r['PPSN2_PACKSZ6'] && $r['PPSN2_PICKQT6'] > 0) {
                        $d['ISOK'] = 0;
                        $r['PPSN2_PICKQT6'] -= 1;
                        break;
                    } elseif ($d['SPLSCN_QTY'] == $r['PPSN2_PACKSZ7'] && $r['PPSN2_PICKQT7'] > 0) {
                        $d['ISOK'] = 0;
                        $r['PPSN2_PICKQT7'] -= 1;
                        break;
                    } elseif ($d['SPLSCN_QTY'] == $r['PPSN2_PACKSZ8'] && $r['PPSN2_PICKQT8'] > 0) {
                        $d['ISOK'] = 0;
                        $r['PPSN2_PICKQT8'] -= 1;
                        break;
                    }
                }
            }
            unset($r);
        }
        unset($d);

        return $RSFinal;
    }

    function getCategoryByPSN(Request $request)
    {
        if ($request->outstanding == 1) {
            #try to check is already uploaded v2
            $RSFinal = $this->OutstandingUpload(['doc' => $request->doc]);
            $distinctPart = [];

            $RSFinal2 = [];
            foreach ($RSFinal as $r) {
                if ($r['ISOK'] == 1) {
                    $RSFinal2[] = $r;
                    if (!in_array($r['SPLSCN_ITMCD'], $distinctPart)) {
                        $distinctPart[] = $r['SPLSCN_ITMCD'];
                    }
                }
            }

            $RSCategory = [];
            if (!empty($distinctPart)) {
                $RSCategory = DB::table('XMITM_VCIMS')->select(DB::raw("RTRIM(MITM_ITMCAT) SPL_CAT"))
                    ->whereIn("MITM_ITMCD", $distinctPart)
                    ->groupBy("MITM_ITMCAT")
                    ->get();
            }
            return ['data' => $RSCategory];
        } else {
            $RSCategory = DB::table('SPL_TBL')->select("SPL_CAT")
                ->where("SPL_DOC", $request->doc)
                ->groupBy("SPL_CAT")
                ->orderBy("SPL_CAT")
                ->get();

            $RSNotCanceledFully = DB::table("SPLSCN_TBL")->select("SPLSCN_TBL.*")
                ->leftJoin("SPL_TBL", function ($join) {
                    $join->on("SPLSCN_DOC", "=", "SPL_DOC")
                        ->on("SPLSCN_ITMCD", "=", "SPL_ITMCD")
                        ->on("SPLSCN_ORDERNO", "=", "SPL_ORDERNO");
                })->where("SPLSCN_DOC", $request->doc)->whereNull("SPL_DOC")
                ->get();

            if (!empty($RSCategory)) {
                $myar[] = ["cd" => 1, "msg" => "GO AHEAD"];
            } else {
                $myar[] = ["cd" => 0, "msg" => "Trans No not found"];
            }

            return [
                'data' => $RSCategory,
                'data_unfixed' => $RSNotCanceledFully,
                'status' => $myar,
            ];
        }
    }

    function getOutstandingUpload(Request $request)
    {
        $RSFinal = $this->OutstandingUpload(['doc' => $request->doc, 'category' => $request->category, 'line' => $request->line]);
        $RSFinal2 = [];
        foreach ($RSFinal as $r) {
            if ($r['ISOK'] == 1) {
                $RSFinal2[] = $r;
            }
        }
        return ['data' => $RSFinal2];
    }

    function getLineByPSNandCategory(Request $request)
    {
        $RSPSNLine = DB::table("SPL_TBL")->select("SPL_LINE")
            ->where("SPL_DOC", $request->doc)
            ->where("SPL_CAT", $request->category)
            ->groupBy("SPL_LINE")->get()->toArray();

        if (!empty($RSPSNLine)) {
            $result[] = ["cd" => 1, "msg" => "GO AHEAD"];
        } else {
            $result[] = ["cd" => 0, "msg" => "Data not found"];
        }
        return ['data' => $RSPSNLine, 'status' => $result];
    }

    function isDocumentExist(Request $request)
    {
        $whereExtension = [];
        if (isset($request->category)) {
            $whereExtension[] = ["SPL_CAT", "=", $request->category];
        }
        if (isset($request->line)) {
            $whereExtension[] = ["SPL_LINE", "=", $request->line];
        }
        $DocumentCount = DB::table("SPL_TBL")->where("SPL_DOC", $request->doc)->where($whereExtension)->count();
        $WorkOrder = [];
        if ($DocumentCount > 0) {
            if (isset($request->line)) {
                $WorkOrder = strtoupper(substr($request->doc, 0, 3)) == "PR-" ? [["PPSN1_WONO" => "_"]]
                    : DB::select("exec xsp_megapsnhead_nofr ?, ?", [$request->doc, $request->line]);
            }
            $result[] =  ["cd" => 1, "msg" => "GO AHEAD"];
        } else {
            $result[] = ["cd" => 0, "msg" => "Trans No not found"];
        }
        return ['status' => $result, 'WorkOrder' => $WorkOrder];
    }

    function isPartInDocumentExist(Request $request)
    {
        $RSSPL = DB::table("SPL_TBL")->select(DB::raw("RTRIM(MITM_SPTNO) MITM_SPTNO"), "SPL_RACKNO")
            ->leftJoin("MITM_TBL", "SPL_ITMCD", "=", "MITM_ITMCD")
            ->where("SPL_DOC", $request->doc)
            ->where("SPL_CAT", $request->category)
            ->where("SPL_LINE", $request->line)
            ->where("SPL_ITMCD", $request->item)->get();
        $result = [];
        if (count($RSSPL) > 0) {
            foreach ($RSSPL as $r) {
                $result[] = ["cd" => 1, "msg" => "Go ahead", "ref" => $r->MITM_SPTNO, "rackno" => $r->SPL_RACKNO];
                break;
            }
        } else {
            $result[] = ["cd" => 0, "msg" => "Data not found in PSN"];
        }
        return ['data' => $result];
    }
}
