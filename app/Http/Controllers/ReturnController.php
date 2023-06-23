<?php

namespace App\Http\Controllers;

use App\Models\C3LC;
use App\Models\ITH;
use App\Models\PartReturned;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReturnController extends Controller
{

    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
    }

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

    function save(Request $request)
    {
        $DocumentCount = DB::table("SPL_TBL")
            ->where("SPL_DOC", $request->doc)
            ->where("SPL_CAT", $request->category)
            ->where("SPL_LINE", $request->line)
            ->where("SPL_ITMCD", $request->item)->count();
        if ($DocumentCount > 0) {
            $PartScannedCount = DB::table("SPLSCN_TBL")
                ->where("SPLSCN_DOC", $request->doc)
                ->where("SPLSCN_CAT", $request->category)
                ->where("SPLSCN_LINE", $request->line)
                ->where("SPLSCN_ITMCD", $request->item)
                ->where("SPLSCN_LOTNO", $request->lotNumber)
                ->count();
            if ($PartScannedCount === 0) {
                $result[] = ['cd' => '01', 'msg' => 'PSN and label does not match'];
                return ['status' => $result];
            }

            $RSPartScanned = DB::select("EXEC sp_splvsret_nofr ?, ?, ?,  ?", [$request->doc, $request->category, $request->line, $request->item]);
            $RSPartScanned = json_decode(json_encode($RSPartScanned), true);
            $allow = false;
            $orderno = '';
            $fr = '';
            foreach ($RSPartScanned as $r) {
                if (($r['SPLSCN_ITMCD'] == $request->item) && ($r['SPLSCN_LOTNO'] == $request->lotNumber) && ($r['SPLSCN_QTY'] >= $request->qtyBefore)) {
                    if ($r['SPLSCN_QTY'] >= ($r['RETQTY'] + $request->qtyAfter)) {
                        $orderno = $r['SPLSCN_ORDERNO'];
                        $fr = trim($r['SPLSCN_FEDR']);
                        $allow = true;
                        break;
                    }
                }
            }
            if (!$allow) {
                $allow2 = false;
                foreach ($RSPartScanned as $r) {
                    if ((trim($r['SPLSCN_ITMCD']) == trim($request->item)) && (trim($r['SPLSCN_LOTNO']) == trim($request->lotNumber))) {
                        if ($r['SPLSCN_QTY'] >= ($r['RETQTY'] + $request->qtyAfter)) {
                            $orderno = $r['SPLSCN_ORDERNO'];
                            $fr = trim($r['SPLSCN_FEDR']);
                            $allow2 = true;
                            break;
                        }
                    }
                }
                if (!$allow2) {
                    $RSBalncePerItem = DB::select("EXEC sp_getreturnbalance_peritem ?, ?, ?,  ?", [$request->doc, $request->line, $request->category,  $request->item]);
                    $RSBalncePerItem = json_decode(json_encode($RSBalncePerItem), true);
                    $RSBalncePerItem = count($RSBalncePerItem) > 0 ? reset($RSBalncePerItem) : ['BALQTY' => 0];
                    if ($RSBalncePerItem['BALQTY'] >= $request->qtyAfter) {
                        #GET FR , ORDERNO
                        $RSSPLSCN = DB::table("SPLSCN_TBL")
                            ->select(DB::raw("SPLSCN_ID,SPLSCN_DOC,SPLSCN_CAT,SPLSCN_LINE,RTRIM(SPLSCN_FEDR) SPLSCN_FEDR,SPLSCN_ORDERNO,UPPER(SPLSCN_ITMCD) SPLSCN_ITMCD,SPLSCN_LOTNO,SPLSCN_SAVED,
                        SPLSCN_QTY,SPLSCN_LUPDT,SPLSCN_USRID,SPLSCN_EXPORTED"))
                            ->where('SPLSCN_DOC', $request->doc)
                            ->where('SPLSCN_CAT', $request->category)
                            ->where('SPLSCN_LINE', $request->line)
                            ->where('SPLSCN_ITMCD', $request->item)
                            ->where('SPLSCN_LOTNO', $request->lotNumber)
                            ->where('SPLSCN_QTY', $request->qtyBefore)
                            ->get();
                        #END
                        $RSSPLSCN = json_decode(json_encode($RSSPLSCN), true);
                        if (!empty($RSSPLSCN)) {
                            $RSSPLSCN = reset($RSSPLSCN);
                            $orderno = $RSSPLSCN['SPLSCN_ORDERNO'];
                            $fr = $RSSPLSCN['SPLSCN_FEDR'];
                        } else {
                            $result[] = ['cd' => '00', 'msg' => 'could not get FR and ORDER NO'];
                            return ['status' => $result];
                        }
                    } else {
                        $result[] = ['cd' => '00', 'msg' => 'Balance Qty < Return Qty'];
                        return ['status' => $result];
                    }
                }
            }
            //end validate

            $mlastid = $this->getLastIdOfReturnRecord();
            $mlastid++;
            $newid = date('Ymd') . $mlastid;
            $datas = [
                'RETSCN_ID' =>  $newid, 'RETSCN_SPLDOC' => $request->doc, 'RETSCN_CAT' => $request->category, 'RETSCN_LINE' => $request->line, 'RETSCN_FEDR' => $fr, 'RETSCN_ORDERNO' => $orderno,
                'RETSCN_ITMCD' => $request->item, 'RETSCN_LOT' => trim($request->lotNumber),
                'RETSCN_QTYBEF' => $request->qtyBefore, 'RETSCN_QTYAFT' => $request->qtyAfter, 'RETSCN_CNTRYID' => $request->countryId,
                'RETSCN_ROHS' => $request->roHs, 'RETSCN_LUPDT' => date('Y-m-d H:i:s'), 'RETSCN_USRID' => $request->userId
            ];
            PartReturned::insert($datas);
            return ['status' => [['cd' => '11', 'msg' => 'Saved']]];
        } else {
            $result[] = ['cd' => '00', 'msg' => 'Sorry, Item not found in PSN'];
            return ['status' => $result];
        }
    }

    private function getLastIdOfReturnRecord()
    {
        $RSLastCountedPart = DB::table("RETSCN_TBL")->select(DB::raw("substring(RETSCN_ID, 9, 20) lastNumber"))
            ->whereDate("RETSCN_LUPDT", date('Y-m-d'))
            ->orderBy(DB::raw("convert(bigint,SUBSTRING(RETSCN_ID,9,11))"), "DESC")
            ->take(1)
            ->first();
        $mlastid = $RSLastCountedPart->lastNumber;
        return $mlastid;
    }

    function saveAlternative(Request $request)
    {
        $RSPartScanned = DB::select("EXEC sp_splvsret_nofr ?, ?, ?,  ?", [$request->doc, $request->category, $request->line, $request->item]);
        $RSPartScanned = json_decode(json_encode($RSPartScanned), true);
        $allow = false;
        $orderno = '';
        $fr = '';
        $citemnm = '';
        $clot = '';
        $cqbf = '';
        foreach ($RSPartScanned as $r) {
            if (($r['SPLSCN_ITMCD'] == $request->item) && ($r['RLOGICQTY'] > $request->qtyAfter)) {
                $orderno = $r['SPLSCN_ORDERNO'];
                $fr = $r['SPLSCN_FEDR'];
                $clot = $r['SPLSCN_LOTNO'];
                $cqbf = $r['SPLSCN_QTY'];
                $citemnm = $r['MITM_SPTNO'];
                $allow = true;
                break;
            }
        }

        if ($allow) {
            $mlastid = $this->getLastIdOfReturnRecord();
            $mlastid++;
            $newid = date('Ymd') . $mlastid;
            $datas = [
                'RETSCN_ID' =>  $newid, 'RETSCN_SPLDOC' => $request->doc, 'RETSCN_CAT' => $request->category, 'RETSCN_LINE' => $request->line, 'RETSCN_FEDR' => $fr, 'RETSCN_ORDERNO' => $orderno,
                'RETSCN_ITMCD' => $request->item, 'RETSCN_LOT' => $clot,
                'RETSCN_QTYBEF' => $cqbf, 'RETSCN_QTYAFT' => $request->qtyAfter, 'RETSCN_CNTRYID' => $request->countryId,
                'RETSCN_ROHS' => $request->roHs, 'RETSCN_LUPDT' => date('Y-m-d H:i:s'), 'RETSCN_USRID' => $request->userId
            ];
            PartReturned::insert($datas);
            return ['status' => [['cd' => '11', 'msg' => 'Saved', "xitem" => $request->item, "xqty" => $request->qtyAfter, "xlot" => trim($request->lotNumber), "xitemnm" =>  $citemnm]]];
        } else {
            $myar[] = ['cd' => '00', 'msg' => 'could not return, please contact Mr. H '];
            die('{"status":' . json_encode($myar) . '}');
        }
    }

    function delete(Request $request)
    {
        $affectedRow = PartReturned::where("RETSCN_ID", $request->id)
            ->where(DB::raw("COALESCE(RETSCN_SAVED,'0')"), '0')
            ->delete();
        $result[] = $affectedRow > 0 ? ["cd" => "1", "msg" => "Deleted successfully"] : ["cd" => "0", "msg" => "could not be deleted, please refresh the page"];
        return ['status' => $result];
    }

    function setPartStatus(Request $request)
    {
        $affectedRow = PartReturned::where("RETSCN_ID", $request->id)->whereNull("RETSCN_SAVED")
            ->update(["RETSCN_HOLD" => $request->status], ['timestamps' => false]);
        $result[] = $affectedRow > 0 ? ["cd" => 1, "msg" => "OK"] : ["cd" => 0, "msg" => "Could not Hold/Release"];
        return ['status' => $result];
    }

    function saveByCombining(Request $request)
    {
        $result = [];
        if (is_array($request->item)) {
            $DocumentCount = DB::table("SPL_TBL")
                ->where("SPL_DOC", $request->doc)
                ->where("SPL_CAT", $request->category)
                ->where("SPL_LINE", $request->line)
                ->where("SPL_ITMCD", $request->item[0])->count();
            if ($DocumentCount > 0) {
                $ttldata = count($request->item);
                if ($ttldata === 1) {
                    return ['status' => [['cd' => '00', 'msg' => 'at least two record should be sent']]];
                }
                $isItemlotOK = true;
                $C3Data = [];

                $lotasHome = $request->lotNumber[0];
                if ($request->qtyAfter > $request->qtyBefore[0] && $request->lotNumber[0] != $request->lotNumber[1]) {
                    $lotasHome = substr($request->lotNumber[0], 0, 10);
                    $lotasHome .= '#C';
                }
                #PREPARE NEW ROW ID
                $mlastid = $this->getLastIdOfReturnRecord();
                $mlastid++;
                $newid = date('Ymd') . $mlastid;
                #END
                for ($i = 0; $i < $ttldata; $i++) {
                    $ttldata_psnscan = DB::table("SPLSCN_TBL")
                        ->where('SPLSCN_DOC', $request->doc)
                        ->where('SPLSCN_CAT', $request->category)
                        ->where('SPLSCN_LINE', $request->line)
                        ->where('SPLSCN_ITMCD', $request->item[$i])
                        ->where('SPLSCN_LOTNO', $request->lotNumber[$i])->count();
                    if ($ttldata_psnscan == 0) {
                        $isItemlotOK = false;
                        break;
                    }
                    $C3Data[] = [
                        'C3LC_ITMCD' => $request->item[0], 'C3LC_NLOTNO' => $lotasHome, 'C3LC_NQTY' => $request->qtyAfter, 'C3LC_LOTNO' => $request->lotNumber[$i], 'C3LC_QTY' => $request->qtyBefore[$i], 'C3LC_REFF' => $newid, 'C3LC_LINE' => $i,  'C3LC_USRID' => $request->userId, 'C3LC_LUPTD' => date('Y-m-d H:i:s')
                    ];
                }

                if ($isItemlotOK) {
                    $RSBalncePerItem = DB::select("EXEC sp_getreturnbalance_peritem ?, ?, ?,  ?", [$request->doc, $request->line, $request->category,  $request->item[0]]);
                    $RSBalncePerItem = json_decode(json_encode($RSBalncePerItem), true);
                    $RSBalncePerItem = count($RSBalncePerItem) > 0 ? reset($RSBalncePerItem) : ['BALQTY' => 0];

                    if ($RSBalncePerItem['BALQTY'] >= $request->qtyAfter) {
                        #GET FR , ORDERNO
                        $RSSPLSCN = DB::table("SPLSCN_TBL")
                            ->select(DB::raw("SPLSCN_ID,SPLSCN_DOC,SPLSCN_CAT,SPLSCN_LINE,RTRIM(SPLSCN_FEDR) SPLSCN_FEDR,SPLSCN_ORDERNO,UPPER(SPLSCN_ITMCD) SPLSCN_ITMCD,SPLSCN_LOTNO,SPLSCN_SAVED,
                        SPLSCN_QTY,SPLSCN_LUPDT,SPLSCN_USRID,SPLSCN_EXPORTED"))
                            ->where('SPLSCN_DOC', $request->doc)
                            ->where('SPLSCN_CAT', $request->category)
                            ->where('SPLSCN_LINE', $request->line)
                            ->where('SPLSCN_ITMCD', $request->item[0])
                            ->where('SPLSCN_LOTNO', $request->lotNumber[0])
                            ->where('SPLSCN_QTY', $request->qtyBefore[0])
                            ->get();
                        #END
                        $RSSPLSCN = json_decode(json_encode($RSSPLSCN), true);
                        if (count($RSSPLSCN) > 0) {
                            if (count($C3Data) > 1) {
                                C3LC::insert($C3Data);
                            }
                            $rsbefore = reset($RSSPLSCN);
                            $datas = [
                                'RETSCN_ID' =>  $newid, 'RETSCN_SPLDOC' => $request->doc, 'RETSCN_CAT' => $request->category, 'RETSCN_LINE' => $request->line, 'RETSCN_FEDR' => $rsbefore['SPLSCN_FEDR'], 'RETSCN_ORDERNO' => $rsbefore['SPLSCN_ORDERNO'],
                                'RETSCN_ITMCD' => $request->item[0], 'RETSCN_LOT' => $lotasHome, 'RETSCN_QTYBEF' => $request->qtyBefore[0], 'RETSCN_QTYAFT' => $request->qtyAfter, 'RETSCN_CNTRYID' => $request->countryId,
                                'RETSCN_ROHS' => $request->roHs, 'RETSCN_LUPDT' => date('Y-m-d H:i:s'), 'RETSCN_USRID' => $request->userId
                            ];
                            $toret = PartReturned::insert($datas);
                            if ($toret > 0) {
                                $result[] = ['cd' => '11', 'msg' => 'Saved', 'lotno' => $lotasHome];
                            }
                        } else {
                            $result[] = ['cd' => '00', 'msg' => 'could not get FR and ORDER NO', '$C3Data' => $C3Data];
                        }
                    } else {
                        $result[] = ['cd' => '00', 'msg' => 'Balance Qty < Return Qty'];
                    }
                } else {
                    $result[] = ['cd' => '00', 'msg' => 'PSN and Item Lot does not match'];
                }
            } else {
                $result[] = ['cd' => '00', 'msg' => 'PSN and Item does not match'];
            }
        } else {
            $result[] = ['cd' => '00', 'msg' => 'It seems You are using wrong menu or function'];
        }
        return ['status' => $result];
    }

    function resume(Request $request)
    {
        $data = [];
        if (isset($request->output)) {
            # data to be upploaded to MEGA Per PSN
            $data = DB::table("RETSCN_TBL")->select("RETSCN_ITMCD", DB::raw("CONVERT(bigint,SUM(RETSCN_QTYAFT)) RETQTY"))
                ->where("RETSCN_SPLDOC", $request->doc)
                ->where(DB::raw("ISNULL(RETSCN_HOLD,'0')"), "0")
                ->groupBy("RETSCN_ITMCD")
                ->get();
        } else {
            # data of Supplied Part Vs Returned Part Per PSN
            $data = DB::select("EXEC sp_splvssupvsret_psnonly ?", [$request->doc]);
        }
        return ['data' => $data];
    }

    function confirm(Request $request)
    {
        if (!is_array($request->item)) {
            return ['message' => 'there is no part code returned'];
        }
        $ttlitem = count($request->item);
        $cwh_out = '';
        $thetime = '07:01:00';
        if ($ttlitem > 0) {
            $rsbg = substr($request->doc, 0, 3) == "PR-" ? DB::table("SPL_TBL")->select(DB::raw("RTRIM(SPL_BG) PPSN1_BSGRP"))
                ->where("SPL_DOC", $request->doc)->groupBy("SPL_BG")->get()
                : DB::table("XPPSN1")->select(DB::raw("RTRIM(PPSN1_BSGRP) PPSN1_BSGRP"))
                ->where("PPSN1_PSNNO", $request->doc)->groupBy("PPSN1_BSGRP")->get();
            $rsbg = json_decode(json_encode($rsbg), true);
            foreach ($rsbg as $r) {
                switch ($r['PPSN1_BSGRP']) {
                    case 'PSI1PPZIEP':
                        $cwh_inc = 'ARWH1';
                        $cwh_out = 'PLANT1';
                        break;
                    case 'PSI2PPZADI':
                        $cwh_inc = 'ARWH2';
                        $cwh_out = 'PLANT2';
                        break;
                    case 'PSI2PPZINS':
                        $cwh_inc = 'NRWH2';
                        $cwh_out = 'PLANT_NA';
                        break;
                    case 'PSI2PPZOMC':
                        $cwh_inc = 'NRWH2';
                        $cwh_out = 'PLANT_NA';
                        break;
                    case 'PSI2PPZOMI':
                        $cwh_inc = 'ARWH2';
                        $cwh_out = 'PLANT2';
                        break;
                    case 'PSI2PPZSSI':
                        $cwh_inc = 'NRWH2';
                        $cwh_out = 'PLANT_NA';
                        break;
                    case 'PSI2PPZSTY':
                        $cwh_inc = 'ARWH2';
                        $cwh_out = 'PLANT2';
                        break;
                    case 'PSI2PPZTDI':
                        $cwh_inc = 'ARWH2';
                        $cwh_out = 'PLANT2';
                        break;
                }
            }
            $thelupdt = $request->dateConfirm . " " . $thetime;
            $_affectedRowTemp = 0;
            $_affectedRowITH = 0;
            for ($b = 0; $b < $ttlitem; $b++) {
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
                    ->where("RETSCN_ITMCD", $request->item[$b])
                    ->where(DB::raw("ISNULL(RETSCN_HOLD,'0')"), '0')
                    ->get();
                $RSCountedPart = json_decode(json_encode($RSCountedPart), true);
                foreach ($RSCountedPart as $r) {
                    $fieldsToBeUpdated = [
                        'RETSCN_SAVED' => '1',
                        'RETSCN_CNFRMDT' => $request->dateConfirm,
                        'RETSCN_LUPDT' => date('Y-m-d H:i:s')
                    ];
                    $affectedRow = PartReturned::where("RETSCN_ID", $r['RETSCN_ID'])
                        ->whereNull("RETSCN_SAVED")
                        ->where(DB::raw("ISNULL(RETSCN_HOLD,'0')"), "!=", '1')
                        ->update($fieldsToBeUpdated, ['timestamps' => false]);
                    $_affectedRowTemp += $affectedRow;
                    if ($affectedRow > 0) {
                        $ithdoc = $request->doc . '|' . trim($r['RETSCN_CAT']) . '|' . trim($r['RETSCN_LINE']) . '|' . trim($r['RETSCN_FEDR']);
                        $datas = [
                            'ITH_ITMCD' => $request->item[$b],
                            'ITH_DATE' => $request->dateConfirm,
                            'ITH_FORM' => 'INC-RET',
                            'ITH_DOC' => $ithdoc,
                            'ITH_QTY' => $r['RETSCN_QTYAFT'],
                            'ITH_WH' => $cwh_inc,
                            'ITH_REMARK' => $r['RETSCN_ID'],
                            'ITH_LUPDT' => $thelupdt,
                            'ITH_USRID' => $request->userId
                        ];
                        $affectedRow = ITH::insert($datas);
                        $_affectedRowITH += $affectedRow;
                        $datas = [
                            'ITH_ITMCD' => $request->item[$b],
                            'ITH_DATE' => $request->dateConfirm,
                            'ITH_FORM' => 'OUT-RET',
                            'ITH_DOC' => $ithdoc,
                            'ITH_QTY' => -$r['RETSCN_QTYAFT'],
                            'ITH_WH' => $cwh_out,
                            'ITH_REMARK' => $r['RETSCN_ID'],
                            'ITH_LUPDT' => $thelupdt,
                            'ITH_USRID' => $request->userId
                        ];
                        $affectedRow = ITH::insert($datas);
                        $_affectedRowITH += $affectedRow;
                    }
                }
            }
            return ['message' => $_affectedRowTemp > 0 ||  $_affectedRowITH > 0  ? 'confirmed' : 'already confirmed', '_affectedRowTemp' => $_affectedRowTemp, '_affectedRowITH' => $_affectedRowITH];
        } else {
            return ['message' => 'no data'];
        }
    }
}
