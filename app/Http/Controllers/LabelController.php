<?php

namespace App\Http\Controllers;

use App\Models\C3LC;
use App\Traits\LabelingTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LabelController extends Controller
{
    use LabelingTrait;

    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
    }

    function combineRMLabel(Request $request)
    {
        $currdate = date('YmdHis');
        $myar = [];
        $currrtime = date('Y-m-d H:i:s');

        $citm = $request->item;
        $clot = $request->lotNumber;
        $cqty_com = $request->qty;
        $unique_com = $request->oldUniqueKey;
        $cuser = $request->userId;

        $printdata = [];
        if (is_array($citm)) {
            $ttldata = count($citm);
            $C3Data = [];
            $newqty = 0;
            $lotasHome = $clot[0];
            $greatestQty = 0;

            for ($i = 0; $i < $ttldata; $i++) {
                $newqty += $cqty_com[$i];

                if ($greatestQty < $cqty_com[$i]) {
                    $greatestQty = $cqty_com[$i];
                    $lotasHome = substr($clot[0], 0, 23);
                }
            }
            $lotasHome .= '$C';

            #PREPARE NEW ROW ID
            $newid = "CM" . $currdate; #combine manual
            #END

            for ($i = 0; $i < $ttldata; $i++) {
                // is already splited or combined
                $rowsCount = DB::table('raw_material_labels')
                    ->where(function ($query) use ($unique_com, $i) {
                        $query->where('code', $unique_com[$i])->where('splitted', 1);
                    })->orWhere(function ($query) use ($unique_com, $i) {
                        $query->where('code', $unique_com[$i])->where('combined', 1);
                    })->count();

                if ($rowsCount > 0) {
                    $myar[] = ['cd' => '0', 'msg' => $unique_com[$i] . ' Already splitted or combined'];
                    return ['status' => $myar];
                }
            }

            try {
                DB::beginTransaction();
                $Response = $this->generateLabelId([
                    'machineName' => $request->machineName ?? 'DF',
                    'documentCode' => 'COMBINE-' . $newid,
                    'itemCode' => $citm[0],
                    'qty' => $newqty,
                    'lotNumber' => $lotasHome,
                    'userID' => $request->userId,
                    'composed' => 1
                ]);

                for ($i = 0; $i < $ttldata; $i++) {
                    $C3Data[] = [
                        'C3LC_ITMCD' => $citm[0],
                        'C3LC_NLOTNO' => $lotasHome,
                        'C3LC_NQTY' => $newqty,
                        'C3LC_LOTNO' => $clot[$i],
                        'C3LC_QTY' => $cqty_com[$i],
                        'C3LC_REFF' => $newid,
                        'C3LC_LINE' => $i,
                        'C3LC_USRID' => $cuser,
                        'C3LC_LUPTD' => $currrtime,
                        'C3LC_COMID' => $unique_com[$i],
                        'C3LC_NEWID' => $Response['data'],
                    ];

                    if ($unique_com[$i]) {
                        DB::table('raw_material_labels')->where('code', $unique_com[$i])->update(['combined' => 1]);
                    }
                }

                $rack = DB::table('ITMLOC_TBL')
                    ->select('ITMLOC_LOC')
                    ->where('ITMLOC_ITM', $citm[0])->first();

                C3LC::insert($C3Data);

                $printdata[] = ['NEWQTY' => $newqty, 'NEWLOT' => $lotasHome, 'SER_ID' => $Response['data'], 'rackCode' => $rack->ITMLOC_LOC];
                $myar[] = ['cd' => '1', 'msg' => 'Saved successfully'];

                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                $myar[] = ['cd' => '0', 'msg' => $e->getMessage() . " on line " . $e->getLine()];
                return ['status' => $myar, 'data' => $printdata];
            }
        } else {
            $myar[] = ['cd' => '0', 'msg' => 'It seems You are using wrong menu or function'];
        }
        return ['status' => $myar, 'data' => $printdata];
    }

    function getRawMaterialLabelsHelper(Request $request)
    {
        $data = DB::table('raw_material_labels')
            ->leftJoin('MITM_TBL', 'item_code', '=', 'MITM_ITMCD')
            ->leftJoin('ITMLOC_TBL', 'item_code', '=', 'ITMLOC_ITM')
            ->where('parent_code', '=', $request->code)
            ->groupBy(
                'code',
                'doc_code',
                'item_code',
                'MITM_SPTNO',
                "MITM_ITMD1",
                'quantity',
                'lot_code',
                'created_by',
            )
            ->get([
                'code',
                'doc_code',
                'item_code',
                DB::raw("RTRIM(MITM_SPTNO) SPTNO"),
                DB::raw("RTRIM(MITM_ITMD1) ITMD1"),
                DB::raw('CONVERT(INT,quantity) quantity'),
                'lot_code',
                'created_by',
                DB::raw("MAX(ITMLOC_LOC) LOC")
            ]);
        $distinctDoc = $data->unique('created_by')->values()->pluck('created_by');
        $userDB = DB::table('VNPSI_USERS')->whereIn('ID', $distinctDoc)->get(['ID', 'user_nicename']);

        foreach ($data as &$r) {
            foreach ($userDB as $u) {
                if ($r->created_by == $u->ID) {
                    $userName = explode(' ', $u->user_nicename);
                    $r->user_nicename = $userName[0];
                    break;
                }
            }
        }
        return ['data' => $data, 'message' => 'OK'];
    }
}
