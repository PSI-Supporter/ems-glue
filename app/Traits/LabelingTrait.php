<?php

namespace App\Traits;

use App\Models\RawMaterialLabel;
use Illuminate\Support\Facades\DB;

trait LabelingTrait
{

    function generateLabelId($data = [])
    {
        sleep(0.9);
        $LabelDate = date('Y-m-d');
        $LabelTime = date('H:i:s');
        $LabelCreator = '3'; # from SMT
        $LabelMachineName = substr('00' . $data['machineName'], -2);

        $Labels = RawMaterialLabel::where('created_at', $LabelDate . ' ' . $LabelTime)
            ->where(DB::raw("SUBSTRING(code,7,1)"), $LabelCreator)
            ->count();

        $orderChar = [
            1 => '1',
            2 => '2',
            3 => '3',
            4 => '4',
            5 => '5',
            6 => '6',
            7 => '7',
            8 => '8',
            9 => '9',
            10 => 'A',
            11 => 'B',
            12 => 'C',
            13 => 'D',
            14 => 'E',
            15 => 'F',
            16 => 'G',
            17 => 'H',
            18 => 'I',
            19 => 'J',
            20 => 'K',
            21 => 'L',
            22 => 'M',
            23 => 'N',
            24 => 'O',
            25 => 'P',
            26 => 'Q',
            27 => 'R',
            28 => 'S',
            29 => 'T',
            30 => 'U',
            31 => 'V',
            32 => 'W',
            33 => 'X',
            34 => 'Y',
            35 => 'Z',
        ];

        $NewID = substr(str_replace('-', '', $LabelDate), -6) . $LabelCreator . $LabelMachineName . str_replace(':', '', $LabelTime) . $orderChar[++$Labels];

        RawMaterialLabel::insert([
            'code' => $NewID,
            'doc_code' => $data['documentCode'],
            'item_code' => $data['itemCode'],
            'quantity' => $data['qty'],
            'lot_code' => $data['lotNumber'],
            'created_by' => $data['userID'],
            'created_at' => $LabelDate . ' ' . $LabelTime,
            'composed' => $data['composed'] ?? NULL,
            'parent_code' => $data['parent_code'] ?? NULL,
            'item_value' => $data['item_value'] ?? NULL,
            'pallet' => $data['pallet'] ?? NULL,
        ]);


        return ['data' => $NewID, 'created_at' => $LabelDate . ' ' . $LabelTime];
    }

    function balancingPerPallet($data = [])
    {
        $rcv_data = DB::table('receive_p_l_s')->whereNull('deleted_at')
            ->where('delivery_doc',  $data['doc'])
            ->where('item_code',  $data['item'])
            ->groupBy('item_code', 'pallet')
            ->select('item_code', 'pallet', DB::raw("SUM(ship_quantity) total_ship_qty"));

        $ser_data = DB::table('raw_material_labels')
            ->whereNull('deleted_at')
            ->where('doc_code', $data['doc'])
            ->where('item_code',  $data['item'])
            ->groupBy('item_code', 'pallet')
            ->select('item_code', 'pallet', DB::raw("SUM(quantity) total_lbl_qty"));

        $join_data = DB::query()->fromSub($rcv_data, 'v1')
            ->leftJoinSub($ser_data, 'v2', function ($join) {
                $join->on('v1.item_code', '=', 'v2.item_code')
                    ->on(DB::raw("isnull(v1.pallet,'')"), '=', DB::raw("isnull(v2.pallet,'')"));
            })->get([
                "v1.item_code",
                "v1.pallet",
                DB::raw("ISNULL(total_ship_qty,0)-ISNULL(total_lbl_qty,0) total_bal_qty"),
            ]);

        return $join_data;
    }

    function progressLabeling($data = [])
    {
        $rcv_data = DB::table('receive_p_l_s')->whereNull('deleted_at')->where('delivery_doc', $data['doc'])
            ->groupBy('delivery_doc')
            ->select('delivery_doc', DB::raw("SUM(ship_quantity) ship_qty"));

        $lbl_data = DB::table('raw_material_labels')->whereNull('deleted_at')->where('doc_code', $data['doc'])
            ->groupBy('doc_code')
            ->select('doc_code', DB::raw("SUM(quantity) lbl_qty"));

        $balance_data = DB::query()->fromSub($rcv_data, 'v1')->leftJoinSub($lbl_data, 'v2', 'v1.delivery_doc', '=', 'doc_code')
            ->get(['v1.*', DB::raw("round(lbl_qty / ship_qty * 100, 2) percentage")]);

        return $balance_data->first();
    }
}
