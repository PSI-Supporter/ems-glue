<?php

namespace App\Http\Controllers;

use App\Models\ITH;
use App\Models\PartSupply;
use App\Models\SPLSCN_LOG;
use App\Traits\BusinessSelectionTrait;
use DateInterval;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Classes\EMSFpdf;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use Symfony\Component\Process\Process;

class SupplyController extends Controller
{
    use BusinessSelectionTrait;

    protected $fpdf;

    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
        $this->fpdf = new EMSFpdf();
    }

    function OutstandingUpload($data)
    {
        $whereExtension = [];
        $whereExtensionMEGA = [];
        if (isset($data['category'])) {
            $whereExtension[] = ['SPLSCN_CAT', '=', $data['category']];
        }
        if (isset($data['line'])) {
            $whereExtension[] = ['SPLSCN_LINE', '=', $data['line']];
            $whereExtensionMEGA[] = ['PPSN2_LINENO', '=', $data['line']];
        }
        $RSPartSupply = PartSupply::select(DB::raw("CONCAT('1',convert(varchar(30), SPLSCN_LUPDT,12),RIGHT(SPLSCN_ID,4) ) AS SPLSCN_ID"), DB::raw("RTRIM(SPLSCN_FEDR) SPLSCN_FEDR"), 'SPLSCN_ORDERNO', 'SPLSCN_LINE', DB::raw('UPPER(RTRIM(SPLSCN_ITMCD)) SPLSCN_ITMCD'), 'SPLSCN_USRID', 'SPLSCN_QTY', DB::raw('(convert(varchar(30), SPLSCN_LUPDT,21)) SPLSCN_LUPDT'), 'SPLSCN_LOTNO')
            ->where("SPLSCN_DOC", $data['doc'])
            ->where("SPLSCN_SAVED", '1')
            ->where($whereExtension)
            ->orderByRaw('SPLSCN_FEDR ASC,SPLSCN_LUPDT ASC')
            ->get();
        $RSPartSupply = json_decode(json_encode($RSPartSupply), true);

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
            ->where($whereExtensionMEGA)
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
                                                "SPLSCN_ID" => $d["SPLSCN_ID"],
                                                "SPLSCN_USRID" => $d["SPLSCN_USRID"],
                                                "PPSN2_DATANO" => $r["PPSN2_DATANO"],
                                                "SPLSCN_FEDR" => $d["SPLSCN_FEDR"],
                                                "SPLSCN_ITMCD" => $d["SPLSCN_ITMCD"],
                                                "SPLSCN_QTY" => $d["SPLSCN_QTY"],
                                                "SPLSCN_LUPDT" => substr($d['SPLSCN_LUPDT'], 0, 16),
                                                "SPLSCN_LOTNO" => $d["SPLSCN_LOTNO"],
                                                "SPLSCN_ORDERNO" => $d["SPLSCN_ORDERNO"],
                                                "SPLSCN_LINE" => $d["SPLSCN_LINE"],
                                                "PPSN2_MC" => $r["PPSN2_MC"],
                                                "PPSN2_PROCD" => $r["PPSN2_PROCD"],
                                                "ISOK" => 1,
                                            ];
                                            $r['TTLSCN'] += $d['SPLSCN_QTY'];
                                            $d['USED'] = true;
                                        } else {
                                            $isfound = false;
                                            foreach ($RSFinal as &$t) {
                                                if (
                                                    ($t["PPSN2_MC"] == $r["PPSN2_MC"]) && ($t["SPLSCN_ORDERNO"] == $r["PPSN2_MCZ"])
                                                    && ($t["SPLSCN_ITMCD"] == $r["PPSN2_SUBPN"]) && ($t["PPSN2_PROCD"] == $r["PPSN2_PROCD"])
                                                ) {
                                                    $r['TTLSCN'] += $d['SPLSCN_QTY'];
                                                    $RSFinal[] =
                                                        [
                                                            "SPLSCN_ID" => $d["SPLSCN_ID"],
                                                            "SPLSCN_USRID" => $d["SPLSCN_USRID"],
                                                            "PPSN2_DATANO" => $r["PPSN2_DATANO"],
                                                            "SPLSCN_FEDR" => $d["SPLSCN_FEDR"],
                                                            "SPLSCN_ITMCD" => $d["SPLSCN_ITMCD"],
                                                            "SPLSCN_QTY" => $d["SPLSCN_QTY"],
                                                            "SPLSCN_LUPDT" => substr($d['SPLSCN_LUPDT'], 0, 16),
                                                            "SPLSCN_LOTNO" => $d["SPLSCN_LOTNO"],
                                                            "SPLSCN_ORDERNO" => $d["SPLSCN_ORDERNO"],
                                                            "SPLSCN_LINE" => $d["SPLSCN_LINE"],
                                                            "PPSN2_MC" => $r["PPSN2_MC"],
                                                            "PPSN2_PROCD" => $r["PPSN2_PROCD"],
                                                            "ISOK" => 1,
                                                        ];
                                                    $isfound = true;
                                                    $d['USED'] = true;
                                                    break;
                                                }
                                            }
                                            unset($t);
                                            if (!$isfound) {
                                                $RSFinal[] = [
                                                    "SPLSCN_ID" => $d["SPLSCN_ID"],
                                                    "SPLSCN_USRID" => $d["SPLSCN_USRID"],
                                                    "PPSN2_DATANO" => $r["PPSN2_DATANO"],
                                                    "SPLSCN_FEDR" => $d["SPLSCN_FEDR"],
                                                    "SPLSCN_ITMCD" => $d["SPLSCN_ITMCD"],
                                                    "SPLSCN_QTY" => $d["SPLSCN_QTY"],
                                                    "SPLSCN_LUPDT" => substr($d['SPLSCN_LUPDT'], 0, 16),
                                                    "SPLSCN_LOTNO" => $d["SPLSCN_LOTNO"],
                                                    "SPLSCN_ORDERNO" => $d["SPLSCN_ORDERNO"],
                                                    "SPLSCN_LINE" => $d["SPLSCN_LINE"],
                                                    "PPSN2_MC" => $r["PPSN2_MC"],
                                                    "PPSN2_PROCD" => $r["PPSN2_PROCD"],
                                                    "ISOK" => 1,
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
            $grasp = false;
            foreach ($RSPartSupply as $d) {
                if (($r['PPSN2_FR'] == $d['SPLSCN_FEDR']) && ($r['PPSN2_MCZ'] == $d['SPLSCN_ORDERNO']) && ($r['PPSN2_SUBPN'] == $d['SPLSCN_ITMCD']) && !$d['USED']) {
                    $grasp = true;
                    break;
                }
            }
            if ($grasp) {
                foreach ($RSPartSupply as &$d) {
                    if (($r['PPSN2_MCZ'] == $d['SPLSCN_ORDERNO']) && ($r['PPSN2_SUBPN'] == $d['SPLSCN_ITMCD']) && !$d['USED'] && ($r['PPSN2_FR'] == $d['SPLSCN_FEDR'])) {
                        if (count($RSFinal) == 0) {
                            $RSFinal[] = [
                                "SPLSCN_ID" => $d["SPLSCN_ID"],
                                "SPLSCN_USRID" => $d["SPLSCN_USRID"],
                                "PPSN2_DATANO" => $r["PPSN2_DATANO"],
                                "SPLSCN_FEDR" => $d["SPLSCN_FEDR"],
                                "SPLSCN_ITMCD" => $d["SPLSCN_ITMCD"],
                                "SPLSCN_QTY" => $d["SPLSCN_QTY"],
                                "SPLSCN_LUPDT" => substr($d['SPLSCN_LUPDT'], 0, 16),
                                "SPLSCN_LOTNO" => $d["SPLSCN_LOTNO"],
                                "SPLSCN_ORDERNO" => $d["SPLSCN_ORDERNO"],
                                "SPLSCN_LINE" => $d["SPLSCN_LINE"],
                                "PPSN2_MC" => $r["PPSN2_MC"],
                                "PPSN2_PROCD" => $r["PPSN2_PROCD"],
                                "ISOK" => 1,
                            ];
                            $r['TTLSCN'] += $d['SPLSCN_QTY'];
                            $d['USED'] = true;
                        } else {
                            $isfound = false;
                            foreach ($RSFinal as &$t) {
                                if (
                                    ($t["PPSN2_MC"] == $r["PPSN2_MC"]) && ($t["SPLSCN_ORDERNO"] == $r["PPSN2_MCZ"])
                                    && ($t["SPLSCN_ITMCD"] == $r["PPSN2_SUBPN"]) && ($t["PPSN2_PROCD"] == $r["PPSN2_PROCD"])
                                ) {
                                    $r['TTLSCN'] += $d['SPLSCN_QTY'];
                                    $RSFinal[] =
                                        [
                                            "SPLSCN_ID" => $d["SPLSCN_ID"],
                                            "SPLSCN_USRID" => $d["SPLSCN_USRID"],
                                            "PPSN2_DATANO" => $r["PPSN2_DATANO"],
                                            "SPLSCN_FEDR" => $d["SPLSCN_FEDR"],
                                            "SPLSCN_ITMCD" => $d["SPLSCN_ITMCD"],
                                            "SPLSCN_QTY" => $d["SPLSCN_QTY"],
                                            "SPLSCN_LUPDT" => substr($d['SPLSCN_LUPDT'], 0, 16),
                                            "SPLSCN_LOTNO" => $d["SPLSCN_LOTNO"],
                                            "SPLSCN_ORDERNO" => $d["SPLSCN_ORDERNO"],
                                            "SPLSCN_LINE" => $d["SPLSCN_LINE"],
                                            "PPSN2_MC" => $r["PPSN2_MC"],
                                            "PPSN2_PROCD" => $r["PPSN2_PROCD"],
                                            "ISOK" => 1,
                                        ];
                                    $isfound = true;
                                    $d['USED'] = true;
                                    break;
                                }
                            }
                            unset($t);

                            if (!$isfound) {
                                $RSFinal[] = [
                                    "SPLSCN_ID" => $d["SPLSCN_ID"],
                                    "SPLSCN_USRID" => $d["SPLSCN_USRID"],
                                    "PPSN2_DATANO" => $r["PPSN2_DATANO"],
                                    "SPLSCN_FEDR" => $d["SPLSCN_FEDR"],
                                    "SPLSCN_ITMCD" => $d["SPLSCN_ITMCD"],
                                    "SPLSCN_QTY" => $d["SPLSCN_QTY"],
                                    "SPLSCN_LUPDT" => substr($d['SPLSCN_LUPDT'], 0, 16),
                                    "SPLSCN_LOTNO" => $d["SPLSCN_LOTNO"],
                                    "SPLSCN_ORDERNO" => $d["SPLSCN_ORDERNO"],
                                    "SPLSCN_LINE" => $d["SPLSCN_LINE"],
                                    "PPSN2_MC" => $r["PPSN2_MC"],
                                    "PPSN2_PROCD" => $r["PPSN2_PROCD"],
                                    "ISOK" => 1,
                                ];
                                $r['TTLSCN'] += $d['SPLSCN_QTY'];
                                $d['USED'] = true;
                            }
                        }
                    }
                }
                unset($d);
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
        $WorkOrderUnique = [];
        $RSKittingReferenceDocument = [];
        if ($DocumentCount > 0) {
            if (isset($request->line)) {
                $WorkOrder = strtoupper(substr($request->doc, 0, 3)) == "PR-" ? [(object) ["PPSN1_WONO" => "_"]]
                    : DB::select("exec xsp_megapsnhead_nofr ?, ?", [$request->doc, $request->line]);
            } else {
                if (substr($request->doc, 0, 3) == "PR-") {
                    # jika dokumen part req maka WO nya blank (_)
                    $WorkOrder = [(object) ["PPSN1_WONO" => "_"]];

                    # cari referensi dokumen kitting
                    $RSKittingReferenceDocument = DB::table("SPL_TBL")->select("SPL_REFDOCNO", DB::raw("MAX(SPL_REFDOCCAT) REFDOCCAT"))
                        ->where("SPL_DOC", $request->doc)
                        ->groupBy("SPL_REFDOCNO")->get();
                } else {
                    $WorkOrder = DB::table("XPPSN1")->select(DB::raw("RTRIM(PPSN1_WONO) PPSN1_WONO"))
                        ->where("PPSN1_PSNNO", $request->doc)
                        ->groupBy("PPSN1_WONO")->get();

                    # cari referensi dokumen kitting
                    $RSKittingReferenceDocument = DB::table("SPL_TBL")->select(DB::raw("SPL_DOC SPL_REFDOCNO,MAX(SPL_REFDOCCAT) REFDOCCAT"))
                        ->where("SPL_REFDOCNO", $request->doc)
                        ->groupBy("SPL_DOC")->get();
                }
            }
            $result[] = ["cd" => 1, "msg" => "GO AHEAD"];
        } else {
            $result[] = ["cd" => 0, "msg" => "Trans No not found"];
        }

        foreach ($WorkOrder as $r) {
            $isFound = false;
            foreach ($WorkOrderUnique as $u) {
                if ($u['PPSN1_WONO'] === $r->PPSN1_WONO) {
                    $isFound = true;
                    break;
                }
            }

            if (!$isFound) {
                $WorkOrderUnique[] = ['PPSN1_WONO' => $r->PPSN1_WONO];
            }
        }

        return ['status' => $result, 'WorkOrder' => $WorkOrderUnique, 'dataReff' => $RSKittingReferenceDocument];
    }

    function isPartInDocumentExist(Request $request)
    {
        $whereParam = [];
        if ($request->category) {
            $whereParam[] = ['SPL_CAT', '=',  $request->category];
        }
        if ($request->line) {
            $whereParam[] = ['SPL_LINE', '=',  $request->line];
        }

        $RSSPL = DB::table("SPL_TBL")->select(DB::raw("RTRIM(MITM_SPTNO) MITM_SPTNO"), "SPL_RACKNO")
            ->leftJoin("MITM_TBL", "SPL_ITMCD", "=", "MITM_ITMCD")
            ->where("SPL_DOC", $request->doc)
            ->where("SPL_ITMCD", $request->item)
            ->where($whereParam)
            ->get();
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

    function isPartAlreadySuppliedInDocument(Request $request)
    {
        $whereParam = [];
        $partMeasurement = [];
        if ($request->item) {
            $whereParam[] = ['SPLSCN_ITMCD', '=',  $request->item];
            $partMeasurement = DB::table('ENG_TECPRTLC')->where('PRTCD', $request->item)->first();
        }
        if ($request->lotNumber) {
            $whereParam[] = ['SPLSCN_LOTNO', '=',  $request->lotNumber];
        }
        if ($request->qty) {
            $whereParam[] = ['SPLSCN_QTY', '=',  $request->qty];
        }
        if ($request->uniquekey) {
            $whereParam[] = ['SPLSCN_UNQCODE', '=',  $request->uniquekey];
        }

        $SuppliedPart = DB::table("SPLSCN_TBL")
            ->where("SPLSCN_DOC", $request->doc)
            ->where($whereParam)
            ->get();
        $result = [];
        if ($SuppliedPart->count() > 0) {
            $result[] = ["cd" => 1, "msg" => "GO AHEAD", 'data' => $SuppliedPart, 'dataMeasurement' => $partMeasurement];
        } else {
            $result[] = ["cd" => 0, "msg" => "Lot No not found $request->lotNumber"];
        }
        return ['status' => $result];
    }

    function getOutstandingScan(Request $request)
    {
        $RSSub1 = DB::table("SPL_TBL")->selectRaw("RTRIM(SPL_MC) SPL_MC, RTRIM(SPL_ORDERNO) SPL_ORDERNO, RTRIM(SPL_ITMCD) SPL_ITMCD, RTRIM(MITM_SPTNO) MITM_SPTNO, SPL_MS,SPL_QTYUSE, SUM(SPL_QTYREQ) REQQT, 0 PLOTQT")
            ->leftJoin("MITM_TBL", "SPL_ITMCD", "=", "MITM_ITMCD")
            ->where("SPL_DOC", $request->doc)
            ->where("SPL_CAT", $request->category)
            ->groupBy('SPL_MC', 'SPL_ORDERNO', 'SPL_ITMCD', 'MITM_SPTNO', 'SPL_MS', 'SPL_QTYUSE')->get();
        $RSSub1 = json_decode(json_encode($RSSub1), true);
        $RSSub2 = DB::table("SPLSCN_TBL")->selectRaw("RTRIM(SPLSCN_ITMCD) SPLSCN_ITMCD, SUM(SPLSCN_QTY) ACTQT")
            ->where("SPLSCN_DOC", $request->doc)
            ->where("SPLSCN_CAT", $request->category)
            ->groupBy("SPLSCN_ITMCD")->get();
        $RSSub2 = json_decode(json_encode($RSSub2), true);
        $RS = [];
        foreach ($RSSub1 as &$r) {
            foreach ($RSSub2 as &$s) {
                if ($r['SPL_ITMCD'] == $s['SPLSCN_ITMCD']) {
                    $_currentReq = $r['REQQT'] - $r['PLOTQT'];
                    if ($_currentReq > 0) {
                        if ($_currentReq > $s['ACTQT']) {
                            $r['PLOTQT'] += $s['ACTQT'];
                            $s['ACTQT'] = 0;
                        } else {
                            $r['PLOTQT'] += $_currentReq;
                            $s['ACTQT'] -= $_currentReq;
                        }
                        if ($r['REQQT'] == $r['PLOTQT']) {
                            break;
                        }
                    } else {
                        break;
                    }
                }
            }
            unset($s);
        }
        unset($r);
        foreach ($RSSub1 as $r) {
            if ($r['REQQT'] > $r['PLOTQT']) {
                $RS[] = $r;
            }
        }
        return ['data' => $RS];
    }

    function fixTransactionBySuppplyNumber(Request $request)
    {
        $TOTAL_COLUMN = 9;
        $RSSub1 = DB::table('SPLSCN_TBL')->selectRaw("SPLSCN_ITMCD, CONVERT(DATE, MAX(SPLSCN_LUPDT)) SCANDT,max(SPLSCN_LUPDT) SPLSCN_LUPDT, SUM(SPLSCN_QTY) SCNQT, CONCAT(SPLSCN_DOC, '|',SPLSCN_CAT,'|',SPLSCN_LINE,'|',SPLSCN_FEDR) DOC, MAX(SPLSCN_USRID) SPLSCN_USRID")
            ->where("SPLSCN_DOC", "like", "%" . $request->doc . "%")->where('SPLSCN_SAVED', '1')
            ->groupByRaw("SPLSCN_ITMCD,SPLSCN_DOC,SPLSCN_CAT,SPLSCN_LINE,SPLSCN_FEDR");
        $RSSub2 = DB::table("ITH_TBL")->selectRaw("ITH_DOC,ITH_ITMCD,SUM(ITH_QTY) TQT")
            ->where("ITH_DOC", "LIKE", "%" . $request->doc . "%")->whereIn("ITH_FORM", ["OUT-WH-RM", "CANCELING-RM-PSN-IN"])
            ->groupBy("ITH_DOC", "ITH_ITMCD");
        $data = DB::query()->fromSub($RSSub1, "V1")->selectRaw("V1.*,TQT,SCNQT+ISNULL(TQT,0) BALQT")
            ->leftJoinSub($RSSub2, "V2", function ($join) {
                $join->on("DOC", "=", "ITH_DOC")->on("SPLSCN_ITMCD", "=", "ITH_ITMCD");
            })->whereRaw("SCNQT+ISNULL(TQT,0)!=0")->orderBy("DOC")
            ->get();
        $RSTobeSaved = [];
        $documents = [];
        $sampleDoc = '';
        $affectedRows = 0;
        if (count($data) > 0) {
            $data = json_decode(json_encode($data), true);
            # ambil sample
            foreach ($data as $r) {
                $sampleDoc = substr($r['DOC'], 0, 19);
                $_isFound = false;
                foreach ($documents as &$n) {
                    if ($sampleDoc === $n['DOC']) {
                        $_isFound = true;
                        $n['COUNTER']++;
                        break;
                    }
                }
                unset($n);

                if (!$_isFound) {
                    $documents[] = ['DOC' => $sampleDoc, 'COUNTER' => 1];
                }
            }

            $RSSPL = DB::table("SPL_TBL")->select("SPL_BG")->where("SPL_DOC", $sampleDoc)->first();
            $locations = $this->getPartLocationRoutes($RSSPL->SPL_BG, 'ISSUE-PART');

            foreach ($data as $d) {
                $RSTobeSaved[] = [
                    'ITH_ITMCD' => $d['SPLSCN_ITMCD'],
                    'ITH_DATE' => $d['SCANDT'],
                    'ITH_FORM' => 'OUT-WH-RM',
                    'ITH_DOC' => $d['DOC'],
                    'ITH_QTY' => $d['BALQT'] * -1,
                    'ITH_WH' => $locations['LOC_FROM'],
                    'ITH_REMARK' => 'Fix',
                    'ITH_USRID' => $d['SPLSCN_USRID'],
                    'ITH_LUPDT' => $d['SPLSCN_LUPDT'],
                ];
                $RSTobeSaved[] = [
                    'ITH_ITMCD' => $d['SPLSCN_ITMCD'],
                    'ITH_DATE' => $d['SCANDT'],
                    'ITH_FORM' => 'INC-PRD-RM',
                    'ITH_DOC' => $d['DOC'],
                    'ITH_QTY' => $d['BALQT'],
                    'ITH_WH' => $locations['LOC_TO'],
                    'ITH_REMARK' => 'Fix',
                    'ITH_USRID' => $d['SPLSCN_USRID'],
                    'ITH_LUPDT' => $d['SPLSCN_LUPDT'],
                ];
            }

            if (count($RSTobeSaved) > 1) {
                if (strtoupper($request->save) === 'Y') {
                    foreach (array_chunk($RSTobeSaved, (2100 / $TOTAL_COLUMN) - 2) as $chunk) {
                        $affectedRows = ITH::insert($chunk);
                    }
                }
            }
        }
        return [
            'data' => $data,
            '$documents' => $documents,
            'affectedRows' => $affectedRows
        ];
    }

    function reviseLine(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'document' => 'required',
                'userId' => 'required',
                'rowId' => 'required',
                'itemId' => 'required',
                'qty' => 'required|numeric',
                'transDate' => 'required|date',
            ],
            [
                'document.required' => ':attribute is required',
                'userId.required' => ':attribute is required',
                'rowId.required' => ':attribute is required',
                'itemId.required' => ':attribute is required',
                'qty.required' => ':attribute is required',
                'qty.numeric' => ':attribute should be numeric',
                'transDate.date' => ':attribute should be date',
                'transDate.required' => ':attribute is required',
            ]
        );

        if ($validator->fails()) {
            return response()->json($validator->errors()->all(), 406);
        }

        $data = DB::table("SPLSCN_TBL")
            ->where("SPLSCN_DOC", $request->document)
            ->where("SPLSCN_ID", $request->rowId)
            ->selectRaw("RTRIM(SPLSCN_CAT) SPLSCN_CAT,SPLSCN_LUPDT,SPLSCN_QTY,RTRIM(SPLSCN_LINE) SPLSCN_LINE,RTRIM(SPLSCN_FEDR) SPLSCN_FEDR")
            ->get();
        $PSNHeader = DB::table('SPL_TBL')
            ->where("SPL_DOC", $request->document)
            ->selectRaw("RTRIM(SPL_BG) SPL_BG")
            ->first();
        $locations = $this->getPartLocationRoutes($PSNHeader->SPL_BG, 'EDIT-ISSUE-PART');
        $message = '';
        if (count($data) === 1) {

            $theDoc = '';
            $fixedDateTime = NULL;
            $qtyTx = 0;
            $qtyBefore = NULL;
            foreach ($data as $r) {
                $theDoc = $request->document . "|" . $r->SPLSCN_CAT . "|" . $r->SPLSCN_LINE . "|" . $r->SPLSCN_FEDR;
                $qtyTx = $r->SPLSCN_QTY - $request->qty;
                $qtyBefore = $r->SPLSCN_QTY;

                $kittingDateTime = new DateTime($r->SPLSCN_LUPDT);
                $interval = new DateInterval('PT1S');
                $kittingDateTime->add($interval);

                $initFixedTime = $kittingDateTime->format('H:i:s');
                $fixedTime = $initFixedTime < '07:00:00' ? '23:23:23' : $initFixedTime;
                $fixedDateTime = $request->transDate . " " . $fixedTime;
            }

            DB::beginTransaction();
            try {
                $affectedRows = DB::table("SPLSCN_TBL")
                    ->where("SPLSCN_DOC", $request->document)
                    ->where("SPLSCN_ID", $request->rowId)
                    ->update([
                        "SPLSCN_QTY" => $request->qty,
                        'SPLSCN_USRID' => $request->userId,
                    ]);
                if ($affectedRows) {
                    $isOriginal = SPLSCN_LOG::where('SPLSCN_ID', $request->rowId)->count() ? 1 : 0;
                    SPLSCN_LOG::create([
                        'SPLSCN_ID' => $request->rowId,
                        'SPLSCN_DATATYPE' => $isOriginal,
                        'SPLSCN_OLDQTY' => $qtyBefore,
                        'SPLSCN_NEWQTY' => $request->qty,
                        'created_by' => $request->userId,
                    ]);
                    $dataTobeSaved1 = [
                        'ITH_ITMCD' => $request->itemId,
                        'ITH_DATE' => $request->transDate,
                        'ITH_DOC' => $theDoc,
                        'ITH_LUPDT' => $fixedDateTime,
                        'ITH_USRID' => $request->userId
                    ];

                    $dataTobeSaved2 = [
                        'ITH_ITMCD' => $request->itemId,
                        'ITH_DATE' => $request->transDate,
                        'ITH_DOC' => $theDoc,
                        'ITH_LUPDT' => $fixedDateTime,
                        'ITH_USRID' => $request->userId
                    ];

                    if ($qtyTx > 0) {
                        $dataTobeSaved1['ITH_FORM'] = 'CANCELING-RM-PSN-OUT';
                        $dataTobeSaved1['ITH_QTY'] = $qtyTx * -1;
                        $dataTobeSaved1['ITH_WH'] = $locations['LOC_FROM'];

                        $dataTobeSaved2['ITH_FORM'] = 'CANCELING-RM-PSN-IN';
                        $dataTobeSaved2['ITH_QTY'] = $qtyTx;
                        $dataTobeSaved2['ITH_WH'] = $locations['LOC_TO'];
                    } else {
                        // Reverse
                        $dataTobeSaved1['ITH_FORM'] = 'OUT-WH-RM';
                        $dataTobeSaved1['ITH_QTY'] = $qtyTx;
                        $dataTobeSaved1['ITH_WH'] = $locations['LOC_TO'];

                        $dataTobeSaved2['ITH_FORM'] = 'INC-PRD-RM';
                        $dataTobeSaved2['ITH_QTY'] = $qtyTx * -1;
                        $dataTobeSaved2['ITH_WH'] = $locations['LOC_FROM'];
                    }

                    $dataTobeSaved = array_merge($dataTobeSaved1, $dataTobeSaved2);

                    DB::table('ITH_TBL')->insert($dataTobeSaved);
                }
                DB::commit();
                $message = $affectedRows ? 'OK' : 'could not be updated';
            } catch (Exception $e) {
                DB::rollBack();
            }
        } else {
            $message = 'could not update';
        }

        return [
            'message' => $message,
            'data' => $data
        ];
    }

    function toPickingInstruction(Request $request)
    {
        if (!isset($request->psn)) {
            exit('no data to be found');
        }
        $cpsn = $request->psn;
        $rspsn_group = DB::table('SPL_TBL')->select('SPL_DOC', 'SPL_CAT', 'SPL_LINE', 'SPL_FEDR')
            ->groupByRaw('SPL_DOC, SPL_CAT,SPL_LINE,SPL_FEDR')
            ->where('SPL_DOC', $cpsn)
            ->orderBy('SPL_CAT')
            ->orderBy('SPL_LINE')
            ->orderBy('SPL_FEDR')
            ->get();
        $rspsn_group = json_decode(json_encode($rspsn_group), true);

        if (substr($cpsn, 0, 2) == 'SP') {
            # validasi [assy code type] vs [assy code type in wo]
            $PPSN1 = DB::table('XPPSN1')->select('PPSN1_WONO', 'PPSN1_MDLCD', 'MITM_ITMD1', 'PPSN1_SIMQT')
                ->join('XMITM_V', 'PPSN1_MDLCD', '=', 'MITM_ITMCD')
                ->where('PPSN1_PSNNO', $cpsn)
                ->groupBy('PPSN1_WONO', 'PPSN1_MDLCD', 'MITM_ITMD1', 'PPSN1_SIMQT')->get();
            $PPSN1 = json_decode(json_encode($PPSN1), true);

            $WorkOrdersNumber = [];
            if (count($PPSN1) > 0) {
                foreach ($PPSN1 as $r) {
                    $WorkOrdersNumber[] = $r['PPSN1_WONO'];
                }
            }

            $DifferentAssyTypes = DB::table('DIFFERENT_TYPE_ASSY_WO')->select('*')->whereIn('PDPP_WONO', $WorkOrdersNumber)->get();
            $DifferentAssyTypes = json_decode(json_encode($DifferentAssyTypes), true);

            if (count($DifferentAssyTypes) > 0) {
                foreach ($DifferentAssyTypes as $r) {
                    die($r['PDPP_WONO'] . ' TYPE should be ' . $r['TYPE_FIX']);
                }
            }

            if (DB::table('SPLSCN_TBL')->where('SPLSCN_DOC', $cpsn)->count() > 0) {
                $rshead = DB::select("exec xsp_megapsnhead_bypsn ?", [$cpsn]);
                $rshead = json_decode(json_encode($rshead), true);

                $rsdiff_mch = DB::table('SPL_TBL')->select('SPL_DOC', 'SPL_CAT', 'SPL_LINE', 'SPL_FEDR', 'SPL_ORDERNO', 'SPL_ITMCD')
                    ->where('SPL_DOC', $cpsn)
                    ->groupBy('SPL_DOC', 'SPL_CAT', 'SPL_LINE', 'SPL_FEDR', 'SPL_ORDERNO', 'SPL_ITMCD')
                    ->having(DB::raw("count(*)", '>', 0))
                    ->get();
                $rsdiff_mch = json_decode(json_encode($rsdiff_mch), true);

                $cwos = [];
                $cmodels = [];
                $clotsize = [];
                foreach ($rshead as $r) {
                    if (count($cwos) == 0) {
                        $cwos[] = trim($r['PPSN1_WONO']);
                        $cmodels[] = trim($r['MITM_ITMD1']);
                        $clotsize[] = trim($r['PPSN1_SIMQT']);
                    } else {
                        $ttlwo = count($cwos);
                        $isexist = false;
                        for ($i = 0; $i < $ttlwo; $i++) {
                            if ($cwos[$i] == trim($r['PPSN1_WONO'])) {
                                $isexist = true;
                                break;
                            }
                        }
                        if (!$isexist) {
                            $cwos[] = trim($r['PPSN1_WONO']);
                            $cmodels[] = trim($r['MITM_ITMD1']);
                            $clotsize[] = trim($r['PPSN1_SIMQT']);
                        }
                    }
                }

                $_vrak = DB::table('vinitlocation')->select('MSTLOC_CD', DB::raw("MAX(aliasrack) aliasrack"))->groupBy('MSTLOC_CD');
                $_a = DB::table('SPL_TBL')->leftJoinSub($_vrak, 'VRAK', 'SPL_RACKNO', '=', 'MSTLOC_CD')
                    ->where('SPL_DOC', $cpsn)
                    ->select(
                        'SPL_PROCD',
                        DB::raw("RTRIM(SPL_ORDERNO) SPL_ORDERNO"),
                        'SPL_RACKNO',
                        'aliasrack',
                        'SPL_ITMCD',
                        DB::raw("max(SPL_QTYUSE) SPL_QTYUSE"),
                        'SPL_MS',
                        DB::raw("RTRIM(SPL_MC) SPL_MC"),
                        DB::raw("SUM(SPL_QTYREQ) TTLREQ"),
                        DB::raw("0 TTLSCN"),
                        DB::raw("max(SPL_ITMRMRK) SPL_ITMRMRK"),
                        'SPL_LINE',
                        'SPL_CAT',
                        'SPL_FEDR'
                    )->groupBy('SPL_LINE', 'SPL_CAT', 'SPL_FEDR', 'SPL_PROCD', 'SPL_ORDERNO', 'SPL_RACKNO', 'aliasrack', 'SPL_ITMCD', 'SPL_MC', 'SPL_MS');
                $rs = DB::query()->fromSub($_a, 'a')
                    ->leftJoin('MITM_TBL', 'SPL_ITMCD', '=', 'MITM_ITMCD')
                    ->orderByRaw('SPL_CAT, SPL_LINE, SPL_FEDR, aliasrack, SPL_RACKNO, SPL_ORDERNO, SPL_MC, SPL_ITMCD, SPL_PROCD')
                    ->selectRaw("RTRIM(SPL_PROCD) SPL_PROCD,SPL_ORDERNO,SPL_RACKNO, rtrim(SPL_ITMCD) SPL_ITMCD,rtrim(MITM_SPTNO) MITM_SPTNO, SPL_QTYUSE, SPL_MC, SPL_MS, TTLREQ, TTLSCN, SPL_ITMRMRK,TTLREQ TTLREQB4,SPL_LINE,SPL_CAT,SPL_FEDR")
                    ->get();
                $rs = json_decode(json_encode($rs), true);

                $rsdetail = DB::table("SPLSCN_TBL")
                    ->where('SPLSCN_DOC', $cpsn)
                    ->select(
                        'SPLSCN_ID',
                        'SPLSCN_DOC',
                        'SPLSCN_CAT',
                        'SPLSCN_LINE',
                        'SPLSCN_FEDR',
                        'SPLSCN_ORDERNO',
                        DB::raw("UPPER(SPLSCN_ITMCD) SPLSCN_ITMCD"),
                        'SPLSCN_LOTNO',
                        'SPLSCN_SAVED',
                        'SPLSCN_QTY',
                        'SPLSCN_LUPDT',
                        'SPLSCN_USRID',
                        'SPLSCN_EXPORTED',
                        DB::raw('RTRIM(SPLSCN_PROCD) SPLSCN_PROCD'),
                    )->orderBy('SPLSCN_FEDR')
                    ->orderBy('SPLSCN_LUPDT')->get();
                $rsdetail = json_decode(json_encode($rsdetail), true);

                foreach ($rsdetail as &$d) {
                    if (!array_key_exists("USED", $d)) {
                        $d["USED"] = false;
                    }
                }
                unset($d);

                $firstCategory = '';

                # filter 1
                foreach ($rs as &$r) {
                    if ($firstCategory === '') {
                        $firstCategory = $r['SPL_CAT'];
                    }
                    $think = true;
                    while ($think) {
                        $grasp = false;
                        foreach ($rsdetail as $d) {
                            if (
                                (trim($r['SPL_ORDERNO']) == trim($d['SPLSCN_ORDERNO']))
                                && (trim($r['SPL_ITMCD']) == trim($d['SPLSCN_ITMCD']))
                                && ($r['SPL_PROCD'] == $d['SPLSCN_PROCD'])
                                && ($r['SPL_FEDR'] == $d['SPLSCN_FEDR'])
                                && $d['USED'] == false
                            ) {
                                $grasp = true;
                                break;
                            }
                        }
                        if ($grasp) {
                            foreach ($rsdetail as &$d) {
                                if (
                                    (trim($r['SPL_ORDERNO']) == trim($d['SPLSCN_ORDERNO']))
                                    && (trim($r['SPL_ITMCD']) == trim($d['SPLSCN_ITMCD']))
                                    && ($r['SPL_PROCD'] == $d['SPLSCN_PROCD'])
                                    && $d['USED'] == false
                                ) {
                                    $think2 = true;
                                    while ($think2) {
                                        if ($r['TTLREQ'] > $r['TTLSCN']) {
                                            if ($d['USED'] == false) {
                                                $r['TTLSCN'] += $d['SPLSCN_QTY'];
                                                $d['USED'] = true;
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

                # filter 2
                foreach ($rs as &$r) {
                    if ($firstCategory === '') {
                        $firstCategory = $r['SPL_CAT'];
                    }
                    $think = true;
                    while ($think) {
                        $grasp = false;
                        foreach ($rsdetail as $d) {
                            if (
                                (trim($r['SPL_ORDERNO']) == trim($d['SPLSCN_ORDERNO']))
                                && (trim($r['SPL_ITMCD']) == trim($d['SPLSCN_ITMCD']))
                                && $d['USED'] == false
                            ) {
                                $grasp = true;
                                break;
                            }
                        }
                        if ($grasp) {
                            foreach ($rsdetail as &$d) {
                                if (
                                    (trim($r['SPL_ORDERNO']) == trim($d['SPLSCN_ORDERNO']))
                                    && (trim($r['SPL_ITMCD']) == trim($d['SPLSCN_ITMCD']))
                                    && $d['USED'] == false
                                ) {
                                    $think2 = true;
                                    while ($think2) {
                                        if ($r['TTLREQ'] > $r['TTLSCN']) {
                                            if ($d['USED'] == false) {
                                                $r['TTLSCN'] += $d['SPLSCN_QTY'];
                                                $d['USED'] = true;
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

                foreach ($rs as &$r) {
                    $r['TTLREQ'] -= $r['TTLSCN'];
                    foreach ($rsdiff_mch as $k) {
                        if (trim($r['SPL_ORDERNO']) == trim($k['SPL_ORDERNO']) && trim($r['SPL_ITMCD']) == trim($k['SPL_ITMCD'])) {
                            $r['SPL_ITMCD'] = trim($r['SPL_ITMCD']) . ' *';
                        }
                    }
                }
                unset($r);

                $this->fpdf->AliasNbPages();
                $this->fpdf->AddPage();
                $hgt_p = $this->fpdf->GetPageHeight();
                $this->fpdf->SetAutoPageBreak(true, 1);
                $this->fpdf->SetMargins(0, 0);
                $this->fpdf->SetFont('Arial', '', 6);
                $strheader = '';
                $cury = 4;
                $td_h = 7;
                $xWOcount = count($cwos);
                $firstPage = false;
                $i = 0;
                foreach ($rs as $r) {
                    #Print Outstanding QTY Only
                    if ($r['TTLREQ'] > 0) {
                        $ccat = $r['SPL_CAT'];
                        $cline = $r['SPL_LINE'];
                        $cfedr = $r['SPL_FEDR'];
                        #print header
                        $QrHeadContent = $cpsn . '|' . $r['SPL_CAT'] . "|" . $r['SPL_LINE'] . "|" . $r['SPL_FEDR'];
                        $headQRImage = $this->generateQR(['content' => $QrHeadContent]);
                        if ($strheader != $r['SPL_CAT'] . "|" . $r['SPL_LINE'] . "|" . $r['SPL_FEDR']) {
                            if (($cury + 20) > $hgt_p) {
                                $cury = 4;
                                $this->fpdf->AddPage();
                                if ($firstCategory != $ccat) {
                                    $firstCategory = $ccat;
                                }
                            } else {
                                if (!$firstPage) {
                                    $cury = 4;
                                    $firstPage = true;
                                } else {
                                    $cury += 4;
                                }
                                if ($firstCategory != $ccat) {
                                    $firstCategory = $ccat;
                                    $cury = 4;
                                    $this->fpdf->AddPage();
                                }
                            }

                            $strheader = $r['SPL_CAT'] . "|" . $r['SPL_LINE'] . "|" . $r['SPL_FEDR'];
                            $this->fpdf->Image($headQRImage, 120, $cury + 7);
                            $this->fpdf->SetFont('Arial', '', 6);
                            $clebar = $this->fpdf->GetStringWidth($cpsn) + 40;
                            $this->fpdf->Code128(3, $cury, $cpsn, $clebar, 4);
                            $this->fpdf->Text(3, $cury + 7, $cpsn);
                            $clebar = $this->fpdf->GetStringWidth($ccat) + 17;
                            $this->fpdf->Code128(170, $cury, trim($ccat), $clebar, 4);
                            $this->fpdf->Text(170, $cury + 7, $ccat);
                            $clebar = $this->fpdf->GetStringWidth($cline) + 17;
                            $this->fpdf->Code128(3, $cury + 9, $cline, $clebar, 4);
                            $this->fpdf->Text(3, $cury + 16, $cline);
                            $clebar = $this->fpdf->GetStringWidth($cfedr) + 17;
                            $this->fpdf->Code128(170, $cury + 9, $cfedr, $clebar, 4);
                            $this->fpdf->Text(170, $cury + 16, $cfedr);
                            $this->fpdf->SetXY(90, $cury);
                            $this->fpdf->SetFont('Arial', 'BU', 10);
                            $this->fpdf->Cell(35, 4, 'Picking Instruction', 0, 0, 'C');
                            $this->fpdf->SetXY(100, $cury + 11);
                            $this->fpdf->SetFont('Arial', '', 6);
                            $this->fpdf->Cell(15, 4, 'Page ' . $this->fpdf->PageNo() . ' / {nb}', 1, 0, 'R');
                            $this->fpdf->SetFont('Arial', 'B', 7);
                            $cury = $cury + 18;
                            $isleft = true;
                            for ($j = 0; $j < $xWOcount; $j++) {
                                if (($cury + 10) > $hgt_p) {
                                    $cury = 4;
                                    $this->fpdf->AddPage();
                                    $QrHeadContent = $cpsn . '|' . $strheader;
                                    $this->fpdf->Image($this->generateQR(['content' => $QrHeadContent]), 120, 10);
                                    $this->fpdf->SetFont('Arial', '', 6);
                                    $clebar = $this->fpdf->GetStringWidth($cpsn) + 40;
                                    $this->fpdf->Code128(3, $cury, $cpsn, $clebar, 4);
                                    $this->fpdf->Text(3, $cury + 7, $cpsn);
                                    $clebar = $this->fpdf->GetStringWidth($ccat) + 17;
                                    $this->fpdf->Code128(170, $cury, trim($ccat), $clebar, 4);
                                    $this->fpdf->Text(170, $cury + 7, $ccat);
                                    $clebar = $this->fpdf->GetStringWidth($cline) + 17;
                                    $this->fpdf->Code128(3, $cury + 9, $cline, $clebar, 4);
                                    $this->fpdf->Text(3, $cury + 16, $cline);
                                    $clebar = $this->fpdf->GetStringWidth($cfedr) + 17;
                                    $this->fpdf->Code128(170, $cury + 9, $cfedr, $clebar, 4);
                                    $this->fpdfText(170, $cury + 16, $cfedr);
                                    $this->fpdfSetXY(90, $cury);
                                    $this->fpdfSetFont('Arial', 'BU', 10);
                                    $this->fpdf->Cell(35, 4, 'Picking Instruction', 0, 0, 'C');
                                    $this->fpdf->SetXY(100, $cury + 11);
                                    $this->fpdf->SetFont('Arial', '', 6);
                                    $this->fpdf->Cell(15, 4, 'Page ' . $this->fpdf->PageNo() . ' / {nb}', 1, 0, 'R');
                                    $this->fpdf->SetFont('Arial', 'B', 7);
                                    $cury = $cury + 18;
                                    $isleft = true;
                                }
                                if (($j % 2) == 0) {
                                    $this->fpdf->SetXY(3, $cury);
                                    $this->fpdf->Cell(50, 4, 'Model', 1, 0, 'L');
                                    $this->fpdf->Cell(40, 4, 'Job', 1, 0, 'L');
                                    $this->fpdf->Cell(10, 4, 'Lot Size', 1, 0, 'C');
                                    $this->fpdf->SetXY(3, $cury + 4);
                                    $ttlwidth = $this->fpdf->GetStringWidth($cmodels[$j]);
                                    if ($ttlwidth > 50) {
                                        $ukuranfont = 6.5;
                                        while ($ttlwidth > 50) {
                                            $this->fpdf->SetFont('Arial', '', $ukuranfont);
                                            $ttlwidth = $this->fpdf->GetStringWidth($cmodels[$j]);
                                            $ukuranfont = $ukuranfont - 0.5;
                                        }
                                    }
                                    $this->fpdf->Cell(50, 4, $cmodels[$j], 1, 0, 'L');
                                    $this->fpdf->SetFont('Arial', 'B', 7);
                                    $this->fpdf->Cell(40, 4, $cwos[$j], 1, 0, 'L');
                                    $this->fpdf->Cell(10, 4, number_format($clotsize[$j]), 1, 0, 'L');
                                    $isleft = true;
                                } else {
                                    $this->fpdf->SetXY(105, $cury);
                                    $this->fpdf->Cell(50, 4, 'Model', 1, 0, 'L');
                                    $this->fpdf->Cell(40, 4, 'Job', 1, 0, 'L');
                                    $this->fpdf->Cell(10, 4, 'Lot Size', 1, 0, 'C');
                                    $this->fpdf->SetXY(105, $cury + 4);
                                    $ttlwidth = $this->fpdf->GetStringWidth($cmodels[$j]);
                                    if ($ttlwidth > 50) {
                                        $ukuranfont = 6.5;
                                        while ($ttlwidth > 50) {
                                            $this->fpdf->SetFont('Arial', '', $ukuranfont);
                                            $ttlwidth = $this->fpdf->GetStringWidth($cmodels[$j]);
                                            $ukuranfont = $ukuranfont - 0.5;
                                        }
                                    }
                                    $this->fpdf->Cell(50, 4, $cmodels[$j], 1, 0, 'L');
                                    $this->fpdf->SetFont('Arial', 'B', 7);
                                    $this->fpdf->Cell(40, 4, $cwos[$j], 1, 0, 'L');
                                    $this->fpdf->Cell(10, 4, number_format($clotsize[$j]), 1, 0, 'L');
                                    $cury += 8;
                                    $isleft = false;
                                }
                            }

                            $cury += $isleft ? 9 : 1;
                            if (($cury + 10) > $hgt_p) {
                                $cury = 4;
                                $this->fpdf->AddPage();
                            }
                            $this->fpdf->SetXY(3, $cury);
                            $this->fpdf->Cell(20, 4, 'No Rak', 1, 0, 'L');
                            $this->fpdf->Cell(80, 4, 'Machine No', 1, 0, 'C');
                            $this->fpdf->Cell(25, 4, 'Part No', 1, 0, 'L');
                            $this->fpdf->Cell(30, 4, 'Part Name', 1, 0, 'L');
                            $this->fpdf->Cell(8, 4, 'Use', 1, 0, 'C');
                            $this->fpdf->Cell(13, 4, 'Req.', 1, 0, 'R');
                            $this->fpdf->Cell(13, 4, 'Issued', 1, 0, 'R');
                            $this->fpdf->Cell(13, 4, 'Remain', 1, 0, 'R');
                            $wd2col = 3 + 20 + 80;
                            $cury += 4;
                        } else {
                            if (($cury + 10) > $hgt_p) {
                                $cury = 4;
                                $this->fpdf->AddPage();
                                $this->fpdf->Image($headQRImage, 120, $cury + 7);
                                $this->fpdf->SetFont('Arial', '', 6);
                                $clebar = $this->fpdf->GetStringWidth($cpsn) + 40;
                                $this->fpdf->Code128(3, $cury, $cpsn, $clebar, 4);
                                $this->fpdf->Text(3, $cury + 7, $cpsn);
                                $clebar = $this->fpdf->GetStringWidth($ccat) + 17;
                                $this->fpdf->Code128(170, $cury, trim($ccat), $clebar, 4);
                                $this->fpdf->Text(170, $cury + 7, $ccat);
                                $clebar = $this->fpdf->GetStringWidth($cline) + 17;
                                $this->fpdf->Code128(3, $cury + 9, $cline, $clebar, 4);
                                $this->fpdf->Text(3, $cury + 16, $cline);
                                $clebar = $this->fpdf->GetStringWidth($cfedr) + 17;
                                $this->fpdf->Code128(170, 13, $cfedr, $clebar, 4);
                                $this->fpdf->Text(170, $cury + 16, $cfedr);
                                $this->fpdf->SetXY(90, $cury);
                                $this->fpdf->SetFont('Arial', 'BU', 10);
                                $this->fpdf->Cell(35, 4, 'Picking Instruction', 0, 0, 'C');
                                $this->fpdf->SetXY(100, $cury + 11);
                                $this->fpdf->SetFont('Arial', '', 6);
                                $this->fpdf->Cell(15, 4, 'Page ' . $this->fpdf->PageNo() . ' / {nb}', 1, 0, 'R');
                                $this->fpdf->SetFont('Arial', 'B', 7);
                                $cury = $cury + 18;
                                $isleft = true;
                                for ($j = 0; $j < $xWOcount; $j++) {
                                    if (($cury + 10) > $hgt_p) {
                                        $cury = 4;
                                        $this->fpdf->AddPage();
                                        $this->fpdf->Image($headQRImage, 120, $cury + 7);
                                        $this->fpdf->SetFont('Arial', '', 6);
                                        $clebar = $this->fpdf->GetStringWidth($cpsn) + 40;
                                        $this->fpdf->Code128(3, $cury, $cpsn, $clebar, 4);
                                        $this->fpdf->Text(3, $cury + 7, $cpsn);
                                        $clebar = $this->fpdf->GetStringWidth($ccat) + 17;
                                        $this->fpdf->Code128(170, $cury, trim($ccat), $clebar, 4);
                                        $this->fpdf->Text(170, $cury + 7, $ccat);
                                        $clebar = $this->fpdf->GetStringWidth($cline) + 17;
                                        $this->fpdf->Code128(3, $cury + 9, $cline, $clebar, 4);
                                        $this->fpdf->Text(3, $cury + 16, $cline);
                                        $clebar = $this->fpdf->GetStringWidth($cfedr) + 17;
                                        $this->fpdf->Code128(170, $cury + 9, $cfedr, $clebar, 4);
                                        $this->fpdf->Text(170, $cury + 16, $cfedr);
                                        $this->fpdf->SetXY(90, $cury);
                                        $this->fpdf->SetFont('Arial', 'BU', 10);
                                        $this->fpdf->Cell(35, 4, 'Picking Instruction', 0, 0, 'C');
                                        $this->fpdf->SetXY(100, $cury + 11);
                                        $this->fpdf->SetFont('Arial', '', 6);
                                        $this->fpdf->Cell(15, 4, 'Page ' . $this->fpdf->PageNo() . ' / {nb}', 1, 0, 'R');
                                        $this->fpdf->SetFont('Arial', 'B', 7);
                                        $cury = $cury + 18;
                                        $isleft = true;
                                    }
                                    if (($j % 2) == 0) {
                                        $this->fpdf->SetXY(3, $cury);
                                        $this->fpdf->Cell(50, 4, 'Model', 1, 0, 'L');
                                        $this->fpdf->Cell(40, 4, 'Job', 1, 0, 'L');
                                        $this->fpdf->Cell(10, 4, 'Lot Size', 1, 0, 'C');
                                        $this->fpdf->SetXY(3, $cury + 4);
                                        $ttlwidth = $this->fpdf->GetStringWidth($cmodels[$j]);
                                        if ($ttlwidth > 50) {
                                            $ukuranfont = 6.5;
                                            while ($ttlwidth > 50) {
                                                $this->fpdf->SetFont('Arial', '', $ukuranfont);
                                                $ttlwidth = $this->fpdf->GetStringWidth($cmodels[$j]);
                                                $ukuranfont = $ukuranfont - 0.5;
                                            }
                                        }
                                        $this->fpdf->Cell(50, 4, $cmodels[$j], 1, 0, 'L');
                                        $this->fpdf->SetFont('Arial', 'B', 7);
                                        $this->fpdf->Cell(40, 4, $cwos[$j], 1, 0, 'L');
                                        $this->fpdf->Cell(10, 4, number_format($clotsize[$j]), 1, 0, 'L');
                                        $isleft = true;
                                    } else {
                                        $this->fpdf->SetXY(105, $cury);
                                        $this->fpdf->Cell(50, 4, 'Model', 1, 0, 'L');
                                        $this->fpdf->Cell(40, 4, 'Job', 1, 0, 'L');
                                        $this->fpdf->Cell(10, 4, 'Lot Size', 1, 0, 'C');
                                        $this->fpdf->SetXY(105, $cury + 4);
                                        $ttlwidth = $this->fpdf->GetStringWidth($cmodels[$j]);
                                        if ($ttlwidth > 50) {
                                            $ukuranfont = 6.5;
                                            while ($ttlwidth > 50) {
                                                $this->fpdf->SetFont('Arial', '', $ukuranfont);
                                                $ttlwidth = $this->fpdf->GetStringWidth($cmodels[$j]);
                                                $ukuranfont = $ukuranfont - 0.5;
                                            }
                                        }
                                        $this->fpdf->Cell(50, 4, $cmodels[$j], 1, 0, 'L');
                                        $this->fpdf->SetFont('Arial', 'B', 7);
                                        $this->fpdf->Cell(40, 4, $cwos[$j], 1, 0, 'L');
                                        $this->fpdf->Cell(10, 4, number_format($clotsize[$j]), 1, 0, 'L');
                                        $cury += 8;
                                        $isleft = false;
                                    }
                                }
                                $cury += $isleft ? 9 : 1;
                                if (($cury + 10) > $hgt_p) {
                                    $cury = 4;
                                    $this->fpdf->AddPage();
                                }
                                $this->fpdf->SetXY(3, $cury);
                                $this->fpdf->Cell(20, 4, 'No Rak', 1, 0, 'L');
                                $this->fpdf->Cell(80, 4, 'Machine No', 1, 0, 'C');
                                $this->fpdf->Cell(25, 4, 'Part No', 1, 0, 'L');
                                $this->fpdf->Cell(30, 4, 'Part Name', 1, 0, 'L');
                                $this->fpdf->Cell(8, 4, 'Use', 1, 0, 'C');
                                $this->fpdf->Cell(13, 4, 'Req.', 1, 0, 'R');
                                $this->fpdf->Cell(13, 4, 'Issued', 1, 0, 'R');
                                $this->fpdf->Cell(13, 4, 'Remain', 1, 0, 'R');
                                $wd2col = 3 + 20 + 80;
                                $cury += 4;
                            }
                        }
                        $this->fpdf->SetFont('Arial', '', 8);
                        $this->fpdf->SetXY(3, $cury);
                        if (strpos($r['SPL_RACKNO'], '-') !== false) {
                            $this->fpdf->Cell(20, $td_h, '', 1, 0, 'L');
                            $achar = explode('-', $r['SPL_RACKNO']);
                            $this->fpdf->Text(3.5, $cury + 3, $achar[0]);
                            $this->fpdf->Text(3.5, $cury + 6, $achar[1]);
                        } else {
                            $this->fpdf->Cell(20, $td_h, $r['SPL_RACKNO'], 1, 0, 'L');
                        }
                        $_contentToEncode = $r['SPL_MC'] . '|' . $r['SPL_PROCD'] . '|' . $r['SPL_ORDERNO'];
                        $lebar = $this->fpdf->GetStringWidth($_contentToEncode) + 17;
                        $clebar = $this->fpdf->GetStringWidth($_contentToEncode) + 16;
                        $strx = $wd2col - ($lebar + 3);
                        if (($i % 2) > 0) {
                            $this->fpdf->Code128($wd2col - 80 + 2, $cury + 1.5, $_contentToEncode, $clebar, 3);
                            $this->fpdf->Cell(80, $td_h, $r['SPL_ORDERNO'], 1, 0, 'R');
                            $this->fpdf->SetFont('Arial', '', 4);
                            $this->fpdf->Text($wd2col - 5, $cury + 1.5, $r['SPL_PROCD']);
                            $this->fpdf->SetFont('Arial', '', 8);
                        } else {
                            $this->fpdf->Code128($strx, $cury + 1.5, $_contentToEncode, $clebar, 3);
                            $this->fpdf->Cell(80, $td_h, $r['SPL_ORDERNO'], 1, 0, 'L');
                            $this->fpdf->SetFont('Arial', '', 4);
                            $this->fpdf->Text($wd2col - 79, $cury + 1.5, $r['SPL_PROCD']);
                            $this->fpdf->SetFont('Arial', '', 8);
                        }
                        $this->fpdf->Cell(25, $td_h, trim($r['SPL_ITMCD']), 1, 0, 'L');
                        $ttlwidth = $this->fpdf->GetStringWidth(trim($r['MITM_SPTNO']));
                        if ($ttlwidth > 28) {
                            $ukuranfont = 7.5;
                            while ($ttlwidth > 28) {
                                $this->fpdf->SetFont('Arial', '', $ukuranfont);
                                $ttlwidth = $this->fpdf->GetStringWidth(trim($r['MITM_SPTNO']));
                                $ukuranfont = $ukuranfont - 0.5;
                            }
                        }
                        $this->fpdf->Cell(30, $td_h, trim($r['MITM_SPTNO']), 1, 0, 'L');
                        $this->fpdf->SetFont('Arial', '', 8);
                        $this->fpdf->Cell(8, $td_h, $r['SPL_QTYUSE'] * 1, 1, 0, 'C');
                        $this->fpdf->Cell(13, $td_h, number_format($r['TTLREQB4']), 1, 0, 'R');
                        $this->fpdf->Cell(13, $td_h, number_format($r['TTLREQB4'] - $r['TTLREQ']), 1, 0, 'R');
                        $this->fpdf->Cell(13, $td_h, number_format($r['TTLREQ']), 1, 0, 'R');
                        $cury += $td_h;
                        $i++;
                    }
                }
            } else {
                foreach ($rspsn_group as $rh) {
                    $ccat = trim($rh['SPL_CAT']);
                    $cline = trim($rh['SPL_LINE']);
                    $cfedr = trim($rh['SPL_FEDR']);
                    $QrHeadContent = $cpsn . '|' . $rh['SPL_CAT'] . "|" . $rh['SPL_LINE'] . "|" . $rh['SPL_FEDR'];
                    $headQRImage = $this->generateQR(['content' => $QrHeadContent]);
                    $rshead = DB::select("exec xsp_megapsnhead ?, ?, ?", [$cpsn, $cline, $cfedr]);
                    $rshead = json_decode(json_encode($rshead), true);

                    $rsdiff_mch = DB::table("SPL_TBL")->selectRaw("SPL_DOC, SPL_CAT, SPL_LINE, SPL_FEDR,SPL_ORDERNO,SPL_ITMCD")
                        ->where('SPL_DOC', $cpsn)
                        ->where('SPL_CAT', $ccat)
                        ->where('SPL_LINE', $cline)
                        ->where('SPL_FEDR', $cfedr)
                        ->groupByRaw('SPL_DOC, SPL_CAT, SPL_LINE, SPL_FEDR,SPL_ORDERNO,SPL_ITMCD')
                        ->havingRaw("COUNT(*)>1")->get();
                    $rsdiff_mch = json_decode(json_encode($rsdiff_mch), true);

                    $cwos = [];
                    $cmodels = [];
                    $clotsize = [];
                    foreach ($rshead as $r) {
                        if (count($cwos) == 0) {
                            $cwos[] = trim($r['PPSN1_WONO']);
                            $cmodels[] = trim($r['MITM_ITMD1']);
                            $clotsize[] = trim($r['PPSN1_SIMQT']);
                        } else {
                            $ttlwo = count($cwos);
                            $isexist = false;
                            for ($i = 0; $i < $ttlwo; $i++) {
                                if ($cwos[$i] == trim($r['PPSN1_WONO'])) {
                                    $isexist = true;
                                    break;
                                }
                            }
                            if (!$isexist) {
                                $cwos[] = trim($r['PPSN1_WONO']);
                                $cmodels[] = trim($r['MITM_ITMD1']);
                                $clotsize[] = trim($r['PPSN1_SIMQT']);
                            }
                        }
                    }

                    $_vrak = DB::table('vinitlocation')->select('MSTLOC_CD', DB::raw("MAX(aliasrack) aliasrack"))->groupBy('MSTLOC_CD');
                    $_a = DB::table('SPL_TBL')->leftJoinSub($_vrak, 'VRAK', 'SPL_RACKNO', '=', 'MSTLOC_CD')
                        ->where('SPL_DOC', $cpsn)
                        ->where('SPL_CAT', $ccat)
                        ->where('SPL_LINE', $cline)
                        ->where('SPL_FEDR', $cfedr)
                        ->select(
                            'SPL_PROCD',
                            DB::raw("RTRIM(SPL_ORDERNO) SPL_ORDERNO"),
                            'SPL_RACKNO',
                            'aliasrack',
                            'SPL_ITMCD',
                            DB::raw("max(SPL_QTYUSE) SPL_QTYUSE"),
                            'SPL_MS',
                            DB::raw("RTRIM(SPL_MC) SPL_MC"),
                            DB::raw("SUM(SPL_QTYREQ) TTLREQ"),
                            DB::raw("0 TTLSCN"),
                            DB::raw("max(SPL_ITMRMRK) SPL_ITMRMRK"),
                            'SPL_LINE',
                            'SPL_CAT',
                            'SPL_FEDR'
                        )->groupBy('SPL_LINE', 'SPL_CAT', 'SPL_FEDR', 'SPL_PROCD', 'SPL_ORDERNO', 'SPL_RACKNO', 'aliasrack', 'SPL_ITMCD', 'SPL_MC', 'SPL_MS');

                    $rs = DB::query()->fromSub($_a, 'a')
                        ->leftJoin('MITM_TBL', 'SPL_ITMCD', '=', 'MITM_ITMCD')
                        ->orderByRaw('aliasrack,SPL_RACKNO,SPL_ORDERNO,SPL_MC,SPL_ITMCD,SPL_PROCD')
                        ->selectRaw("SPL_PROCD,SPL_ORDERNO,SPL_RACKNO, rtrim(SPL_ITMCD) SPL_ITMCD,rtrim(MITM_SPTNO) MITM_SPTNO, SPL_QTYUSE, SPL_MC, SPL_MS, TTLREQ, TTLSCN, SPL_ITMRMRK,TTLREQ TTLREQB4,SPL_LINE,SPL_CAT,SPL_FEDR")
                        ->get();
                    $rs = json_decode(json_encode($rs), true);


                    $rsdetail = DB::table("SPLSCN_TBL")->selectRaw("SPLSCN_ID,SPLSCN_DOC,SPLSCN_CAT,SPLSCN_LINE,SPLSCN_FEDR,SPLSCN_ORDERNO,UPPER(SPLSCN_ITMCD) SPLSCN_ITMCD,SPLSCN_LOTNO,SPLSCN_SAVED,
                                SPLSCN_QTY,SPLSCN_LUPDT,SPLSCN_USRID,SPLSCN_EXPORTED")
                        ->where('SPLSCN_DOC', $cpsn)
                        ->where('SPLSCN_CAT', $ccat)
                        ->where('SPLSCN_LINE', $cline)
                        ->where('SPLSCN_FEDR', $cfedr)
                        ->orderByRaw("SPLSCN_FEDR,SPLSCN_LUPDT ASC")->get();
                    $rsdetail = json_decode(json_encode($rsdetail), true);

                    foreach ($rsdetail as &$d) {
                        if (!array_key_exists("USED", $d)) {
                            $d["USED"] = false;
                        }
                    }
                    unset($d);

                    $this->fpdf->AliasNbPages();
                    $this->fpdf->AddPage();
                    $hgt_p = $this->fpdf->GetPageHeight();
                    $this->fpdf->SetAutoPageBreak(true, 1);
                    $this->fpdf->SetMargins(0, 0);
                    $this->fpdf->Image($headQRImage, 120, 10);
                    $this->fpdf->SetFont('Arial', '', 6);
                    $clebar = $this->fpdf->GetStringWidth($cpsn) + 40;
                    $this->fpdf->Code128(3, 4, $cpsn, $clebar, 4);
                    $this->fpdf->Text(3, 11, $cpsn);
                    $clebar = $this->fpdf->GetStringWidth($ccat) + 17;
                    if ($ccat == '') {
                        $ccat = '??';
                    }
                    $this->fpdf->Code128(170, 4, $ccat, $clebar, 4);
                    $this->fpdf->Text(170, 11, $ccat);
                    $clebar = $this->fpdf->GetStringWidth($cline) + 17;
                    $this->fpdf->Code128(3, 13, $cline, $clebar, 4);
                    $this->fpdf->Text(3, 20, $cline);
                    $clebar = $this->fpdf->GetStringWidth($cfedr) + 17;
                    $this->fpdf->Code128(170, 13, $cfedr, $clebar, 4);
                    $this->fpdf->Text(170, 20, $cfedr);
                    $this->fpdf->SetXY(90, 4);
                    $this->fpdf->SetFont('Arial', 'BU', 10);
                    $this->fpdf->Cell(35, 4, 'Picking Instruction', 0, 0, 'C');
                    $this->fpdf->SetXY(100, 15);
                    $this->fpdf->SetFont('Arial', '', 6);
                    $this->fpdf->Cell(15, 4, 'Page ' . $this->fpdf->PageNo() . ' / {nb}', 1, 0, 'R');
                    $this->fpdf->SetFont('Arial', 'B', 7);
                    $cury = 22;
                    $isleft = true;

                    $xWOcount = count($cwos);

                    for ($j = 0; $j < $xWOcount; $j++) { // print job info
                        if (($j % 2) == 0) {
                            $this->fpdf->SetXY(3, $cury);
                            $this->fpdf->SetFont('Arial', '', 7);
                            $this->fpdf->Cell(50, 4, 'Model', 1, 0, 'L');
                            $this->fpdf->Cell(40, 4, 'Job', 1, 0, 'L');
                            $this->fpdf->Cell(10, 4, 'Lot Size', 1, 0, 'C');
                            $this->fpdf->SetFont('Arial', 'B', 7);
                            $this->fpdf->SetXY(3, $cury + 4);
                            $ttlwidth = $this->fpdf->GetStringWidth($cmodels[$j]);
                            if ($ttlwidth > 50) {
                                $ukuranfont = 6.5;
                                while ($ttlwidth > 50) {
                                    $this->fpdf->SetFont('Arial', '', $ukuranfont);
                                    $ttlwidth = $this->fpdf->GetStringWidth($cmodels[$j]);
                                    $ukuranfont = $ukuranfont - 0.5;
                                }
                            }
                            $this->fpdf->Cell(50, 4, $cmodels[$j], 1, 0, 'L');
                            $this->fpdf->SetFont('Arial', 'B', 7);
                            $this->fpdf->Cell(40, 4, $cwos[$j], 1, 0, 'L');
                            $this->fpdf->Cell(10, 4, number_format($clotsize[$j]), 1, 0, 'L');
                            $isleft = true;
                        } else {
                            $this->fpdf->SetXY(105, $cury);
                            $this->fpdf->SetFont('Arial', '', 7);
                            $this->fpdf->Cell(50, 4, 'Model', 1, 0, 'L');
                            $this->fpdf->Cell(40, 4, 'Job', 1, 0, 'L');
                            $this->fpdf->Cell(10, 4, 'Lot Size', 1, 0, 'C');
                            $this->fpdf->SetFont('Arial', 'B', 7);
                            $this->fpdf->SetXY(105, $cury + 4);
                            $ttlwidth = $this->fpdf->GetStringWidth($cmodels[$j]);
                            if ($ttlwidth > 50) {
                                $ukuranfont = 6.5;
                                while ($ttlwidth > 50) {
                                    $this->fpdf->SetFont('Arial', '', $ukuranfont);
                                    $ttlwidth = $this->fpdf->GetStringWidth($cmodels[$j]);
                                    $ukuranfont = $ukuranfont - 0.5;
                                }
                            }
                            $this->fpdf->Cell(50, 4, $cmodels[$j], 1, 0, 'L');
                            $this->fpdf->SetFont('Arial', 'B', 7);
                            $this->fpdf->Cell(40, 4, $cwos[$j], 1, 0, 'L');
                            $this->fpdf->Cell(10, 4, number_format($clotsize[$j]), 1, 0, 'L');
                            $cury += 8;
                            $isleft = false;
                        }
                    }

                    $cury += $isleft ? 9 : 1;
                    #old way
                    $this->fpdf->SetXY(3, $cury);
                    $this->fpdf->Cell(20, 4, 'No Rak', 1, 0, 'L');
                    $this->fpdf->Cell(80, 4, 'Machine No', 1, 0, 'C');
                    $this->fpdf->Cell(25, 4, 'Part No', 1, 0, 'L');
                    $this->fpdf->Cell(30, 4, 'Part Name', 1, 0, 'L');
                    $this->fpdf->Cell(8, 4, 'Use', 1, 0, 'C');
                    $this->fpdf->Cell(13, 4, 'Req.', 1, 0, 'R');
                    $this->fpdf->Cell(13, 4, 'Issued', 1, 0, 'R');
                    $this->fpdf->Cell(13, 4, 'Remain', 1, 0, 'R');
                    #end old way
                    $wd2col = 3 + 20 + 80;
                    $cury += 4;
                    $td_h = 7;

                    foreach ($rs as &$r) {
                        $think = true;
                        while ($think) {
                            $grasp = false;
                            foreach ($rsdetail as $d) {
                                if ((trim($r['SPL_ORDERNO']) == trim($d['SPLSCN_ORDERNO'])) && (trim($r['SPL_ITMCD']) == trim($d['SPLSCN_ITMCD'])) && $d['USED'] == false) {
                                    $grasp = true;
                                    break;
                                }
                            }
                            if ($grasp) {
                                foreach ($rsdetail as &$d) {
                                    if ((trim($r['SPL_ORDERNO']) == trim($d['SPLSCN_ORDERNO'])) && (trim($r['SPL_ITMCD']) == trim($d['SPLSCN_ITMCD'])) && $d['USED'] == false) {
                                        $think2 = true;
                                        while ($think2) {
                                            if ($r['TTLREQ'] > $r['TTLSCN']) {
                                                if ($d['USED'] == false) {
                                                    $r['TTLSCN'] += $d['SPLSCN_QTY'];
                                                    $d['USED'] = true;
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

                    foreach ($rs as &$r) {
                        $r['TTLREQ'] -= $r['TTLSCN'];
                        foreach ($rsdiff_mch as $k) {
                            if (trim($r['SPL_ORDERNO']) == trim($k['SPL_ORDERNO']) && trim($r['SPL_ITMCD']) == trim($k['SPL_ITMCD'])) {
                                $r['SPL_ITMCD'] = trim($r['SPL_ITMCD']) . ' *';
                            }
                        }
                    }
                    unset($r);

                    #current way
                    $i = 1;
                    foreach ($rs as $r) {
                        if ($r['TTLREQ'] > 0) {
                            if (($cury + 10) > $hgt_p) {
                                $this->fpdf->AddPage();
                                $this->fpdf->SetFont('Arial', '', 6);
                                $this->fpdf->Image($headQRImage, 120, 10);
                                $clebar = $this->fpdf->GetStringWidth($cpsn) + 40;
                                $this->fpdf->Code128(3, 4, $cpsn, $clebar, 4);
                                $this->fpdf->Text(3, 11, $cpsn);
                                $clebar = $this->fpdf->GetStringWidth($ccat) + 17;
                                $this->fpdf->Code128(170, 4, trim($ccat), $clebar, 4);
                                $this->fpdf->Text(170, 11, $ccat);
                                $clebar = $this->fpdf->GetStringWidth($cline) + 17;
                                $this->fpdf->Code128(3, 13, $cline, $clebar, 4);
                                $this->fpdf->Text(3, 20, $cline);
                                $clebar = $this->fpdf->GetStringWidth($cfedr) + 17;
                                $this->fpdf->Code128(170, 13, $cfedr, $clebar, 4);
                                $this->fpdf->Text(170, 20, $cfedr);
                                $this->fpdf->SetXY(90, 4);
                                $this->fpdf->SetFont('Arial', 'BU', 10);
                                $this->fpdf->Cell(35, 4, 'Picking Instruction', 0, 0, 'C');
                                $this->fpdf->SetXY(100, 15);
                                $this->fpdf->SetFont('Arial', '', 6);
                                $this->fpdf->Cell(15, 4, 'Page ' . $this->fpdf->PageNo() . ' / {nb}', 1, 0, 'R');
                                $this->fpdf->SetFont('Arial', 'B', 7);
                                $cury = 22;
                                $isleft = true;
                                for ($j = 0; $j < $xWOcount; $j++) {
                                    if (($j % 2) == 0) {
                                        $this->fpdf->SetXY(3, $cury);
                                        $this->fpdf->Cell(50, 4, 'Model', 1, 0, 'L');
                                        $this->fpdf->Cell(40, 4, 'Job', 1, 0, 'L');
                                        $this->fpdf->Cell(10, 4, 'Lot Size', 1, 0, 'C');
                                        $this->fpdf->SetXY(3, $cury + 4);
                                        $ttlwidth = $this->fpdf->GetStringWidth($cmodels[$j]);
                                        if ($ttlwidth > 50) {
                                            $ukuranfont = 6.5;
                                            while ($ttlwidth > 50) {
                                                $this->fpdf->SetFont('Arial', '', $ukuranfont);
                                                $ttlwidth = $this->fpdf->GetStringWidth($cmodels[$j]);
                                                $ukuranfont = $ukuranfont - 0.5;
                                            }
                                        }
                                        $this->fpdf->Cell(50, 4, $cmodels[$j], 1, 0, 'L');
                                        $this->fpdf->SetFont('Arial', 'B', 7);
                                        $this->fpdf->Cell(40, 4, $cwos[$j], 1, 0, 'L');
                                        $this->fpdf->Cell(10, 4, number_format($clotsize[$j]), 1, 0, 'L');
                                        $isleft = true;
                                    } else {
                                        $this->fpdf->SetXY(105, $cury);
                                        $this->fpdf->Cell(50, 4, 'Model', 1, 0, 'L');
                                        $this->fpdf->Cell(40, 4, 'Job', 1, 0, 'L');
                                        $this->fpdf->Cell(10, 4, 'Lot Size', 1, 0, 'C');
                                        $this->fpdf->SetXY(105, $cury + 4);
                                        $ttlwidth = $this->fpdf->GetStringWidth($cmodels[$j]);
                                        if ($ttlwidth > 50) {
                                            $ukuranfont = 6.5;
                                            while ($ttlwidth > 50) {
                                                $this->fpdf->SetFont('Arial', '', $ukuranfont);
                                                $ttlwidth = $this->fpdf->GetStringWidth($cmodels[$j]);
                                                $ukuranfont = $ukuranfont - 0.5;
                                            }
                                        }
                                        $this->fpdf->Cell(50, 4, $cmodels[$j], 1, 0, 'L');
                                        $this->fpdf->SetFont('Arial', 'B', 7);
                                        $this->fpdf->Cell(40, 4, $cwos[$j], 1, 0, 'L');
                                        $this->fpdf->Cell(10, 4, number_format($clotsize[$j]), 1, 0, 'L');
                                        $cury += 8;
                                        $isleft = false;
                                    }
                                }

                                $cury += $isleft ? 9 : 1;
                                $this->fpdf->SetXY(3, $cury);
                                $this->fpdf->Cell(20, 4, 'No Rak', 1, 0, 'L');
                                $this->fpdf->Cell(80, 4, 'Machine No', 1, 0, 'C');
                                $this->fpdf->Cell(25, 4, 'Part No', 1, 0, 'L');
                                $this->fpdf->Cell(30, 4, 'Part Name', 1, 0, 'L');
                                $this->fpdf->Cell(8, 4, 'Use', 1, 0, 'C');
                                $this->fpdf->Cell(13, 4, 'Req.', 1, 0, 'R');
                                $this->fpdf->Cell(13, 4, 'Issued', 1, 0, 'R');
                                $this->fpdf->Cell(13, 4, 'Remain', 1, 0, 'R');
                                $wd2col = 3 + 20 + 80;
                                $cury += 4;
                            }
                            $this->fpdf->SetFont('Arial', '', 8);
                            $this->fpdf->SetXY(3, $cury);

                            if (strpos($r['SPL_RACKNO'], '-') !== false) {
                                $this->fpdf->Cell(20, $td_h, '', 1, 0, 'L');
                                $achar = explode('-', $r['SPL_RACKNO']);
                                $this->fpdf->Text(3.5, $cury + 3, $achar[0]);
                                $this->fpdf->Text(3.5, $cury + 6, $achar[1]);
                            } else {
                                $this->fpdf->Cell(20, $td_h, $r['SPL_RACKNO'], 1, 0, 'L');
                            }
                            $_contentToEncode = $r['SPL_MC'] . '|' . $r['SPL_PROCD'] . '|' . trim($r['SPL_ORDERNO']);
                            $lebar = $this->fpdf->GetStringWidth($_contentToEncode) + 17;
                            $clebar = $this->fpdf->GetStringWidth($_contentToEncode) + 16;
                            $strx = $wd2col - ($lebar + 3);
                            if (($i % 2) > 0) {
                                $this->fpdf->Code128($wd2col - 80 + 2, $cury + 1.5, $_contentToEncode, $clebar, 3);
                                $this->fpdf->Cell(80, $td_h, $r['SPL_ORDERNO'], 1, 0, 'R');
                                $this->fpdf->SetFont('Arial', '', 4);
                                $this->fpdf->Text($wd2col - 5, $cury + 1.5, $r['SPL_PROCD']);
                                $this->fpdf->SetFont('Arial', '', 8);
                            } else {
                                $this->fpdf->Code128($strx, $cury + 1.5, $_contentToEncode, $clebar, 3);
                                $this->fpdf->Cell(80, $td_h, $r['SPL_ORDERNO'], 1, 0, 'L');
                                $this->fpdf->SetFont('Arial', '', 4);
                                $this->fpdf->Text($wd2col - 79, $cury + 1.5, $r['SPL_PROCD']);
                                $this->fpdf->SetFont('Arial', '', 8);
                            }

                            $this->fpdf->Cell(25, $td_h, trim($r['SPL_ITMCD']), 1, 0, 'L');
                            $ttlwidth = $this->fpdf->GetStringWidth(trim($r['MITM_SPTNO']));
                            if ($ttlwidth > 28) {
                                $ukuranfont = 7.5;
                                while ($ttlwidth > 28) {
                                    $this->fpdf->SetFont('Arial', '', $ukuranfont);
                                    $ttlwidth = $this->fpdf->GetStringWidth(trim($r['MITM_SPTNO']));
                                    $ukuranfont = $ukuranfont - 0.5;
                                }
                            }
                            $this->fpdf->Cell(30, $td_h, trim($r['MITM_SPTNO']), 1, 0, 'L');
                            $this->fpdf->SetFont('Arial', '', 8);
                            $this->fpdf->Cell(8, $td_h, $r['SPL_QTYUSE'] * 1, 1, 0, 'C');
                            $this->fpdf->Cell(13, $td_h, number_format($r['TTLREQB4']), 1, 0, 'R');
                            $this->fpdf->Cell(13, $td_h, number_format($r['TTLREQB4'] - $r['TTLREQ']), 1, 0, 'R');
                            $this->fpdf->Cell(13, $td_h, number_format($r['TTLREQ']), 1, 0, 'R');
                            $cury += $td_h;
                            $i++;
                        }
                    }
                    #end current way
                }
            }
        } else {
            if (DB::table("SPL_TBL")->select("SPL_DOC")->where('SPL_DOC', $cpsn)->whereNotNull('SPL_APPRV_TM')->count() == 0) {
                die($cpsn . ' should be approved first');
            }

            foreach ($rspsn_group as $rh) {
                $ccat = trim($rh['SPL_CAT']);
                $cline = trim($rh['SPL_LINE']);
                $cfedr = trim($rh['SPL_FEDR']);

                $QrHeadContent = $cpsn . '|' . $rh['SPL_CAT'] . "|" . $rh['SPL_LINE'] . "|" . $rh['SPL_FEDR'];
                $headQRImage = $this->generateQR(['content' => $QrHeadContent]);

                $rshead = DB::table("SPL_TBL")->selectRaw("SPL_REFDOCNO,MAX(SPL_REFDOCCAT) REFDOCCAT")
                    ->groupBy('SPL_REFDOCNO')
                    ->where('SPL_DOC', $cpsn)->get();
                $rshead = json_decode(json_encode($rshead), true);

                $_vrak = DB::table('vinitlocation')->select('MSTLOC_CD', DB::raw("MAX(aliasrack) aliasrack"))->groupBy('MSTLOC_CD');
                $_a = DB::table('SPL_TBL')->leftJoinSub($_vrak, 'VRAK', 'SPL_RACKNO', '=', 'MSTLOC_CD')
                    ->where('SPL_DOC', $cpsn)
                    ->where('SPL_CAT', $ccat)
                    ->where('SPL_LINE', $cline)
                    ->where('SPL_FEDR', $cfedr)
                    ->select(
                        'SPL_PROCD',
                        DB::raw("RTRIM(SPL_ORDERNO) SPL_ORDERNO"),
                        'SPL_RACKNO',
                        'aliasrack',
                        'SPL_ITMCD',
                        DB::raw("max(SPL_QTYUSE) SPL_QTYUSE"),
                        'SPL_MS',
                        DB::raw("RTRIM(SPL_MC) SPL_MC"),
                        DB::raw("SUM(SPL_QTYREQ) TTLREQ"),
                        DB::raw("0 TTLSCN"),
                        DB::raw("max(SPL_ITMRMRK) SPL_ITMRMRK"),
                        'SPL_LINE',
                        'SPL_CAT',
                        'SPL_FEDR'
                    )->groupBy('SPL_LINE', 'SPL_CAT', 'SPL_FEDR', 'SPL_PROCD', 'SPL_ORDERNO', 'SPL_RACKNO', 'aliasrack', 'SPL_ITMCD', 'SPL_MC', 'SPL_MS');

                $rs = DB::query()->fromSub($_a, 'a')
                    ->leftJoin('MITM_TBL', 'SPL_ITMCD', '=', 'MITM_ITMCD')
                    ->orderByRaw('SPL_CAT, SPL_LINE, SPL_FEDR, aliasrack, SPL_RACKNO, SPL_ORDERNO, SPL_MC, SPL_ITMCD, SPL_PROCD')
                    ->selectRaw("SPL_PROCD,SPL_ORDERNO,SPL_RACKNO, rtrim(SPL_ITMCD) SPL_ITMCD,rtrim(MITM_SPTNO) MITM_SPTNO, SPL_QTYUSE, SPL_MC, SPL_MS, TTLREQ, TTLSCN, SPL_ITMRMRK,TTLREQ TTLREQB4,SPL_LINE,SPL_CAT,SPL_FEDR")
                    ->get();
                $rs = json_decode(json_encode($rs), true);

                $rsdetail = DB::table("SPLSCN_TBL")->selectRaw("SPLSCN_ID,SPLSCN_DOC,SPLSCN_CAT,SPLSCN_LINE,SPLSCN_FEDR,SPLSCN_ORDERNO,UPPER(SPLSCN_ITMCD) SPLSCN_ITMCD,SPLSCN_LOTNO,SPLSCN_SAVED,
                                SPLSCN_QTY,SPLSCN_LUPDT,SPLSCN_USRID,SPLSCN_EXPORTED")
                    ->where('SPLSCN_DOC', $cpsn)
                    ->where('SPLSCN_CAT', $ccat)
                    ->where('SPLSCN_LINE', $cline)
                    ->where('SPLSCN_FEDR', $cfedr)
                    ->orderByRaw("SPLSCN_FEDR,SPLSCN_LUPDT ASC")->get();
                $rsdetail = json_decode(json_encode($rsdetail), true);

                foreach ($rs as &$r) {
                    foreach ($rsdetail as &$d) {
                        if ($d['SPLSCN_QTY'] > 0) {
                            if ($r['SPL_ITMCD'] == $d['SPLSCN_ITMCD']) {
                                if ($r['TTLREQ'] > 0) {
                                    if ($r['TTLREQ'] == $d['SPLSCN_QTY']) {
                                        $r['TTLREQ'] = 0;
                                        $d['SPLSCN_QTY'] = 0;
                                    } elseif ($r['TTLREQ'] > $d['SPLSCN_QTY']) {
                                        $r['TTLREQ'] -= $d['SPLSCN_QTY'];
                                        $d['SPLSCN_QTY'] = 0;
                                    } elseif ($r['TTLREQ'] < $d['SPLSCN_QTY']) {
                                        $d['SPLSCN_QTY'] -= $r['TTLREQ'];
                                        $r['TTLREQ'] = 0;
                                    }
                                }
                            }
                        }
                    }
                    unset($d);
                }
                unset($r);

                $this->fpdf->AliasNbPages();
                $this->fpdf->AddPage();
                $hgt_p = $this->fpdf->GetPageHeight();
                $this->fpdf->SetAutoPageBreak(true, 1);
                $this->fpdf->SetMargins(0, 0);
                $this->fpdf->Image($headQRImage, 120, 10);
                $this->fpdf->SetFont('Arial', '', 6);
                $clebar = $this->fpdf->GetStringWidth($cpsn) + 40;
                $this->fpdf->Code128(3, 4, $cpsn, $clebar, 4);
                $this->fpdf->Text(3, 11, $cpsn);
                $clebar = $this->fpdf->GetStringWidth($ccat) + 17;
                if ($ccat == '') {
                    $ccat = '??';
                }
                $this->fpdf->Code128(170, 4, $ccat, $clebar, 4);
                $this->fpdf->Text(170, 11, $ccat);
                $clebar = $this->fpdf->GetStringWidth($cline) + 17;
                $this->fpdf->Code128(3, 13, $cline, $clebar, 4);
                $this->fpdf->Text(3, 20, $cline);
                $clebar = $this->fpdf->GetStringWidth($cfedr) + 17;
                $this->fpdf->Code128(170, 13, $cfedr, $clebar, 4);
                $this->fpdf->Text(170, 20, $cfedr);
                $this->fpdf->SetFont('Arial', 'BU', 10);
                $this->fpdf->SetXY(90, 4);
                $this->fpdf->Cell(35, 4, 'Picking Instruction', 0, 0, 'C');
                $this->fpdf->SetXY(100, 15);
                $this->fpdf->SetFont('Arial', '', 6);
                $this->fpdf->Cell(15, 4, 'Page ' . $this->fpdf->PageNo() . ' / {nb}', 1, 0, 'R');
                $this->fpdf->SetFont('Arial', 'B', 7);
                $cury = 22;
                $isleft = true;
                $nom = 0;
                $PSNDocAsReff = [];
                foreach ($rshead as $r) {
                    if ($r['REFDOCCAT'] == 'PSN') {
                        $PSNDocAsReff[] = $r['SPL_REFDOCNO'];
                    }
                    if (($nom % 2) == 0) {
                        $this->fpdf->SetXY(3, $cury);
                        $this->fpdf->SetFont('Arial', '', 7);
                        $this->fpdf->Cell(100, 4, 'Reff Document', 1, 0, 'C');
                        $this->fpdf->SetFont('Arial', 'B', 7);
                        $this->fpdf->SetXY(3, $cury + 4);
                        $ttlwidth = $this->fpdf->GetStringWidth($r['SPL_REFDOCNO']);
                        if ($ttlwidth > 100) {
                            $ukuranfont = 6.5;
                            while ($ttlwidth > 50) {
                                $this->fpdf->SetFont('Arial', '', $ukuranfont);
                                $ttlwidth = $this->fpdf->GetStringWidth($r['SPL_REFDOCNO']);
                                $ukuranfont = $ukuranfont - 0.5;
                            }
                        }
                        $this->fpdf->Cell(100, 4, $r['SPL_REFDOCNO'], 1, 0, 'L');
                        $isleft = true;
                    } else {
                        $this->fpdf->SetXY(105, $cury);
                        $this->fpdf->SetFont('Arial', '', 7);
                        $this->fpdf->Cell(100, 4, 'Reff Document', 1, 0, 'C');
                        $this->fpdf->SetFont('Arial', 'B', 7);
                        $this->fpdf->SetXY(105, $cury + 4);
                        $ttlwidth = $this->fpdf->GetStringWidth($r['SPL_REFDOCNO']);
                        if ($ttlwidth > 100) {
                            $ukuranfont = 6.5;
                            while ($ttlwidth > 50) {
                                $this->fpdf->SetFont('Arial', '', $ukuranfont);
                                $ttlwidth = $this->fpdf->GetStringWidth($r['SPL_REFDOCNO']);
                                $ukuranfont = $ukuranfont - 0.5;
                            }
                        }
                        $this->fpdf->Cell(100, 4, $r['SPL_REFDOCNO'], 1, 0, 'L');
                        $cury += 8;
                        $isleft = false;
                    }
                    $nom++;
                }
                $cury += $isleft ? 9 : 1;

                if (count($PSNDocAsReff) > 0) {

                    $rsJob = DB::table('XPPSN1')->join('XMITM_V', 'PPSN1_MDLCD', '=', 'MITM_ITMCD')
                        ->whereIn('PPSN1_PSNNO', $PSNDocAsReff)
                        ->groupByRaw("PPSN1_WONO,PPSN1_MDLCD,MITM_ITMD1,PPSN1_SIMQT")
                        ->selectRaw("RTRIM(PPSN1_WONO) PPSN1_WONO,RTRIM(PPSN1_MDLCD) PPSN1_MDLCD,RTRIM(MITM_ITMD1) MITM_ITMD1,PPSN1_SIMQT")->get();
                    $rsJob = json_decode(json_encode($rsJob), true);

                    $cwos = [];
                    $cmodels = [];
                    $clotsize = [];

                    foreach ($rsJob as $r) {
                        if (count($cwos) == 0) {
                            $cwos[] = $r['PPSN1_WONO'];
                            $cmodels[] = $r['MITM_ITMD1'];
                            $clotsize[] = $r['PPSN1_SIMQT'];
                        } else {
                            $ttlwo = count($cwos);
                            $isexist = false;
                            for ($i = 0; $i < $ttlwo; $i++) {
                                if ($cwos[$i] == $r['PPSN1_WONO']) {
                                    $isexist = true;
                                    break;
                                }
                            }
                            if (!$isexist) {
                                $cwos[] = $r['PPSN1_WONO'];
                                $cmodels[] = $r['MITM_ITMD1'];
                                $clotsize[] = $r['PPSN1_SIMQT'];
                            }
                        }
                    }
                    $xWOcount = count($cwos);
                    if ($xWOcount > 0) {
                        for ($j = 0; $j < $xWOcount; $j++) {
                            if (($j % 2) == 0) {
                                $this->fpdf->SetXY(3, $cury);
                                $this->fpdf->SetFont('Arial', '', 7);
                                $this->fpdf->Cell(50, 4, 'Model', 1, 0, 'L');
                                $this->fpdf->Cell(40, 4, 'Job', 1, 0, 'L');
                                $this->fpdf->Cell(10, 4, 'Lot Size', 1, 0, 'C');
                                $this->fpdf->SetFont('Arial', 'B', 7);
                                $this->fpdf->SetXY(3, $cury + 4);
                                $ttlwidth = $this->fpdf->GetStringWidth($cmodels[$j]);
                                if ($ttlwidth > 50) {
                                    $ukuranfont = 6.5;
                                    while ($ttlwidth > 50) {
                                        $this->fpdf->SetFont('Arial', '', $ukuranfont);
                                        $ttlwidth = $this->fpdf->GetStringWidth($cmodels[$j]);
                                        $ukuranfont = $ukuranfont - 0.5;
                                    }
                                }
                                $this->fpdf->Cell(50, 4, $cmodels[$j], 1, 0, 'L');
                                $this->fpdf->SetFont('Arial', 'B', 7);
                                $this->fpdf->Cell(40, 4, $cwos[$j], 1, 0, 'L');
                                $this->fpdf->Cell(10, 4, number_format($clotsize[$j]), 1, 0, 'L');
                                $isleft = true;
                            } else {
                                $this->fpdf->SetXY(105, $cury);
                                $this->fpdf->SetFont('Arial', '', 7);
                                $this->fpdf->Cell(50, 4, 'Model', 1, 0, 'L');
                                $this->fpdf->Cell(40, 4, 'Job', 1, 0, 'L');
                                $this->fpdf->Cell(10, 4, 'Lot Size', 1, 0, 'C');
                                $this->fpdf->SetFont('Arial', 'B', 7);
                                $this->fpdf->SetXY(105, $cury + 4);
                                $ttlwidth = $this->fpdf->GetStringWidth($cmodels[$j]);
                                if ($ttlwidth > 50) {
                                    $ukuranfont = 6.5;
                                    while ($ttlwidth > 50) {
                                        $this->fpdf->SetFont('Arial', '', $ukuranfont);
                                        $ttlwidth = $this->fpdf->GetStringWidth($cmodels[$j]);
                                        $ukuranfont = $ukuranfont - 0.5;
                                    }
                                }
                                $this->fpdf->Cell(50, 4, $cmodels[$j], 1, 0, 'L');
                                $this->fpdf->SetFont('Arial', 'B', 7);
                                $this->fpdf->Cell(40, 4, $cwos[$j], 1, 0, 'L');
                                $this->fpdf->Cell(10, 4, number_format($clotsize[$j]), 1, 0, 'L');
                                $cury += 8;
                                $isleft = false;
                            }
                        }
                        $cury += $isleft ? 9 : 1;
                    }
                }

                $this->fpdf->SetXY(3, $cury);
                $this->fpdf->Cell(20, 4, 'No Rak', 1, 0, 'L');
                $this->fpdf->Cell(60, 4, 'Machine No', 1, 0, 'C');
                $this->fpdf->Cell(25, 4, 'Part No', 1, 0, 'L');
                $this->fpdf->Cell(40, 4, 'Part Name', 1, 0, 'L');
                $this->fpdf->Cell(15, 4, 'Req', 1, 0, 'R');
                $this->fpdf->Cell(43, 4, 'Remark', 1, 0, 'L');
                $wd2col = 3 + 20 + 60;
                $cury += 4;
                $td_h = 7;
                $i = 1;
                foreach ($rs as $r) {
                    if ($r['TTLREQ'] > 0) {
                        if (($cury + 10) > $hgt_p) {
                            $this->fpdf->AddPage();
                            $this->fpdf->SetFont('Arial', '', 6);
                            $clebar = $this->fpdf->GetStringWidth($cpsn) + 40;
                            $this->fpdf->Code128(3, 4, $cpsn, $clebar, 4);
                            $this->fpdf->Text(3, 11, $cpsn);
                            $clebar = $this->fpdf->GetStringWidth($ccat) + 17;
                            if ($ccat == '') {
                                $ccat = '??';
                            }
                            $this->fpdf->Code128(170, 4, $ccat, $clebar, 4);
                            $this->fpdf->Text(170, 11, $ccat);
                            $clebar = $this->fpdf->GetStringWidth($cline) + 17;
                            $this->fpdf->Code128(3, 13, $cline, $clebar, 4);
                            $this->fpdf->Text(3, 20, $cline);
                            $clebar = $this->fpdf->GetStringWidth($cfedr) + 17;
                            $this->fpdf->Code128(170, 13, $cfedr, $clebar, 4);
                            $this->fpdf->Text(170, 20, $cfedr);
                            $this->fpdf->SetFont('Arial', 'BU', 10);
                            $this->fpdf->SetXY(90, 4);
                            $this->fpdf->Cell(35, 4, 'Picking Instruction', 0, 0, 'C');
                            $this->fpdf->SetXY(100, 15);
                            $this->fpdf->SetFont('Arial', '', 6);
                            $this->fpdf->Cell(15, 4, 'Page ' . $this->fpdf->PageNo() . ' / {nb}', 1, 0, 'R');
                            $this->fpdf->SetFont('Arial', 'B', 7);
                            $cury = 22;
                            $isleft = true;
                            $nom = 0;
                            foreach ($rshead as $h) {
                                if (($nom % 2) == 0) {
                                    $this->fpdf->SetXY(3, $cury);
                                    $this->fpdf->SetFont('Arial', '', 7);
                                    $this->fpdf->Cell(100, 4, 'Reff Document', 1, 0, 'C');
                                    $this->fpdf->SetFont('Arial', 'B', 7);
                                    $this->fpdf->SetXY(3, $cury + 4);
                                    $ttlwidth = $this->fpdf->GetStringWidth($h['SPL_REFDOCNO']);
                                    if ($ttlwidth > 100) {
                                        $ukuranfont = 6.5;
                                        while ($ttlwidth > 50) {
                                            $this->fpdf->SetFont('Arial', '', $ukuranfont);
                                            $ttlwidth = $this->fpdf->GetStringWidth($h['SPL_REFDOCNO']);
                                            $ukuranfont = $ukuranfont - 0.5;
                                        }
                                    }
                                    $this->fpdf->Cell(100, 4, $h['SPL_REFDOCNO'], 1, 0, 'L');
                                    $isleft = true;
                                } else {
                                    $this->fpdf->SetXY(105, $cury);
                                    $this->fpdf->SetFont('Arial', '', 7);
                                    $this->fpdf->Cell(100, 4, 'Model', 1, 0, 'C');
                                    $this->fpdf->SetFont('Arial', 'B', 7);
                                    $this->fpdf->SetXY(105, $cury + 4);
                                    $ttlwidth = $this->fpdf->GetStringWidth($h['SPL_REFDOCNO']);
                                    if ($ttlwidth > 100) {
                                        $ukuranfont = 6.5;
                                        while ($ttlwidth > 50) {
                                            $this->fpdf->SetFont('Arial', '', $ukuranfont);
                                            $ttlwidth = $this->fpdf->GetStringWidth($h['SPL_REFDOCNO']);
                                            $ukuranfont = $ukuranfont - 0.5;
                                        }
                                    }
                                    $this->fpdf->Cell(100, 4, $h['SPL_REFDOCNO'], 1, 0, 'L');
                                    $cury += 8;
                                    $isleft = false;
                                }
                                $nom++;
                            }
                            $cury += $isleft ? 9 : 1;
                            $cwos = [];
                            if (count($PSNDocAsReff) > 0) {
                                $rsJob = DB::table('XPPSN1')->join('XMITM_V', 'PPSN1_MDLCD', '=', 'MITM_ITMCD')
                                    ->whereIn('PPSN1_PSNNO', $PSNDocAsReff)
                                    ->groupByRaw("PPSN1_WONO,PPSN1_MDLCD,MITM_ITMD1,PPSN1_SIMQT")
                                    ->selectRaw("PPSN1_WONO,PPSN1_MDLCD,MITM_ITMD1,PPSN1_SIMQT");
                                $rsJob = json_decode(json_encode($rsJob), true);


                                $cmodels = [];
                                $clotsize = [];
                                foreach ($rsJob as $rj) {
                                    if (count($cwos) == 0) {
                                        $cwos[] = trim($rj['PPSN1_WONO']);
                                        $cmodels[] = trim($rj['MITM_ITMD1']);
                                        $clotsize[] = trim($rj['PPSN1_SIMQT']);
                                    } else {
                                        $ttlwo = count($cwos);
                                        $isexist = false;
                                        for ($i = 0; $i < $ttlwo; $i++) {
                                            if ($cwos[$i] == trim($rj['PPSN1_WONO'])) {
                                                $isexist = true;
                                                break;
                                            }
                                        }
                                        if (!$isexist) {
                                            $cwos[] = trim($rj['PPSN1_WONO']);
                                            $cmodels[] = trim($rj['MITM_ITMD1']);
                                            $clotsize[] = trim($rj['PPSN1_SIMQT']);
                                        }
                                    }
                                }
                            }
                            $xWOcount = count($cwos);
                            for ($j = 0; $j < $xWOcount; $j++) {
                                if (($j % 2) == 0) {
                                    $this->fpdf->SetXY(3, $cury);
                                    $this->fpdf->SetFont('Arial', '', 7);
                                    $this->fpdf->Cell(50, 4, 'Model', 1, 0, 'L');
                                    $this->fpdf->Cell(40, 4, 'Job', 1, 0, 'L');
                                    $this->fpdf->Cell(10, 4, 'Lot Size', 1, 0, 'C');
                                    $this->fpdf->SetFont('Arial', 'B', 7);
                                    $this->fpdf->SetXY(3, $cury + 4);
                                    $ttlwidth = $this->fpdf->GetStringWidth($cmodels[$j]);
                                    if ($ttlwidth > 50) {
                                        $ukuranfont = 6.5;
                                        while ($ttlwidth > 50) {
                                            $this->fpdf->SetFont('Arial', '', $ukuranfont);
                                            $ttlwidth = $this->fpdf->GetStringWidth($cmodels[$j]);
                                            $ukuranfont = $ukuranfont - 0.5;
                                        }
                                    }
                                    $this->fpdf->Cell(50, 4, $cmodels[$j], 1, 0, 'L');
                                    $this->fpdf->SetFont('Arial', 'B', 7);
                                    $this->fpdf->Cell(40, 4, $cwos[$j], 1, 0, 'L');
                                    $this->fpdf->Cell(10, 4, number_format($clotsize[$j]), 1, 0, 'L');
                                    $isleft = true;
                                } else {
                                    $this->fpdf->SetXY(105, $cury);
                                    $this->fpdf->SetFont('Arial', '', 7);
                                    $this->fpdf->Cell(50, 4, 'Model', 1, 0, 'L');
                                    $this->fpdf->Cell(40, 4, 'Job', 1, 0, 'L');
                                    $this->fpdf->Cell(10, 4, 'Lot Size', 1, 0, 'C');
                                    $this->fpdf->SetFont('Arial', 'B', 7);
                                    $this->fpdf->SetXY(105, $cury + 4);
                                    $ttlwidth = $this->fpdf->GetStringWidth($cmodels[$j]);
                                    if ($ttlwidth > 50) {
                                        $ukuranfont = 6.5;
                                        while ($ttlwidth > 50) {
                                            $this->fpdf->SetFont('Arial', '', $ukuranfont);
                                            $ttlwidth = $this->fpdf->GetStringWidth($cmodels[$j]);
                                            $ukuranfont = $ukuranfont - 0.5;
                                        }
                                    }
                                    $this->fpdf->Cell(50, 4, $cmodels[$j], 1, 0, 'L');
                                    $this->fpdf->SetFont('Arial', 'B', 7);
                                    $this->fpdf->Cell(40, 4, $cwos[$j], 1, 0, 'L');
                                    $this->fpdf->Cell(10, 4, number_format($clotsize[$j]), 1, 0, 'L');
                                    $cury += 8;
                                    $isleft = false;
                                }
                            }
                            $cury += $isleft ? 9 : 1;
                            $this->fpdf->SetXY(3, $cury);
                            $this->fpdf->Cell(20, 4, 'No Rak', 1, 0, 'L');
                            $this->fpdf->Cell(60, 4, 'Machine No', 1, 0, 'C');
                            $this->fpdf->Cell(25, 4, 'Part No', 1, 0, 'L');
                            $this->fpdf->Cell(40, 4, 'Part Name', 1, 0, 'L');
                            $this->fpdf->Cell(15, 4, 'Req', 1, 0, 'R');
                            $this->fpdf->Cell(43, 4, 'Remark', 1, 0, 'L');

                            $wd2col = 3 + 20 + 60;
                            $cury += 4;
                        }
                        $this->fpdf->SetFont('Arial', '', 8);
                        $this->fpdf->SetXY(3, $cury);

                        if (strpos($r['SPL_RACKNO'], '-') !== false) {
                            $this->fpdf->Cell(20, $td_h, '', 1, 0, 'L');
                            $achar = explode('-', $r['SPL_RACKNO']);
                            $this->fpdf->Text(3.5, $cury + 3, $achar[0]);
                            $this->fpdf->Text(3.5, $cury + 6, $achar[1]);
                        } else {
                            $this->fpdf->Cell(20, $td_h, $r['SPL_RACKNO'], 1, 0, 'L');
                        }
                        $_contentToEncode = $r['SPL_MC'] . '|' . $r['SPL_PROCD'] . '|' . trim($r['SPL_ORDERNO']);
                        $lebar = $this->fpdf->GetStringWidth($_contentToEncode) + 17;
                        $clebar = $this->fpdf->GetStringWidth($_contentToEncode) + 16;


                        $strx = $wd2col - ($lebar + 3);
                        if (($i % 2) > 0) {
                            $this->fpdf->Code128($wd2col - 60 + 2, $cury + 1.5, $_contentToEncode, $clebar, 3);
                            $this->fpdf->Cell(60, $td_h, $r['SPL_ORDERNO'], 1, 0, 'R');
                            $this->fpdf->SetFont('Arial', '', 4);
                            $this->fpdf->Text($wd2col - 5, $cury + 1.5, $r['SPL_PROCD']);
                            $this->fpdf->SetFont('Arial', '', 8);
                        } else {
                            $this->fpdf->Code128($strx, $cury + 1.5, $_contentToEncode, $clebar, 3);
                            $this->fpdf->Cell(60, $td_h, $r['SPL_ORDERNO'], 1, 0, 'L');
                            $this->fpdf->SetFont('Arial', '', 4);
                            $this->fpdf->Text($wd2col - 79, $cury + 1.5, $r['SPL_PROCD']);
                            $this->fpdf->SetFont('Arial', '', 8);
                        }

                        $this->fpdf->Cell(25, $td_h, trim($r['SPL_ITMCD']), 1, 0, 'L');
                        $ttlwidth = $this->fpdf->GetStringWidth(trim($r['MITM_SPTNO']));
                        if ($ttlwidth > 40) {
                            $ukuranfont = 7.5;
                            while ($ttlwidth > 39) {
                                $this->fpdf->SetFont('Arial', '', $ukuranfont);
                                $ttlwidth = $this->fpdf->GetStringWidth(trim($r['MITM_SPTNO']));
                                $ukuranfont = $ukuranfont - 0.5;
                            }
                        }
                        $this->fpdf->Cell(40, $td_h, trim($r['MITM_SPTNO']), 1, 0, 'L');
                        $this->fpdf->SetFont('Arial', '', 8);
                        $this->fpdf->Cell(15, $td_h, number_format($r['TTLREQ']), 1, 0, 'R');
                        $ttlwidth = $this->fpdf->GetStringWidth(trim($r['SPL_ITMRMRK']));
                        if ($ttlwidth > 43) {
                            $ukuranfont = 7.5;
                            while ($ttlwidth > 43) {
                                $this->fpdf->SetFont('Arial', '', $ukuranfont);
                                $ttlwidth = $this->fpdf->GetStringWidth(trim($r['SPL_ITMRMRK']));
                                $ukuranfont = $ukuranfont - 0.5;
                            }
                        }
                        $this->fpdf->Cell(43, $td_h, $r['SPL_ITMRMRK'], 1, 0, 'L');
                        $this->fpdf->SetFont('Arial', '', 8);
                        $cury += $td_h;
                        $i++;
                    }
                }
            }
        }

        $files = Storage::disk('public')->files('');
        $currentPSNResources = [];
        foreach ($files as $f) {
            if (str_contains($f, $request->psn)) {
                $currentPSNResources[] = $f;
            }
        }

        Storage::disk('public')->delete($currentPSNResources);
        $this->fpdf->Output('picking instruction' . '.pdf', 'I');
        exit;
    }

    function generateQR($data = [])
    {
        $op = new Process(["Python", base_path("smt.py"), $data['content'], "1"],);
        $op->run();
        if (!$op->isSuccessful()) {
            throw new \RuntimeException($op->getErrorOutput());
        }
        $image_name = str_replace("/", "xxx", $data['content']);
        $image_name = str_replace(" ", "___", $image_name);
        $image_name = str_replace("|", "lll", $image_name);
        $image_name = str_replace("\t", "ttt", $image_name);
        return storage_path('app/public/' . $image_name . '.png');
    }


    function reportPSNJOBPeriod()
    {
        $data = DB::table('V_SPLSCN_TBLC')->where('SPLSCN_DATE', '>=', '2024-05-01')
            ->where('SPLSCN_DATE', '<=', '2024-08-09')
            ->where('SPLSCN_DOC', 'like', '%TDI%')
            ->whereNotIn('SPLSCN_CAT', ['HW', 'PCB'])
            ->groupBy('SPLSCN_DATE', 'SPLSCN_DOC')
            ->get(['SPLSCN_DATE', DB::raw('RTRIM(SPLSCN_DOC) PSN'), DB::raw("COUNT(*) TTLREEL"), DB::raw("'' as wo")]);

        $data2 = DB::query()->fromRaw("(SELECT rtrim(Z.PPSN1_WONO) wo, rtrim(Z.PPSN1_PSNNO) psn FROM XPPSN1 Z WHERE Z.PPSN1_PSNNO IN (
                SELECT A.SPLSCN_DOC FROM V_SPLSCN_TBLC A WHERE A.SPLSCN_DOC LIKE '%TDI%'
                AND A.SPLSCN_DATE BETWEEN '2024-05-01' AND '2024-08-09'
                AND A.SPLSCN_CAT NOT IN ('HW','PCB')
                GROUP BY A.SPLSCN_DOC
            )
            GROUP BY Z.PPSN1_WONO,Z.PPSN1_PSNNO) as b")->get();

        foreach ($data as &$r) {
            foreach ($data2 as $b) {
                if ($r->PSN == $b->psn) {
                    $r->wo .= $b->wo . '|';
                }
            }
        }
        unset($r);
        return ['data' => $data, 'data2' => $data2];
    }

    function getDocumentByDelivery(Request $request)
    {
        $DLVDocs = DB::table('DLV_TBL')
            ->leftJoin('SERD2_TBL', 'DLV_SER', '=', 'SERD2_SER')
            ->leftJoin('SER_TBL', 'DLV_SER', '=', 'SER_ID')
            ->where('DLV_ID', $request->doc)
            ->where('SERD2_ITMCD', $request->part_code)
            ->groupBy('SERD2_PSNNO', 'SERD2_JOB', 'SERD2_PROCD', 'SERD2_MC', 'SERD2_MCZ', 'SERD2_SER', 'SER_ITMID')
            ->get(['SERD2_PSNNO', 'SERD2_JOB', 'SERD2_PROCD', 'SERD2_MC', 'SERD2_MCZ', 'SERD2_SER', 'SER_ITMID']);

        $suspectedMC = '';
        $suspectedMCZ = '';
        $suspectedModelCode = '';
        $PSNs = [];
        $Parts = [];
        $CustomsParts = [];

        foreach ($DLVDocs as $r) {
            $suspectedMCZ = $r->SERD2_MCZ;
            $suspectedMC = $r->SERD2_MC;
            $suspectedModelCode = $r->SER_ITMID;
            if (!in_array($r->SERD2_PSNNO, $PSNs)) {
                $PSNs[] = $r->SERD2_PSNNO;
            }
        }

        $data = [];
        $ENGBOM = NULL;

        $sourceFlag = 'PSN';
        if ($PSNs) {
            $data = DB::table('SPL_TBL')
                ->leftJoin('MITM_TBL', 'SPL_ITMCD', '=', 'MITM_ITMCD')
                ->whereIn('SPL_DOC', $PSNs)
                ->whereIn('SPL_ORDERNO', [$suspectedMCZ])
                ->whereIn('SPL_MC', [$suspectedMC])
                ->orderBy('SPL_DOC')
                ->orderBy('SPL_ORDERNO')
                ->orderBy('SPL_MC')
                ->orderBy('SPL_PROCD')
                ->get(
                    [
                        'SPL_DOC',
                        'SPL_DOCNO',
                        'SPL_LINE',
                        'SPL_PROCD',
                        'SPL_FEDR',
                        'SPL_CAT',
                        'SPL_MC',
                        'SPL_ORDERNO',
                        'SPL_MS',
                        'SPL_ITMCD',
                        DB::raw('RTRIM(MITM_SPTNO) SPTNO'),
                        DB::raw('RTRIM(MITM_ITMD1) ITMD1'),
                        'SPL_QTYREQ'
                    ]
                );
            foreach ($data as $r) {
                if (!in_array($r->SPL_ITMCD, $Parts)) {
                    if ($r->SPL_ITMCD != $request->part_code) {
                        $Parts[] = $r->SPL_ITMCD;
                    }
                }
            }

            if (count($Parts) >= 1) {
                $CustomsParts = DB::table('ZRPSAL_BCSTOCK')
                    ->leftJoin('MITM_TBL', 'RPSTOCK_ITMNUM', '=', 'MITM_ITMCD')
                    ->whereIn('RPSTOCK_ITMNUM', $Parts)
                    ->where('IODATE', '<=', date('Y-m-d'))
                    ->groupBy('RPSTOCK_ITMNUM', 'MITM_SPTNO', 'MITM_ITMD1')
                    ->get([
                        DB::raw('RTRIM(RPSTOCK_ITMNUM) RPSTOCK_ITMNUM'),
                        DB::raw("RTRIM(MITM_SPTNO) SPTNO"),
                        DB::raw("RTRIM(MITM_ITMD1) ITMD1"),
                        DB::raw("SUM(RPSTOCK_QTY) RMQT"),
                        DB::raw("0 STOCKQT"),
                    ]);

                $StockParts = DB::table('ITH_TBL')->whereIn('ITH_WH', ['ARWH1', 'ARWH2'])
                    ->where('ITH_DATE', '<=', date('Y-m-d'))
                    ->groupBy('ITH_ITMCD')
                    ->get(['ITH_ITMCD', DB::raw("SUM(ITH_QTY) STOCKQT")]);
                foreach ($CustomsParts as &$r) {
                    foreach ($StockParts as $s) {
                        if ($r->RPSTOCK_ITMNUM === $s->ITH_ITMCD) {
                            $r->STOCKQT = $s->STOCKQT;
                            break;
                        }
                    }
                }
                unset($r);
            } else {
                $sourceFlag = 'PA100';
                // cari di PA100
                $ENGBOM = DB::table('ENG_BOMSTX')
                    ->select('MAIN_PART_CODE', 'EPSON_ORG_PART', 'SUB', 'SUB1')
                    ->where('MODEL_CODE', $suspectedModelCode)
                    ->where('MAIN_PART_CODE', $request->part_code)
                    ->get();
                if ($ENGBOM->count() === 0) {

                    $ENGBOM = DB::table('ENG_BOMSTX')
                        ->select('MAIN_PART_CODE', 'EPSON_ORG_PART', 'SUB', 'SUB1')
                        ->where('MODEL_CODE', $suspectedModelCode)
                        ->where('EPSON_ORG_PART', $request->part_code)
                        ->get();
                    if ($ENGBOM->count() === 0) {
                        $ENGBOM = DB::table('ENG_BOMSTX')
                            ->select('MAIN_PART_CODE', 'EPSON_ORG_PART', 'SUB', 'SUB1')
                            ->where('MODEL_CODE', $suspectedModelCode)
                            ->where('SUB', $request->part_code)
                            ->get();
                        if ($ENGBOM->count() === 0) {
                            $ENGBOM = DB::table('ENG_BOMSTX')
                                ->select('MAIN_PART_CODE', 'EPSON_ORG_PART', 'SUB', 'SUB1')
                                ->where('MODEL_CODE', $suspectedModelCode)
                                ->where('SUB1', $request->part_code)
                                ->get();
                            if ($ENGBOM->count() === 0) {
                            } else {
                                $sourceFlag = 'PA100 #3';
                                foreach ($ENGBOM as $r) {
                                    if ($r->MAIN_PART_CODE != $request->part_code) {
                                        if (!in_array($r->MAIN_PART_CODE, $Parts)) {
                                            $Parts[] = $r->MAIN_PART_CODE;
                                        }
                                    }
                                    if ($r->EPSON_ORG_PART != $request->part_code) {
                                        if (!in_array($r->EPSON_ORG_PART, $Parts)) {
                                            $Parts[] = $r->EPSON_ORG_PART;
                                        }
                                    }
                                    if ($r->SUB != $request->part_code) {
                                        if (!in_array($r->SUB, $Parts)) {
                                            $Parts[] = $r->SUB;
                                        }
                                    }
                                }
                            }
                        } else {
                            $sourceFlag = 'PA100 #2';
                            foreach ($ENGBOM as $r) {
                                if ($r->MAIN_PART_CODE != $request->part_code) {
                                    if (!in_array($r->MAIN_PART_CODE, $Parts)) {
                                        $Parts[] = $r->MAIN_PART_CODE;
                                    }
                                }
                                if ($r->EPSON_ORG_PART != $request->part_code) {
                                    if (!in_array($r->EPSON_ORG_PART, $Parts)) {
                                        $Parts[] = $r->EPSON_ORG_PART;
                                    }
                                }
                                if ($r->SUB1 != $request->part_code) {
                                    if (!in_array($r->SUB1, $Parts)) {
                                        $Parts[] = $r->SUB1;
                                    }
                                }
                            }
                        }
                    } else {
                        $sourceFlag = 'PA100 #1';
                        foreach ($ENGBOM as $r) {
                            if ($r->MAIN_PART_CODE != $request->part_code) {
                                if (!in_array($r->MAIN_PART_CODE, $Parts)) {
                                    $Parts[] = $r->MAIN_PART_CODE;
                                }
                            }
                            if ($r->SUB != $request->part_code) {
                                if (!in_array($r->SUB, $Parts)) {
                                    $Parts[] = $r->SUB;
                                }
                            }
                            if ($r->SUB1 != $request->part_code) {
                                if (!in_array($r->SUB1, $Parts)) {
                                    $Parts[] = $r->SUB1;
                                }
                            }
                        }
                    }
                } else {
                    $sourceFlag = 'PA100 #0';
                    foreach ($ENGBOM as $r) {
                        if ($r->EPSON_ORG_PART != $request->part_code) {
                            if (!in_array($r->EPSON_ORG_PART, $Parts)) {
                                $Parts[] = $r->EPSON_ORG_PART;
                            }
                        }
                        if ($r->SUB != $request->part_code) {
                            if (!in_array($r->SUB, $Parts)) {
                                $Parts[] = $r->SUB;
                            }
                        }
                        if ($r->SUB1 != $request->part_code) {
                            if (!in_array($r->SUB1, $Parts)) {
                                $Parts[] = $r->SUB1;
                            }
                        }
                    }
                }
                if (count($Parts) >= 1) {
                    $sourceFlag = 'PA100 ##';
                    $CustomsParts = DB::table('ZRPSAL_BCSTOCK')
                        ->leftJoin('MITM_TBL', 'RPSTOCK_ITMNUM', '=', 'MITM_ITMCD')
                        ->whereIn('RPSTOCK_ITMNUM', $Parts)
                        ->where('IODATE', '<=', date('Y-m-d'))
                        ->groupBy('RPSTOCK_ITMNUM', 'MITM_SPTNO', 'MITM_ITMD1')
                        ->get([
                            DB::raw('RTRIM(RPSTOCK_ITMNUM) RPSTOCK_ITMNUM'),
                            DB::raw("RTRIM(MITM_SPTNO) SPTNO"),
                            DB::raw("RTRIM(MITM_ITMD1) ITMD1"),
                            DB::raw("SUM(RPSTOCK_QTY) RMQT"),
                            DB::raw("0 STOCKQT"),
                        ]);

                    $StockParts = DB::table('ITH_TBL')->whereIn('ITH_WH', ['ARWH1', 'ARWH2'])
                        ->where('ITH_DATE', '<=', date('Y-m-d'))
                        ->groupBy('ITH_ITMCD')
                        ->get(['ITH_ITMCD', DB::raw("SUM(ITH_QTY) STOCKQT")]);

                    foreach ($CustomsParts as &$r) {
                        foreach ($StockParts as $s) {
                            if ($r->RPSTOCK_ITMNUM === $s->ITH_ITMCD) {
                                $r->STOCKQT = $s->STOCKQT;
                                $r->BALANCE = $r->RMQT - $s->STOCKQT;
                                break;
                            }
                        }
                    }
                    unset($r);
                }

                $_commonPart = $CustomsParts->filter(function ($item) {
                    return $item->RMQT > $item->STOCKQT;
                });

                if ($_commonPart->count() === 0) {
                    // cari di common part
                    $CommonParts = DB::table('ENG_COMMPRT_LST')
                        ->where('ITMCDPRI', $request->part_code)
                        ->get(['ITMCDALT']);
                    if ($CommonParts->count() === 0) {
                        $CommonParts = DB::table('ENG_COMMPRT_LST')
                            ->where('ITMCDALT', $request->part_code)
                            ->get(['ITMCDPRI']);
                        if ($CommonParts->count() === 0) {
                        } else {
                            $sourceFlag = 'COMMONPART #1';
                            foreach ($CommonParts as $r) {
                                if ($r->ITMCDPRI != $request->part_code) {
                                    if (!in_array($r->ITMCDPRI, $Parts)) {
                                        $Parts[] = $r->ITMCDPRI;
                                    }
                                }
                            }
                        }
                    } else {
                        $sourceFlag = 'COMMONPART #0';
                        foreach ($CommonParts as $r) {
                            if ($r->ITMCDALT != $request->part_code) {
                                if (!in_array($r->ITMCDALT, $Parts)) {
                                    $Parts[] = $r->ITMCDALT;
                                }
                            }
                        }
                    }

                    if (count($Parts) >= 1) {
                        $sourceFlag = 'COMMONPART ##';
                        $CustomsParts = DB::table('ZRPSAL_BCSTOCK')
                            ->leftJoin('MITM_TBL', 'RPSTOCK_ITMNUM', '=', 'MITM_ITMCD')
                            ->whereIn('RPSTOCK_ITMNUM', $Parts)
                            ->where('IODATE', '<=', date('Y-m-d'))
                            ->groupBy('RPSTOCK_ITMNUM', 'MITM_SPTNO', 'MITM_ITMD1')
                            ->get([
                                DB::raw('RTRIM(RPSTOCK_ITMNUM) RPSTOCK_ITMNUM'),
                                DB::raw("RTRIM(MITM_SPTNO) SPTNO"),
                                DB::raw("RTRIM(MITM_ITMD1) ITMD1"),
                                DB::raw("SUM(RPSTOCK_QTY) RMQT"),
                                DB::raw("0 STOCKQT"),
                            ]);

                        $StockParts = DB::table('ITH_TBL')->whereIn('ITH_WH', ['ARWH1', 'ARWH2'])
                            ->where('ITH_DATE', '<=', date('Y-m-d'))
                            ->groupBy('ITH_ITMCD')
                            ->get(['ITH_ITMCD', DB::raw("SUM(ITH_QTY) STOCKQT")]);

                        foreach ($CustomsParts as &$r) {
                            foreach ($StockParts as $s) {
                                if ($r->RPSTOCK_ITMNUM === $s->ITH_ITMCD) {
                                    $r->STOCKQT = $s->STOCKQT;
                                    $r->BALANCE = $r->RMQT - $s->STOCKQT;
                                    break;
                                }
                            }
                        }
                        unset($r);
                    }
                }
            }
        }

        return [
            'data' => $data,
            'CustomsParts' => $CustomsParts->filter(function ($item) {
                return $item->RMQT > $item->STOCKQT;
            }),
            '$sourceFlag' => $sourceFlag,

        ];
    }

    function validateUniquekeyVsDoc(Request $request)
    {
        $data = DB::table('SPLSCN_TBL')
            ->where('SPLSCN_UNQCODE', $request->uniquekey)
            ->where('SPLSCN_UNQCODE', '!=', '')
            ->get(['SPLSCN_DOC', 'SPLSCN_CAT', 'SPLSCN_LINE']);

        if ($data->count() > 0) {
            return [
                'message' => 'OK',
                'data' => $data
            ];
        } else {
            return response()->json(['message' => 'tidak ditemukan'], 406);
        }
    }

    function joinReels(Request $request)
    {
        $dataTobeSaved = [];
        $data = $request->json()->all();
        $dataTobeSaved['REELC_DOC'] = $data['REELC_DOC'];
        $dataTobeSaved['REELC_ITMCD'] = $data['REELC_ITMCD'];
        $dataTobeSaved['REELC_FOR_PROCESS'] = $data['REELC_FOR_PROCESS'];
        $dataTobeSaved['REELC_FOR_MC'] = $data['REELC_FOR_MC'];
        $dataTobeSaved['REELC_FOR_MCZ'] = $data['REELC_FOR_MCZ'];
        $dataTobeSaved['created_at'] = date('Y-m-d H:i:s');
        $dataTobeSaved['created_by'] = $data['user_id'];
        $ke = 1;

        foreach ($data['detail'] as $r) {
            $dataTobeSaved['REELC_UNIQUE' . $ke] = $r['id'];
            $dataTobeSaved['REELC_LOT' . $ke] = $r['lotNumber'];
            $dataTobeSaved['REELC_QTY' . $ke] = $r['qty'];
            $ke++;
        }

        try {
            DB::beginTransaction();
            DB::table('REELC_TBL')->insert($dataTobeSaved);
            DB::commit();
            return ['message' => 'Saved successfully'];
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    function reportJoinReels(Request $request)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('B5', 'No');
        $sheet->setCellValue('C5', 'Tanggal');
        $sheet->setCellValue('D5', 'Model');
        $sheet->setCellValue('E5', 'Kode List');
        $sheet->setCellValue('F5', 'Assy No');
        $sheet->setCellValue('G5', 'Job No');
        $sheet->setCellValue('H5', 'Lot Size');
        $sheet->setCellValue('I5', 'Part Code');
        $sheet->setCellValue('J5', 'Part Code.');

        $sheet->setCellValue('K5', 'Part Name');
        $sheet->setCellValue('L5', 'Qty Reel');
        $sheet->setCellValue('L6', '1');
        $sheet->setCellValue('M6', 'QTY');
        $sheet->setCellValue('N6', 'LOT NO');
        $sheet->setCellValue('O6', 'ID');

        $sheet->setCellValue('P6', '2');
        $sheet->setCellValue('Q6', 'QTY');
        $sheet->setCellValue('R6', 'LOT NO');
        $sheet->setCellValue('S6', 'ID');

        $sheet->setCellValue('T6', '3');
        $sheet->setCellValue('U6', 'QTY');
        $sheet->setCellValue('V6', 'LOT NO');
        $sheet->setCellValue('W6', 'ID');

        $sheet->setCellValue('X6', '4');
        $sheet->setCellValue('Y6', 'QTY');
        $sheet->setCellValue('Z6', 'LOT NO');
        $sheet->setCellValue('AA6', 'ID');

        $sheet->setCellValue('AB6', '5');
        $sheet->setCellValue('AC6', 'QTY');
        $sheet->setCellValue('AD6', 'LOT NO');
        $sheet->setCellValue('AE6', 'ID');

        $sheet->setCellValue('AF5', 'Total Qty');
        $sheet->setCellValue('AG5', 'Jumlah Joint');

        $sheet->mergeCells('B5:B6', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('C5:C6', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('D5:D6', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('E5:E6', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('F5:F6', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('G5:G6', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('H5:H6', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('I5:I6', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('J5:J6', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('K5:K6', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('L5:AE5', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('AF5:AF6', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('AG5:AG6', $sheet::MERGE_CELL_CONTENT_HIDE);

        $sheet->getStyle('B5:AG6')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('B5:AG6')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $data = DB::table('REELC_TBL')
            ->leftJoin('MITM_TBL', 'REELC_ITMCD', '=', 'MITM_ITMCD')
            ->whereDate('created_at', '>=', $request->dateFrom)
            ->whereDate('created_at', '<=', $request->dateTo)
            ->orderBy('created_at')
            ->orderBy('REELC_DOC')
            ->get([
                DB::raw('CONVERT(DATE, created_at) v_date'),
                'REELC_DOC',
                'REELC_ITMCD',
                DB::raw('RTRIM(MITM_SPTNO) SPTNO'),
                'REELC_QTY1',
                'REELC_QTY2',
                'REELC_QTY3',
                'REELC_QTY4',
                'REELC_QTY5',
                'REELC_LOT1',
                'REELC_LOT2',
                'REELC_LOT3',
                'REELC_LOT4',
                'REELC_LOT5',
                'REELC_UNIQUE1',
                'REELC_UNIQUE2',
                'REELC_UNIQUE3',
                'REELC_UNIQUE4',
                'REELC_UNIQUE5',
            ]);

        $distinctDoc = $data->unique('REELC_DOC')->values()->pluck('REELC_DOC');
        $dataPSN = DB::table('XPPSN1')->leftJoin('XMITM_V', 'PPSN1_MDLCD', '=', 'MITM_ITMCD')
            ->whereIn('PPSN1_PSNNO', $distinctDoc)
            ->distinct()
            ->get([
                DB::raw('RTRIM(PPSN1_PSNNO) PSNNO'),
                DB::raw('RTRIM(PPSN1_WONO) WONO'),
                DB::raw('RTRIM(MITM_ITMD1) ITMD1'),
                DB::raw('RTRIM(MITM_ITMCD) ITMCD'),
                'PPSN1_SIMQT',
            ]);

        foreach ($data as &$r) {
            foreach ($dataPSN as $p) {
                if ($r->REELC_DOC == $p->PSNNO) {
                    $r->v_model = $p->ITMD1;
                    $r->v_assy_code = $p->ITMCD;
                    $r->v_job = $p->WONO;
                    $r->v_lot_size = $p->PPSN1_SIMQT;
                    break;
                }
            }
        }
        unset($r);

        $tempDateDoc = '';

        $rowAt = 7;
        $nomorUrut = 1;
        foreach ($data as $r) {

            if ($tempDateDoc != $r->v_date . $r->REELC_DOC) {
                $tempDateDoc = $r->v_date . $r->REELC_DOC;
                $diplayedDate = $r->v_date;
                $diplayedModel = $r->v_model;
                $diplayedDoc = $r->REELC_DOC;
                $diplayedAssyCode = $r->v_assy_code;
                $diplayedJob = $r->v_job;
                $diplayedLotSize = $r->v_lot_size;
            } else {
                $diplayedDate = '';
                $diplayedModel = '';
                $diplayedDoc = '';
                $diplayedAssyCode = '';
                $diplayedJob = '';
                $diplayedLotSize = '';
            }

            $_total_qty = $r->REELC_QTY1;
            $_total_joint = 0;
            if ($r->REELC_QTY2) {
                $_total_qty += $r->REELC_QTY2;
                $_total_joint++;
            }
            if ($r->REELC_QTY3) {
                $_total_qty += $r->REELC_QTY3;
                $_total_joint++;
            }
            if ($r->REELC_QTY4) {
                $_total_qty += $r->REELC_QTY4;
                $_total_joint++;
            }
            if ($r->REELC_QTY5) {
                $_total_qty += $r->REELC_QTY5;
                $_total_joint++;
            }
            $sheet->setCellValue('B' . $rowAt, $nomorUrut);
            $sheet->setCellValue('C' . $rowAt, $diplayedDate);
            $sheet->setCellValue('D' . $rowAt, $diplayedModel);
            $sheet->setCellValue('E' . $rowAt, $diplayedDoc);
            $sheet->setCellValue('F' . $rowAt, $diplayedAssyCode);
            $sheet->setCellValue('G' . $rowAt, $diplayedJob);
            $sheet->setCellValue('H' . $rowAt, $diplayedLotSize);
            $sheet->setCellValue('I' . $rowAt, '3N1' . $r->REELC_ITMCD);
            $sheet->setCellValue('J' . $rowAt,  $r->REELC_ITMCD);
            $sheet->setCellValue('K' . $rowAt,  $r->SPTNO);
            $sheet->setCellValue('L' . $rowAt,  '3N2');
            $sheet->setCellValue('M' . $rowAt,  $r->REELC_QTY1);
            $sheet->setCellValue('N' . $rowAt,  $r->REELC_LOT1);
            $sheet->setCellValue('O' . $rowAt,  $r->REELC_UNIQUE1);

            $sheet->setCellValue('P' . $rowAt,  $r->REELC_QTY2 ? '3N2' : '');
            $sheet->setCellValue('Q' . $rowAt,  $r->REELC_QTY2);
            $sheet->setCellValue('R' . $rowAt,  $r->REELC_LOT2);
            $sheet->setCellValue('S' . $rowAt,  $r->REELC_UNIQUE2);

            $sheet->setCellValue('T' . $rowAt,  $r->REELC_QTY3 ? '3N2' : '');
            $sheet->setCellValue('U' . $rowAt,  $r->REELC_QTY3);
            $sheet->setCellValue('V' . $rowAt,  $r->REELC_LOT3);
            $sheet->setCellValue('W' . $rowAt,  $r->REELC_UNIQUE3);

            $sheet->setCellValue('X' . $rowAt,  $r->REELC_QTY4 ? '3N2' : '');
            $sheet->setCellValue('Y' . $rowAt,  $r->REELC_QTY4);
            $sheet->setCellValue('Z' . $rowAt,  $r->REELC_LOT4);
            $sheet->setCellValue('AA' . $rowAt,  $r->REELC_UNIQUE4);

            $sheet->setCellValue('AB' . $rowAt,  $r->REELC_QTY5 ? '3N2' : '');
            $sheet->setCellValue('AC' . $rowAt,  $r->REELC_QTY5);
            $sheet->setCellValue('AD' . $rowAt,  $r->REELC_LOT5);
            $sheet->setCellValue('AE' . $rowAt,  $r->REELC_UNIQUE5);


            $sheet->setCellValue('AF' . $rowAt,  $_total_qty);
            $sheet->setCellValue('AG' . $rowAt,  $_total_joint);
            $rowAt++;
            $nomorUrut++;
        }

        $sheet->getStyle('B5:AG' . $rowAt - 1)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new Color('1F1812'));
        $sheet->getStyle('B5:AG' . 6)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('d9d9d9');
        $sheet->getStyle('E7:E' . $rowAt - 1)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('b7dee8');
        $sheet->getStyle('I7:I' . $rowAt - 1)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('b7dee8');
        $sheet->getStyle('L7:L' . $rowAt - 1)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('b7dee8');
        $sheet->getStyle('P7:P' . $rowAt - 1)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('b7dee8');
        $sheet->getStyle('T7:T' . $rowAt - 1)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('b7dee8');
        $sheet->getStyle('X7:X' . $rowAt - 1)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('b7dee8');
        $sheet->getStyle('AB7:AB' . $rowAt - 1)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('b7dee8');

        $sheet->getStyle('O7:O' . $rowAt - 1)->getNumberFormat()->setFormatCode('@');
        $sheet->getStyle('S7:S' . $rowAt - 1)->getNumberFormat()->setFormatCode('@');
        $sheet->getStyle('W7:W' . $rowAt - 1)->getNumberFormat()->setFormatCode('@');
        $sheet->getStyle('AA7:AA' . $rowAt - 1)->getNumberFormat()->setFormatCode('@');
        $sheet->getStyle('AE7:AE' . $rowAt - 1)->getNumberFormat()->setFormatCode('@');

        foreach (range('A', 'Z') as $r) {
            $sheet->getColumnDimension($r)->setAutoSize(true);
        }
        $sheet->getColumnDimension('AA')->setAutoSize(true);
        $sheet->getColumnDimension('AB')->setAutoSize(true);
        $sheet->getColumnDimension('AC')->setAutoSize(true);
        $sheet->getColumnDimension('AD')->setAutoSize(true);
        $sheet->getColumnDimension('AE')->setAutoSize(true);
        $sheet->getColumnDimension('AF')->setAutoSize(true);
        $sheet->getColumnDimension('AG')->setAutoSize(true);

        $sheet->freezePane('B7');

        $sheet->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);

        $sheet->getPageMargins()->setTop(0.2);
        $sheet->getPageMargins()->setRight(0.2);
        $sheet->getPageMargins()->setLeft(0.2);
        $sheet->getPageMargins()->setBottom(0.2);
        $sheet->getPageSetup()->setFitToWidth(1);



        $stringjudul = "Report Joint Period from" . $request->dateFrom . ' to ' . $request->dateTo;
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $filename = $stringjudul;
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
    }

    function changeSuppliedReel(Request $request)
    {
        $data = $request->json()->all();

        $currentReel = DB::table('SPLSCN_TBL')
            ->where('SPLSCN_ID', $data['scan_id'])
            ->first();

        if ($currentReel) {
            try {
                DB::beginTransaction();
                $tobeSaved = [];

                $lastNum = $this->getLastSPLSCNID() + 1;
                foreach ($data['details'] as $r) {
                    if (DB::table('SPLSCN_TBL')->where('SPLSCN_UNQCODE', $r['code'])->count() > 0) {
                        return response()->json(['message' => 'Kode unik sudah digunakan'], 406);
                    }

                    $newID = date('Ymd') . $lastNum;
                    $_tobeSaved = [
                        'SPLSCN_ID' => $newID,
                        'SPLSCN_DOC' => $currentReel->SPLSCN_DOC,
                        'SPLSCN_CAT' => $currentReel->SPLSCN_CAT,
                        'SPLSCN_LINE' => $currentReel->SPLSCN_LINE,
                        'SPLSCN_FEDR' => $currentReel->SPLSCN_FEDR,
                        'SPLSCN_ORDERNO' => $currentReel->SPLSCN_ORDERNO,
                        'SPLSCN_ITMCD' => $currentReel->SPLSCN_ITMCD,
                        'SPLSCN_LOTNO' => $r['lot'],
                        'SPLSCN_SAVED' => $currentReel->SPLSCN_SAVED,
                        'SPLSCN_QTY' => $r['qty'],
                        'SPLSCN_LUPDT' => date('Y-m-d H:i:s'),
                        'SPLSCN_USRID' => $currentReel->SPLSCN_USRID,
                        'SPLSCN_EXPORTED' => $currentReel->SPLSCN_EXPORTED,
                        'SPLSCN_MC' => $currentReel->SPLSCN_MC,
                        'SPLSCN_PROCD' => $currentReel->SPLSCN_PROCD,
                        'SPLSCN_UNQCODE' => $r['code'],
                    ];
                    DB::table('SPLSCN_TBL')->insert($_tobeSaved);
                    $tobeSaved[] = $_tobeSaved;
                    $lastNum++;
                }

                foreach ($tobeSaved as $r) {
                    DB::table('SPLSCN_LOG2')->insert([
                        'SPLSCN_ID' => $r['SPLSCN_ID'],
                        'SPLSCN_DOC' => $r['SPLSCN_DOC'],
                        'SPLSCN_CAT' => $r['SPLSCN_CAT'],
                        'SPLSCN_LINE' => $r['SPLSCN_LINE'],
                        'SPLSCN_FEDR' => $r['SPLSCN_FEDR'],
                        'SPLSCN_ORDERNO' => $r['SPLSCN_ORDERNO'],
                        'SPLSCN_ITMCD' => $r['SPLSCN_ITMCD'],
                        'SPLSCN_LOTNO' => $r['SPLSCN_LOTNO'],
                        'SPLSCN_SAVED' => $r['SPLSCN_SAVED'],
                        'SPLSCN_QTY' => $r['SPLSCN_QTY'],
                        'SPLSCN_LUPDT' => $r['SPLSCN_LUPDT'],
                        'SPLSCN_USRID' => $r['SPLSCN_USRID'],
                        'SPLSCN_EXPORTED' => $r['SPLSCN_EXPORTED'],
                        'SPLSCN_MC' => $r['SPLSCN_MC'],
                        'SPLSCN_PROCD' => $r['SPLSCN_PROCD'],
                        'SPLSCN_UNQCODE' => $r['SPLSCN_UNQCODE'],
                        'SPLSCN_ID_PARENT' => $currentReel->SPLSCN_ID,
                        'SPLSCN_LOG_TYPE' => 'REPLACEMENT'
                    ]);
                }

                DB::table('SPLSCN_LOG2')->insert([
                    'SPLSCN_ID' => $currentReel->SPLSCN_ID,
                    'SPLSCN_DOC' => $currentReel->SPLSCN_DOC,
                    'SPLSCN_CAT' => $currentReel->SPLSCN_CAT,
                    'SPLSCN_LINE' => $currentReel->SPLSCN_LINE,
                    'SPLSCN_FEDR' => $currentReel->SPLSCN_FEDR,
                    'SPLSCN_ORDERNO' => $currentReel->SPLSCN_ORDERNO,
                    'SPLSCN_ITMCD' => $currentReel->SPLSCN_ITMCD,
                    'SPLSCN_LOTNO' => $currentReel->SPLSCN_LOTNO,
                    'SPLSCN_SAVED' => $currentReel->SPLSCN_SAVED,
                    'SPLSCN_QTY' => $currentReel->SPLSCN_QTY,
                    'SPLSCN_LUPDT' => $currentReel->SPLSCN_LUPDT,
                    'SPLSCN_USRID' => $currentReel->SPLSCN_USRID,
                    'SPLSCN_EXPORTED' => $currentReel->SPLSCN_EXPORTED,
                    'SPLSCN_MC' => $currentReel->SPLSCN_MC,
                    'SPLSCN_PROCD' => $currentReel->SPLSCN_PROCD,
                    'SPLSCN_UNQCODE' => $currentReel->SPLSCN_UNQCODE,
                    'SPLSCN_ID_PARENT' => '',
                    'SPLSCN_LOG_TYPE' => 'REPLACED'
                ]);

                DB::commit();
                return ['message' => 'Saved successfully'];
            } catch (Exception $e) {
                DB::rollBack();
                return response()->json(['message' => $e->getMessage()], 400);
            }
        }
    }

    function getLastSPLSCNID()
    {
        $lastID = DB::table('SPLSCN_TBL')
            ->whereDate('SPLSCN_LUPDT', date('Y-m-d'))
            ->orderByRaw("convert(bigint,SUBSTRING(SPLSCN_ID,9,11)) desc")
            ->first([DB::raw('substring(SPLSCN_ID, 9, 20) lser')]);
        if ($lastID) {
            return $lastID->lser;
        } else {
            return '0';
        }
    }
}
