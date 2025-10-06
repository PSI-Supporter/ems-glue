<?php

namespace App\Traits;

use App\Models\RawMaterialLabel;
use Illuminate\Support\Collection;
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
            'org_quantity' => $data['org_qty'] ?? NULL,
            'remark' => $data['remark'] ?? NULL,
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
                DB::raw("ISNULL(total_ship_qty,0) total_ship_qty"),
                DB::raw("ISNULL(total_ship_qty,0)-ISNULL(total_lbl_qty,0) total_bal_qty"),
            ]);

        $isAnyBalance = false;
        foreach ($join_data as $r) {
            if ($r->total_bal_qty > 0) {
                $isAnyBalance = true;
                break;
            }
        }

        if ($isAnyBalance) {
            $isDocRank = substr(strtoupper($data['doc']), -2) == '-I' ? true : false;
            if ($isDocRank) {
            } else {
                $anotherDoc = $isDocRank ? substr($data['doc'], 0, -2) : $data['doc'] . "-I";
                $lbl_data2 = DB::table('raw_material_labels')
                    ->leftJoin('MITMGRP_TBL', 'item_code', '=', 'MITMGRP_ITMCD_GRD')
                    ->whereNull('deleted_at')
                    ->where('doc_code', $anotherDoc)
                    ->where('MITMGRP_ITMCD', $data['item'])
                    ->groupByRaw("isnull(MITMGRP_ITMCD, item_code)")
                    ->select(
                        DB::raw("UPPER(isnull(MITMGRP_ITMCD, item_code)) item_code"),
                        DB::raw("SUM(org_quantity) lbl_qty")
                    )->get();

                foreach ($lbl_data2 as $r) {
                    foreach ($join_data as $b) {
                        if ($b->item_code == $r->item_code) {
                            $b->total_bal_qty = $b->total_ship_qty - $r->lbl_qty;
                            break;
                        }
                    }
                }
            }
        }

        return $join_data;
    }

    function progressLabeling($data = [])
    {
        $isDocContainRankItem = DB::table('raw_material_labels')
            ->join('MITMGRP_TBL', 'item_code', '=', 'MITMGRP_ITMCD_GRD')
            ->whereNull('deleted_at')
            ->whereNotNull('MITMGRP_ITMCD_GRD')
            ->where('doc_code', $data['doc'])
            ->groupBy('MITMGRP_ITMCD_GRD')
            ->select('MITMGRP_ITMCD_GRD')->count();

        $rcv_data = DB::table('receive_p_l_s')->whereNull('deleted_at')->where('delivery_doc', $data['doc'])
            ->leftJoin('MITMGRP_TBL', 'item_code', '=', 'MITMGRP_ITMCD_GRD')
            ->groupByRaw("isnull(MITMGRP_ITMCD, item_code)")
            ->select(
                DB::raw("isnull(MITMGRP_ITMCD, item_code) item_code"),
                DB::raw("SUM(ship_quantity) ship_qty")
            );

        $lbl_data = DB::table('raw_material_labels')
            ->leftJoin('MITMGRP_TBL', 'item_code', '=', 'MITMGRP_ITMCD_GRD')
            ->whereNull('deleted_at')->where('doc_code', $data['doc'])
            ->groupByRaw("isnull(MITMGRP_ITMCD, item_code)")
            ->select(
                DB::raw("isnull(MITMGRP_ITMCD, item_code) item_code"),
                DB::raw("SUM(org_quantity) lbl_qty")
            );

        $balance_data = DB::query()->fromSub($rcv_data, 'v1')->leftJoinSub($lbl_data, 'v2', 'v1.item_code', '=', 'v2.item_code')
            ->groupBy('v1.item_code')
            ->get([
                DB::raw('UPPER(v1.item_code) item_code'),
                DB::raw("sum(lbl_qty) lbl_qty_sum"),
                DB::raw("sum(ship_qty) ship_qty_sum"),
                DB::raw("round(sum(lbl_qty) / sum(ship_qty) * 100, 2) percentage")
            ]);

        $grandPercentage = new \stdClass();
        $grandPercentage->percentage = round($balance_data->sum("lbl_qty_sum") / $balance_data->sum("ship_qty_sum") * 100, 2);
        $grandPercentage->percentage = round($balance_data->sum("lbl_qty_sum") / $balance_data->sum("ship_qty_sum") * 100, 2);
        $grandPercentage->percentage = round($balance_data->sum("lbl_qty_sum") / $balance_data->sum("ship_qty_sum") * 100, 2);

        $firstRow = $grandPercentage;

        if ($grandPercentage->percentage < 100) {
            // apakah DO tersebut terkait Rank
            // ada 2 acuan 
            // 1. di belakang nomorDO mengandung -I
            // 2. di data mengandung item rank

            $isDocRank = substr(strtoupper($data['doc']), -2) == '-I' ? true : false;

            if ($isDocContainRankItem) {
            } else {
                $anotherDoc = $isDocRank ? substr($data['doc'], 0, -2) : $data['doc'] . "-I";
                $lbl_data2 = DB::table('raw_material_labels')
                    ->leftJoin('MITMGRP_TBL', 'item_code', '=', 'MITMGRP_ITMCD_GRD')
                    ->whereNull('deleted_at')
                    ->where('doc_code', $anotherDoc)
                    ->groupByRaw("isnull(MITMGRP_ITMCD, item_code)")
                    ->select(
                        DB::raw("UPPER(isnull(MITMGRP_ITMCD, item_code)) item_code"),
                        DB::raw("SUM(org_quantity) lbl_qty")
                    )->get();

                foreach ($lbl_data2 as $r) {
                    foreach ($balance_data as $b) {
                        if ($b->item_code == $r->item_code) {
                            $b->lbl_qty_sum += $r->lbl_qty;
                            break;
                        }
                    }
                }

                $grandPercentage = new \stdClass();
                $grandPercentage->percentage = round($balance_data->sum("lbl_qty_sum") / $balance_data->sum("ship_qty_sum") * 100, 2);
                $grandPercentage->percentage = round($balance_data->sum("lbl_qty_sum") / $balance_data->sum("ship_qty_sum") * 100, 2);
                $grandPercentage->percentage = round($balance_data->sum("lbl_qty_sum") / $balance_data->sum("ship_qty_sum") * 100, 2);

                $firstRow = $grandPercentage;
            }
        }
        return $firstRow;
    }

    function deleteLabel($data = [])
    {
        $AffectedRows = DB::table('raw_material_labels')->whereNull('deleted_at')
            ->where('code', $data['code'])
            ->update([
                'deleted_by' => $data['user_id'],
                'deleted_at' => date('Y-m-d H:i:s'),
            ]);
        return ['affected_rows' => $AffectedRows];
    }

    function getRelatedLabels($data)
    {
        $code = $data['code'];
        $finalData = DB::select("
        WITH RecursiveLabels
            AS (
                SELECT code
                    ,item_code
                    ,quantity
                    ,parent_code
                    ,splitted
                FROM raw_material_labels
                WHERE code = ?
                
                UNION ALL
                
                SELECT ml.code
                    ,ml.item_code
                    ,ml.quantity
                    ,ml.parent_code
                    ,ml.splitted
                FROM raw_material_labels ml
                INNER JOIN RecursiveLabels r ON ml.code = r.parent_code
                )
            SELECT code
                ,item_code
                ,quantity
                ,parent_code
                ,splitted
            FROM RecursiveLabels        
        ", [$code]);
        return $finalData;
    }
}
