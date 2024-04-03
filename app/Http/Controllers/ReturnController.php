<?php

namespace App\Http\Controllers;

use App\Models\C3LC;
use App\Models\ITH;
use App\Models\Label;
use App\Models\PartReturned;
use App\Models\RawMaterialLabel;
use App\Models\RETRM;
use App\Traits\LabelingTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReturnController extends Controller
{
    use LabelingTrait;

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

            $LotNumber = trim($request->lotNumber);

            $Response = $this->generateLabelId([
                'machineName' => $request->machineName ?? 'DF',
                'documentCode' => $request->doc,
                'itemCode' => $request->item,
                'qty' => $request->qtyAfter,
                'lotNumber' => $LotNumber,
                'userID' => $request->userId,
            ]);

            $datas = [
                'RETSCN_ID' =>  $newid,
                'RETSCN_SPLDOC' => $request->doc,
                'RETSCN_CAT' => $request->category,
                'RETSCN_LINE' => $request->line,
                'RETSCN_FEDR' => $fr,
                'RETSCN_ORDERNO' => $orderno,
                'RETSCN_ITMCD' => $request->item,
                'RETSCN_LOT' => $LotNumber,
                'RETSCN_QTYBEF' => $request->qtyBefore,
                'RETSCN_QTYAFT' => $request->qtyAfter,
                'RETSCN_CNTRYID' => $request->countryId,
                'RETSCN_ROHS' => $request->roHs,
                'RETSCN_LUPDT' => date('Y-m-d H:i:s'),
                'RETSCN_USRID' => $request->userId,
                'RETSCN_UNIQUEKEY' => $Response['data'],
                'created_at' => date('Y-m-d H:i:s')
            ];

            PartReturned::insert($datas);
            return ['status' => [['cd' => '11', 'msg' => 'Saved', 'RETSCN_UNIQUEKEY' => $Response['data']]]];
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
        $mlastid = $RSLastCountedPart->lastNumber ?? 0;
        return $mlastid;
    }

    private function getLastIdOfReturnWithoutPSNRecord()
    {
        $RSLastCountedPart = DB::table("RETRM_TBL")->select(DB::raw("ISNULL(MAX(CONVERT(INT,SUBSTRING(RETRM_DOC,9,4))),0) lastNumber"))
            ->whereDate("RETRM_CREATEDAT", date('Y-m-d'))
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

            $Response = $this->generateLabelId([
                'machineName' => $request->machineName ?? 'DF',
                'documentCode' => $request->doc,
                'itemCode' => $request->item,
                'qty' => $request->qtyAfter,
                'lotNumber' => $clot,
                'userID' => $request->userId,
            ]);

            $datas = [
                'RETSCN_ID' =>  $newid,
                'RETSCN_SPLDOC' => $request->doc,
                'RETSCN_CAT' => $request->category,
                'RETSCN_LINE' => $request->line,
                'RETSCN_FEDR' => $fr,
                'RETSCN_ORDERNO' => $orderno,
                'RETSCN_ITMCD' => $request->item,
                'RETSCN_LOT' => $clot,
                'RETSCN_QTYBEF' => $cqbf,
                'RETSCN_QTYAFT' => $request->qtyAfter,
                'RETSCN_CNTRYID' => $request->countryId,
                'RETSCN_ROHS' => $request->roHs,
                'RETSCN_LUPDT' => date('Y-m-d H:i:s'),
                'RETSCN_USRID' => $request->userId,
                'RETSCN_UNIQUEKEY' => $Response['data'],
                'created_at' => date('Y-m-d H:i:s')
            ];
            PartReturned::insert($datas);

            return ['status' => [[
                'cd' => '11', 'msg' => 'Saved',
                "xitem" => $request->item,
                "xqty" => $request->qtyAfter,
                "xlot" => $clot,
                "xitemnm" => $citemnm,
                "RETSCN_UNIQUEKEY" => $Response['data']
            ]]];
        } else {
            $myar[] = ['cd' => '00', 'msg' => 'could not return, please contact Mr. H '];
            die('{"status":' . json_encode($myar) . '}');
        }
    }

    function delete(Request $request)
    {
        $partReturned = PartReturned::where("RETSCN_ID", $request->id)->select('RETSCN_UNIQUEKEY')->first();

        $affectedRow = PartReturned::where("RETSCN_ID", $request->id)
            ->where(DB::raw("COALESCE(RETSCN_SAVED,'0')"), '0')
            ->delete();

        if ($affectedRow > 0) {
            RawMaterialLabel::where('code', $partReturned->RETSCN_UNIQUEKEY)->update([
                'deleted_by' => $request->userId,
                'deleted_at' => date('Y-m-d H:i:s'),
            ]);
            $result[] = ["cd" => "1", "msg" => "Deleted successfully"];
        } else {
            $result[] = ["cd" => "0", "msg" => "could not be deleted, please refresh the page"];
        }

        return ['status' => $result];
    }

    function setPartStatus(Request $request)
    {
        $affectedRow = PartReturned::where("RETSCN_ID", $request->id)
            ->whereNull("RETSCN_SAVED")
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
                    $lotasHome = substr($request->lotNumber[0], 0, 23);
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

                            $Response = $this->generateLabelId([
                                'machineName' => $request->machineName ?? 'DF',
                                'documentCode' => $request->doc,
                                'itemCode' => $request->item[0],
                                'qty' => $request->qtyAfter,
                                'lotNumber' => $lotasHome,
                                'userID' => $request->userId,
                            ]);

                            $rsbefore = reset($RSSPLSCN);
                            $datas = [
                                'RETSCN_ID' =>  $newid,
                                'RETSCN_SPLDOC' => $request->doc,
                                'RETSCN_CAT' => $request->category,
                                'RETSCN_LINE' => $request->line,
                                'RETSCN_FEDR' => $rsbefore['SPLSCN_FEDR'],
                                'RETSCN_ORDERNO' => $rsbefore['SPLSCN_ORDERNO'],
                                'RETSCN_ITMCD' => $request->item[0],
                                'RETSCN_LOT' => $lotasHome,
                                'RETSCN_QTYBEF' => $request->qtyBefore[0],
                                'RETSCN_QTYAFT' => $request->qtyAfter,
                                'RETSCN_CNTRYID' => $request->countryId,
                                'RETSCN_ROHS' => $request->roHs,
                                'RETSCN_LUPDT' => date('Y-m-d H:i:s'),
                                'RETSCN_USRID' => $request->userId,
                                'RETSCN_UNIQUEKEY' => $Response['data'],
                                'created_at' => date('Y-m-d H:i:s')
                            ];

                            $toret = PartReturned::insert($datas);
                            if ($toret > 0) {
                                $result[] = ['cd' => '11', 'msg' => 'Saved', 'lotno' => $lotasHome, 'RETSCN_UNIQUEKEY' => $Response['data']];
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

    function saveFromXray(Request $request)
    {
        $result = [];

        $ttlRequest = count($request->item);
        $perItemResult = [];
        for ($v = 0; $v < $ttlRequest; $v++) {

            $DocumentCount = DB::table("SPL_TBL")
                ->where("SPL_DOC", $request->doc)
                ->where("SPL_CAT", $request->category)
                ->where("SPL_LINE", $request->line)
                ->where("SPL_ITMCD", $request->item[$v])->count();
            if ($DocumentCount > 0) {

                #PREPARE NEW ROW ID
                $mlastid = $this->getLastIdOfReturnRecord();
                $mlastid++;
                $newid = date('Ymd') . $mlastid;
                #END

                $RSBalncePerItem = DB::select("EXEC sp_getreturnbalance_peritem ?, ?, ?,  ?", [$request->doc, $request->line, $request->category,  $request->item[$v]]);
                $RSBalncePerItem = json_decode(json_encode($RSBalncePerItem), true);
                $RSBalncePerItem = count($RSBalncePerItem) > 0 ? reset($RSBalncePerItem) : ['BALQTY' => 0];

                if ($RSBalncePerItem['BALQTY'] >= $request->qtyAfter[$v]) {
                    #GET FR , ORDERNO
                    $RSSPLSCN = DB::table("SPLSCN_TBL")
                        ->select(DB::raw("SPLSCN_ID,SPLSCN_DOC,SPLSCN_CAT,SPLSCN_LINE,RTRIM(SPLSCN_FEDR) SPLSCN_FEDR,SPLSCN_ORDERNO,UPPER(SPLSCN_ITMCD) SPLSCN_ITMCD,SPLSCN_LOTNO,SPLSCN_SAVED,
                            SPLSCN_QTY,SPLSCN_LUPDT,SPLSCN_USRID,SPLSCN_EXPORTED"))
                        ->where('SPLSCN_DOC', $request->doc)
                        ->where('SPLSCN_CAT', $request->category)
                        ->where('SPLSCN_LINE', $request->line)
                        ->where('SPLSCN_ITMCD', $request->item[$v])
                        ->where('SPLSCN_LOTNO', $request->lotNumber[$v])
                        ->where('SPLSCN_QTY', $request->qtyBefore[$v])
                        ->get();
                    #END
                    $RSSPLSCN = json_decode(json_encode($RSSPLSCN), true);
                    if (count($RSSPLSCN) > 0) {

                        $rsbefore = reset($RSSPLSCN);
                        $datas = [
                            'RETSCN_ID' =>  $newid,
                            'RETSCN_SPLDOC' => $request->doc,
                            'RETSCN_CAT' => $request->category,
                            'RETSCN_LINE' => $request->line,
                            'RETSCN_FEDR' => $rsbefore['SPLSCN_FEDR'],
                            'RETSCN_ORDERNO' => $rsbefore['SPLSCN_ORDERNO'],
                            'RETSCN_ITMCD' => $request->item[$v],
                            'RETSCN_QTYBEF' => $request->qtyBefore[$v],
                            'RETSCN_LOT' => $request->lotNumber[$v],
                            'RETSCN_QTYAFT' => $request->qtyAfter[$v],
                            'RETSCN_CNTRYID' => '01',
                            'RETSCN_ROHS' => '1',
                            'RETSCN_LUPDT' => date('Y-m-d H:i:s'),
                            'RETSCN_USRID' => $request->userId,
                            'RETSCN_UNIQUEKEY' => $request->uniqueKey[$v],
                            'created_at' => date('Y-m-d H:i:s')
                        ];


                        RawMaterialLabel::insert([
                            'code' => $request->uniqueKey[$v],
                            'doc_code' => $request->doc,
                            'item_code' => $request->item[$v],
                            'quantity' => $request->qtyAfter[$v],
                            'lot_code' => $request->lotNumber[$v],
                            'created_by' => $request->userId,
                            'created_at' => date('Y-m-d H:i:s'),
                            'composed' => NULL,
                        ]);

                        $toret = PartReturned::insert($datas);
                        if ($toret > 0) {
                            $result[] = [
                                'cd' => '11', 'msg' => 'Saved', '_unique' => $request->uniqueKey[$v]
                            ];
                        }
                    } else {
                        $result[] = ['cd' => '00', 'msg' => 'could not get FR and ORDER NO', '_unique' => $request->uniqueKey[$v]];
                    }
                } else {
                    $result[] = ['cd' => '00', 'msg' => 'Balance Qty < Return Qty', '_unique' => $request->uniqueKey[$v]];
                }
            } else {
                $result[] = ['cd' => '00', 'msg' => 'PSN and Item does not match', '_unique' => $request->uniqueKey[$v]];
            }
        }
        return ['status' => $result];
    }

    function resume(Request $request)
    {
        $data = [];
        $result = [];
        if (isset($request->doc)) {
            if (isset($request->output)) {
                # data to be upploaded to MEGA Per PSN
                $data = DB::table("RETSCN_TBL")->select("RETSCN_ITMCD", DB::raw("CONVERT(bigint,SUM(RETSCN_QTYAFT)) RETQTY"))
                    ->where("RETSCN_SPLDOC", $request->doc)
                    ->where(DB::raw("ISNULL(RETSCN_HOLD,'0')"), "0")
                    ->groupBy("RETSCN_ITMCD")
                    ->get();
            } else {
                # data of Supplied Part Vs Returned Part Per PSN
                if ($request->outstanding === '1') {
                    $data = DB::select("EXEC sp_splvssupvsret_psnonly ?", [$request->doc]);
                } else {
                    $items = DB::select("SELECT
                                        RETSCN_ITMCD
                                    FROM
                                        (
                                        SELECT
                                            A.*
                                        FROM
                                            OPENQUERY(
                                            [SRVMEGA],
                                            'SELECT RTRIM(PPSN2_PSNNO) PPSN2_PSNNO
                                            ,RTRIM(PPSN2_SUBPN) SUBPN
                                            ,SUM(PPSN2_RTNQT) MGQT
                                        FROM PSI_MEGAEMS.dbo.PPSN2_TBL
                                        WHERE 
                                        SUBSTRING(PPSN2_PSNNO, 8, 4) IN (
                                                YEAR(DATEADD(MONTH, - 1, GETDATE()))
                                                ,YEAR(GETDATE())
                                                )
                                            AND SUBSTRING(PPSN2_PSNNO, 13, 2) IN (
                                                MONTH(DATEADD(MONTH, - 1, GETDATE()))
                                                ,MONTH(GETDATE())	
                                                )	
                                            AND PPSN2_PSNNO= ''$request->doc''
                                        GROUP BY PPSN2_PSNNO
                                            ,PPSN2_SUBPN
                                        '
                                            ) A
                                        ) V1
                                        LEFT JOIN (
                                        SELECT
                                            RETSCN_SPLDOC,
                                            RETSCN_ITMCD,
                                            SUM(RETSCN_QTYAFT) SCNQT
                                        FROM
                                            RETSCN_TBL
                                        WHERE
                                            SUBSTRING(RETSCN_SPLDOC, 8, 4) IN (
                                            YEAR(DATEADD(MONTH, - 1, GETDATE())),
                                            YEAR(GETDATE())
                                            )
                                            AND SUBSTRING(RETSCN_SPLDOC, 13, 2) IN (
                                            MONTH(DATEADD(MONTH, - 1, GETDATE())),
                                            MONTH(GETDATE())
                                            )
                                            AND SUBSTRING(RETSCN_SPLDOC, 1, 2) != 'PR'
                                            AND RETSCN_SAVED IS NOT NULL
                                        GROUP BY
                                            RETSCN_SPLDOC,
                                            RETSCN_ITMCD
                                        ) V2 ON PPSN2_PSNNO = RETSCN_SPLDOC
                                        AND SUBPN = RETSCN_ITMCD
                                    WHERE
                                        MGQT < SCNQT
                                        AND PPSN2_PSNNO LIKE ?
                                        AND PPSN2_PSNNO NOT IN (
                                        SELECT
                                            SPLSCN_DOC
                                        FROM
                                            V_SPLSCN_TBLC
                                        WHERE
                                            SPLSCN_DATE = CONVERT(DATE, GETDATE())
                                            and SPLSCN_DOC not like 'PR-%'
                                        GROUP BY
                                            SPLSCN_DOC
                                        )
                                    GROUP BY
                                        RETSCN_ITMCD", ['%' . $request->doc . '%']);

                    $itemArray = [];
                    foreach ($items as $r) {
                        $itemArray[] = $r->RETSCN_ITMCD;
                    }

                    if (!empty($itemArray)) {
                        $strItem = "'" . implode("','", $itemArray) . "'";
                        $data = DB::select("SELECT
                                    SPL_ITMCD,
                                    SPL_DOC,
                                    RTRIM(MITM_SPTNO) MITM_SPTNO,
                                    SPL_QTYREQ,
                                    isnull(SCNQTY, 0) SCNQTY,
                                    (isnull(SCNQTY, 0) - SPL_QTYREQ) LOGIC,
                                    ISNULL(RETQTY, 0) TTLRET
                                FROM
                                    (
                                    SELECT
                                        SPL_ITMCD,
                                        SPL_DOC,
                                        SUM(SPL_QTYREQ) SPL_QTYREQ
                                    FROM
                                        SPL_TBL
                                    WHERE
                                        SPL_DOC = ? AND
                                        SPL_ITMCD IN ($strItem)
                                    GROUP BY
                                        SPL_ITMCD,
                                        SPL_DOC
                                    ) v1
                                    left join (
                                    SELECT
                                        SPLSCN_ITMCD,
                                        SPLSCN_DOC,
                                        SUM(SPLSCN_QTY) SCNQTY
                                    FROM
                                        SPLSCN_TBL
                                    WHERE
                                        SPLSCN_DOC = ?
                                    GROUP BY
                                        SPLSCN_ITMCD,
                                        SPLSCN_DOC
                                    ) v2 on SPL_ITMCD = SPLSCN_ITMCD
                                    AND SPL_DOC = SPLSCN_DOC
                                    LEFT JOIN (
                                    SELECT
                                        RETSCN_ITMCD,
                                        RETSCN_SPLDOC,
                                        SUM(RETSCN_QTYAFT) RETQTY
                                    FROM
                                        RETSCN_TBL
                                    WHERE
                                        RETSCN_SPLDOC = ?
                                        and ISNULL(RETSCN_HOLD, '0') = '0'
                                    GROUP BY
                                        RETSCN_ITMCD,
                                        RETSCN_SPLDOC
                                    ) v3 on SPL_ITMCD = RETSCN_ITMCD
                                    AND SPL_DOC = RETSCN_SPLDOC
                                    INNER JOIN MITM_TBL ON SPL_ITMCD = MITM_ITMCD
                                where
                                    isnull(RETQTY, 0) > 0
                                ORDER BY
                                    SPL_DOC,
                                    SPL_ITMCD ASC", [$request->doc, $request->doc, $request->doc]);
                    }
                }
            }
        } else {
            $RSSub = DB::table("v_ith_tblc")->select(DB::raw("ITH_DATEC RETSCN_DATE,SUBSTRING(ITH_DOC,1,19) RETSCN_SPLDOC, ITH_ITMCD RETSCN_ITMCD,SUM(ITH_QTY) RTNQTY"))
                ->where("ITH_FORM", 'INC-RET')
                ->where("ITH_DOC", "LIKE", "%PR-%")
                ->where("ITH_DATEC", ">=", $request->dateFrom)
                ->where("ITH_DATEC", "<=", $request->dateTo)
                ->groupByRaw("ITH_DATEC,SUBSTRING(ITH_DOC,1,19), ITH_ITMCD");
            $RSSub2 = DB::table("SPL_TBL")->select(DB::raw("SPL_DOC,SPL_ITMCD,MAX(SPL_REFDOCNO) SPL_REFDOCNO"))
                ->where("SPL_DOC", "LIKE", "PR-%")
                ->groupBy(['SPL_DOC', 'SPL_ITMCD']);
            $data = DB::query()->fromSub($RSSub, "V1")->select(DB::raw("V1.*,UPPER(SPL_REFDOCNO) REFFDOC"))
                ->leftJoinSub($RSSub2, "V2", function ($join) {
                    $join->on("RETSCN_SPLDOC", "=", "SPL_DOC")->on("RETSCN_ITMCD", "=", "SPL_ITMCD");
                })
                ->orderByRaw('1,2,3')->get();
            $result[] = count($data) ? ['cd' => 1, 'msg' => 'Go ahead'] : ['cd' => 0, 'msg' => 'Not found'];
        }
        return ['data' => $data, 'status' => $result];
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

    function reportReturnWithoutPSN(Request $request)
    {
        $RSSub = DB::table("ITMLOC_TBL")->selectRaw("ITMLOC_ITM,MAX(ITMLOC_LOC) ITMLOC_LOC,MAX(ITMLOC_BG) ITMLOC_BG")
            ->groupBy("ITMLOC_ITM");
        $RS = DB::table("RETRM_TBL")->select(DB::raw("RETRM_TBL.*,ITMLOC_LOC,RTRIM(MITM_SPTNO) SPTNO,RTRIM(MITM_ITMD1) ITMD1"))
            ->leftJoinSub($RSSub, "VLOC", function ($join) {
                $join->on("RETRM_ITMCD", "=", "ITMLOC_ITM");
            })->leftJoin("MITM_TBL", "RETRM_ITMCD", "=", "MITM_ITMCD")
            ->whereDate("RETRM_CREATEDAT", ">=", $request->dateFrom)
            ->whereDate("RETRM_CREATEDAT", "<=", $request->dateTo)
            ->where("ITMLOC_BG", $request->businessGroup)
            ->where("RETRM_ITMCD", "like", "%{$request->item}%")
            ->get();
        return ['data' => $RS];
    }

    function returnWithoutPSN(Request $request)
    {
        $AMONTHPATRN = ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'X', 'Y', 'Z'];
        $currentDateTime = date('Y-m-d H:i:s');
        $currentDate = date('Y-m-d');
        $_year = substr(date('Y'), -2);
        $_month = (int)date('m');
        $_day = date('d');
        $cwh_inc = '';
        $cwh_out = '';
        $result = [];
        $rsbg = DB::table("SPL_TBL")->selectRaw("RTRIM(SPL_BG) SPL_BG")
            ->where("SPL_ITMCD", $request->item)
            ->groupBy("SPL_BG")->get();
        $rsbg = json_decode(json_encode($rsbg), true);
        foreach ($rsbg as $r) {
            switch ($r['SPL_BG']) {
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
            break;
        }

        if (DB::table("RETRM_TBL")
            ->where("RETRM_ITMCD", $request->item)
            ->where("RETRM_OLDQTY", $request->qtyBefore)
            ->where("RETRM_LOTNUM", $request->lotNumber)
            ->whereDate("RETRM_CREATEDAT", $currentDate)->count()
        ) {
            $result[] = ['cd' => '0', 'msg' => 'it was already returned'];
        } else {
            $lastNumber = $this->getLastIdOfReturnWithoutPSNRecord() + 1;
            $doc = "RWP" . $_year . $AMONTHPATRN[($_month - 1)] . $_day . $lastNumber;

            $Response = $this->generateLabelId([
                'machineName' => $request->machineName ?? 'DF',
                'documentCode' => $doc,
                'itemCode' => $request->item,
                'qty' => $request->qtyAfter,
                'lotNumber' => $request->lotNumber,
                'userID' => $request->userId,
            ]);

            $data = [
                'RETRM_DOC' => $doc,
                'RETRM_LINE' => 1,
                'RETRM_ITMCD' => $request->item,
                'RETRM_OLDQTY' => $request->qtyBefore,
                'RETRM_NEWQTY' => $request->qtyAfter,
                'RETRM_LOTNUM' => $request->lotNumber,
                'RETRM_CREATEDAT' => $currentDateTime,
                'RETRM_USRID' => $request->userId,
                'RETRM_UNIQUEKEY' => $Response['data']
            ];
            $rv = RETRM::insert($data);

            $datab[] = [
                'ITH_ITMCD' => $request->item,
                'ITH_WH' =>  $cwh_inc,
                'ITH_DOC' => $doc,
                'ITH_DATE' => $currentDate,
                'ITH_FORM' => 'INCRTN-NO-PSN',
                'ITH_QTY' => $request->qtyAfter,
                'ITH_REMARK' => $request->lotNumber,
                'ITH_USRID' =>  $request->userId,
                'ITH_LUPDT' =>  date('Y-m-d H:i:s')
            ];

            $datab[] = [
                'ITH_ITMCD' => $request->item,
                'ITH_WH' =>  $cwh_out,
                'ITH_DOC' => $doc,
                'ITH_DATE' => $currentDate,
                'ITH_FORM' => 'OUTRTN-NO-PSN',
                'ITH_QTY' => -1 * $request->qtyAfter,
                'ITH_REMARK' => $request->lotNumber,
                'ITH_USRID' =>  $request->userId,
                'ITH_LUPDT' =>  date('Y-m-d H:i:s')
            ];
            ITH::insert($datab);
            $result[] = $rv > 0 ? ['cd' => '1', 'msg' => 'OK', 'SER_ID' => $Response['data']] : ['cd' => '0', 'msg' => 'could not be saved'];
        }
        return ['status' => $result];
    }

    function cancelReturnWithoutPSN(Request $request)
    {
        $currentDate = date('Y-m-d');
        $idscan = $request->id;
        $itemcd = $request->item;
        $rs = RETRM::select("*")->where("RETRM_DOC", $idscan)->get();
        $rs = json_decode(json_encode($rs), true);

        $rsbg = DB::table("SPL_TBL")->selectRaw("RTRIM(SPL_BG) SPL_BG")
            ->where("SPL_ITMCD", $request->item)
            ->groupBy("SPL_BG")->get();
        $rsbg = json_decode(json_encode($rsbg), true);

        $cwh_inc = '';
        $cwh_out = '';
        foreach ($rsbg as $r) {
            switch ($r['SPL_BG']) {
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
            break;
        }
        $myar = [];
        if (count($rs) > 0) {
            if (DB::table("ITH_TBL")->where("ITH_DOC", $idscan)->count() > 0) {
                $newqty = 0;
                $lotnum = "";
                foreach ($rs as $r) {
                    $newqty = $r['RETRM_NEWQTY'];
                    $lotnum = $r['RETRM_LOTNUM'];
                }
                $txMonth = $txYear = null;
                $rsTransaction = RETRM::selectRaw("YEAR(RETRM_CREATEDAT) TXYEAR, MONTH(RETRM_CREATEDAT) TXMONTH")
                    ->where("RETRM_DOC", $idscan)->get();
                $rsTransaction = json_decode(json_encode($rsTransaction), true);

                foreach ($rsTransaction as $r) {
                    $txMonth = $r['TXMONTH'];
                    $txYear = $r['TXYEAR'];
                }
                if (
                    DB::connection('sqlsrv_it_inventory')->table('RPSAL_INVENTORY')
                    ->where("INV_MONTH", $txMonth)
                    ->where("INV_YEAR", $txYear)
                    ->where("INV_ITMNUM", $itemcd)->count() > 0
                ) {
                    $myar[] = ['cd' => '0', 'msg' => 'Could not be canceled because it was already uploaded to IT Inventory'];
                } else {
                    $retRM = RETRM::where('RETRM_DOC', $idscan)->select('RETRM_UNIQUEKEY')->first();

                    if (RETRM::where('RETRM_DOC', $idscan)
                        ->where('RETRM_ITMCD', $itemcd)->delete()
                    ) {
                        Label::where('SER_ID', $retRM->RETRM_UNIQUEKEY)->delete();

                        $datab = [
                            'ITH_ITMCD' => $itemcd,
                            'ITH_WH' =>  $cwh_inc,
                            'ITH_DOC' => $idscan,
                            'ITH_DATE' => $currentDate,
                            'ITH_FORM' => 'CANCEL-INCRTN-NO-PSN',
                            'ITH_QTY' => -1 * $newqty,
                            'ITH_REMARK' => $lotnum,
                            'ITH_LUPDT' => date('Y-m-d H:i:s'),
                            'ITH_USRID' =>  $request->userId
                        ];
                        ITH::insert($datab);
                        $datab = [
                            'ITH_ITMCD' => $itemcd,
                            'ITH_WH' =>  $cwh_out,
                            'ITH_DOC' => $idscan,
                            'ITH_DATE' => $currentDate,
                            'ITH_FORM' => 'CANCEL-OUTRTN-NO-PSN',
                            'ITH_QTY' => $newqty,
                            'ITH_REMARK' => $lotnum,
                            'ITH_LUPDT' => date('Y-m-d H:i:s'),
                            'ITH_USRID' => $request->userId
                        ];
                        ITH::insert($datab);
                        $myar[] = ['cd' => '1', 'msg' => 'OK'];
                    } else {
                        $myar[] = ['cd' => '1', 'msg' => 'OK.'];
                    }
                }
            } else {
                if (RETRM::where("RETRM_DOC", $idscan)->where("RETRM_ITMCD", $itemcd)) {
                    $myar[] = ['cd' => '1', 'msg' => 'ok'];
                } else {
                    $myar[] = ['cd' => '0', 'msg' => 'could not delete, try reopen the menu'];
                }
            }
        } else {
            $myar[] = ['cd' => '0', 'msg' => 'not ok'];
        }
        return ['status' => $myar, 'data' => $rs];
    }
}
