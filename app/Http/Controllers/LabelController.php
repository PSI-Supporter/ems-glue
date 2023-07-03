<?php

namespace App\Http\Controllers;

use App\Models\C3LC;
use Illuminate\Http\Request;

class LabelController extends Controller
{
    function combineRMLabel(Request $request)
    {
        $currdate = date('YmdHis');
        $myar = [];
        $currrtime = date('Y-m-d H:i:s');

        $citm = $request->item;
        $clot = $request->lotNumber;
        $cqty_com = $request->qty;
        $cuser = $request->userId;
        if (is_array($citm)) {
            $ttldata = count($citm);
            $lot_distinc = array_values(array_unique($clot));
            $C3Data = [];
            $newqty = 0;
            $lotasHome = $clot[0];
            if (count($lot_distinc) > 1) {
                $lotasHome = substr($clot[0], 0, 10);
                $lotasHome .= '$C';
            }
            for ($i = 0; $i < $ttldata; $i++) {
                $newqty += $cqty_com[$i];
            }
            #PREPARE NEW ROW ID
            $newid = "CM" . $currdate; #combine manual
            #END
            for ($i = 0; $i < $ttldata; $i++) {
                $C3Data[] = [
                    'C3LC_ITMCD' => $citm[0], 'C3LC_NLOTNO' => $lotasHome, 'C3LC_NQTY' => $newqty, 'C3LC_LOTNO' => $clot[$i], 'C3LC_QTY' => $cqty_com[$i], 'C3LC_REFF' => $newid, 'C3LC_LINE' => $i, 'C3LC_USRID' => $cuser, 'C3LC_LUPTD' => $currrtime,
                ];
            }
            C3LC::insert($C3Data);
            $printdata[] = ['NEWQTY' => $newqty, 'NEWLOT' => $lotasHome];
            $myar[] = ['cd' => '1', 'msg' => 'Saved successfully'];
        } else {
            $myar[] = ['cd' => '0', 'msg' => 'It seems You are using wrong menu or function'];
        }
        return ['status' => $myar, 'data' => $printdata];
    }
}
