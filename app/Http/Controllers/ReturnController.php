<?php

namespace App\Http\Controllers;

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
}
