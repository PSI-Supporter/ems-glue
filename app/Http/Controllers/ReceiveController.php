<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReceiveController extends Controller
{
    function search(Request $request)
    {
        $columnMap = [
            'tr_pch_rcv_det.item_code',
            'item_name',
            'tr_pch_rcv_head.vendor_code',
        ];

        $data = DB::table('tr_pch_rcv_head')
            ->leftJoin('tr_pch_rcv_det', 'tr_pch_rcv_head.trans_no', '=', 'tr_pch_rcv_det.trans_no')
            ->leftJoin('ms_item', 'tr_pch_rcv_det.item_code', '=', 'ms_item.item_code')
            ->where('trans_date', '>=', $request->date0)
            ->where('trans_date', '<=', $request->date1)
            ->select(
                'tr_pch_rcv_head.trans_no',
                'trans_date',
                'location_to',
                'tr_pch_rcv_head.vendor_code',
                'delivery_no',
                'po_no',
                'tr_pch_rcv_det.item_code',
                'item_name',
                'item_group_code',
                'item_type_code',
                'rcv_qty',
                'unit_code',
                'curr_code',
                'net_price',
                DB::raw("net_price*rcv_qty AS amount"),
                'nopen',
                'custom_no',
                'custom_doc',
            )
            ->where($columnMap[$request->searchBy], 'like', '%' . $request->searchValue . '%')
            ->orderBy('trans_date')
            ->orderBy('tr_pch_rcv_head.trans_no')
            ->get();
        return ['data' => $data];
    }

    function synchronize_from_MEGAEMS()
    {
        ini_set('max_execution_time', '-1');
        $rsResume = [];
        $rsAPI = [];
        $sub1 = DB::table('RCV_TBL')->groupBy('RCV_BSGRP', 'RCV_DONO', 'RCV_INVNO')
            ->select('RCV_BSGRP', 'RCV_DONO', 'RCV_INVNO')
            ->where('RCV_QTY', '>', 0)
            ->whereNotNull('RCV_BSGRP');

        $sub2 = DB::table('XPGRN_VIEW')
            ->leftJoin('XPNGR', function ($join) {
                $join->on('PGRN_SUPNO', '=', 'PNGR_SUPNO')
                    ->on('PGRN_BSGRP', '=', 'PNGR_BSGRP');
            })
            ->groupBy(
                'PGRN_BSGRP',
                'PGRN_SUPNO',
                'PNGR_INVNO',

            )
            ->select('PGRN_BSGRP', 'PGRN_SUPNO', DB::raw("RTRIM(PNGR_INVNO) PNGR_INVNO"));

        $rs = DB::query()->fromSub($sub1, 'v1')
            ->leftJoinSub($sub2, 'v2', function ($join) {
                $join->on('RCV_BSGRP', '=', 'PGRN_BSGRP')
                    ->on('RCV_DONO', '=', 'PGRN_SUPNO');
            })
            ->whereRaw("isnull(RCV_INVNO,'')!=isnull(PNGR_INVNO,'')")
            ->whereNotNull('PGRN_SUPNO')
            ->whereNotNull('PNGR_INVNO')
            ->select(DB::raw("RTRIM(PGRN_SUPNO) PGRN_SUPNO"), 'PGRN_BSGRP', 'RCV_INVNO', 'PNGR_INVNO')
            ->get();

        $rs = json_decode(json_encode($rs), true);

        if ($rs) {
            logger('Trying to synchronize the invoice data');
            $rsResume = [];
            foreach ($rs as $r) {
                logger('Trying to synchronize the invoice data [' . $r['PGRN_SUPNO'] . ']');

                if (!in_array($r['PGRN_SUPNO'], $rsResume)) {
                    $rsResume[] = $r['PGRN_SUPNO'];
                }

                DB::table("RCV_TBL")->where('RCV_DONO', $r['PGRN_SUPNO'])
                    ->where('RCV_BSGRP', $r['PGRN_BSGRP'])
                    ->update(['RCV_INVNO' => $r['PNGR_INVNO']]);
            }

            $fields = [
                'data' => $rsResume
            ];
            $fields_string = http_build_query($fields);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'http://192.168.0.29:8080/api-report-custom/api/stock/incomingPabeanByDOArray');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3600);
            $data = curl_exec($ch);
            curl_close($ch);
            $rsAPI = json_decode($data);
        } else {
            logger('synchronize invoice data is done, but no data will be synchronized');
        }

        $sub1 = DB::table("RCV_TBL")
            ->where('RCV_QTY', '>', 0)
            ->whereNotNull('RCV_BSGRP')
            ->groupBy('RCV_BSGRP', 'RCV_DONO', 'RCV_ITMCD', 'RCV_PRPRC', 'RCV_WH', 'RCV_GRLNO', 'RCV_INVNO')
            ->select('RCV_BSGRP', 'RCV_DONO', 'RCV_ITMCD', 'RCV_PRPRC', 'RCV_WH', 'RCV_GRLNO', 'RCV_INVNO');

        $sub2 = DB::table('XPGRN_VIEW')->leftJoin('XPNGR', function ($join) {
            $join->on('PGRN_SUPNO', '=', 'PNGR_SUPNO')->on('PGRN_BSGRP', '=', 'PNGR_BSGRP');
        })->groupBy(
            'PGRN_BSGRP',
            'PGRN_SUPNO',
            'PGRN_ITMCD',
            'PGRN_PRPRC',
            'PGRN_LOCCD',
            'PGRN_GRLNO',
            'PNGR_INVNO',
            'PGRN_ROKQT',
            'PGRN_AMT',
            'PGRN_SUPCD',
            'PGRN_RCVDT',
            'PGRN_PONO',
            'PGRN_SUPCR'
        )
            ->select(
                'PGRN_BSGRP',
                'PGRN_SUPNO',
                'PGRN_ITMCD',
                'PGRN_PRPRC',
                DB::raw("RTRIM(PGRN_LOCCD) PGRN_LOCCD"),
                'PGRN_GRLNO',
                DB::raw("SUM(PGRN_ROKQT) PGRN_ROKQT"),
                DB::raw("SUM(PGRN_AMT) PGRN_AMT"),
                'PGRN_SUPCD',
                'PGRN_RCVDT',
                'PGRN_PONO',
                'PGRN_SUPCR',
                'PNGR_INVNO'
            );
        $rs = DB::query()->fromSub($sub1, 'v1')->leftJoinSub($sub2, 'v2', function ($join) {
            $join->on('RCV_BSGRP', '=', 'PGRN_BSGRP')
                ->on('RCV_ITMCD', '=', 'PGRN_ITMCD')
                ->on('RCV_DONO', '=', 'PGRN_SUPNO')
                ->on('RCV_WH', '=', 'PGRN_LOCCD')
                ->on('RCV_GRLNO', '=', 'PGRN_GRLNO');
        })->select(
            DB::raw('RTRIM(PGRN_LOCCD) PGRN_LOCCD'),
            DB::raw('RTRIM(PGRN_ITMCD) PGRN_ITMCD'),
            DB::raw('RTRIM(PGRN_SUPCR) PGRN_SUPCR'),
            DB::raw('RTRIM(PGRN_SUPCD) PGRN_SUPCD'),
            'PGRN_RCVDT',
            DB::raw('RTRIM(PGRN_PONO) PGRN_PONO'),
            'PGRN_PRPRC',
            'PGRN_ROKQT',
            DB::raw('RTRIM(PGRN_GRLNO) PGRN_GRLNO'),
            'PGRN_AMT',
            DB::raw('RTRIM(PGRN_SUPNO) PGRN_SUPNO'),
            'PGRN_BSGRP',
            'RCV_INVNO',
            'PNGR_INVNO'
        )->whereRaw('RCV_PRPRC != PGRN_PRPRC')
            ->get();
        $rs = json_decode(json_encode($rs), true);

        logger('Trying to synchronize the invoice data');

        $rsResume = [];
        if ($rs) {
            logger('Trying to synchronize the price data');
            $rsResume = [];
            foreach ($rs as $r) {
                if (!in_array($r['PGRN_SUPNO'], $rsResume)) {
                    $rsResume[] = $r['PGRN_SUPNO'];
                }
            }
            foreach ($rsResume as $b) {
                $citem = [];
                $cpo = [];
                $cgrlno = [];
                $cqty = [];
                $cprice = [];
                $camt = [];
                $cinvoice = [];
                foreach ($rs as $i) {
                    if ($b == $i['PGRN_SUPNO']) {
                        $cpo[] = $i['PGRN_PONO'];
                        $cgrlno[] = $i['PGRN_GRLNO'];
                        $cqty[] = $i['PGRN_ROKQT'];
                        $cprice[] = $i['PGRN_PRPRC'];
                        $camt[] = $i['PGRN_AMT'];
                        $citem[] = $i['PGRN_ITMCD'];
                        $cinvoice[] = $i['PNGR_INVNO'];
                    }
                }
                $ttlar = count($cpo);
                for ($i = 0; $i < $ttlar; $i++) {
                    $dataw = [
                        'RCV_PO' => $cpo[$i],
                        'RCV_DONO' => $b,
                        'RCV_ITMCD' => $citem[$i],
                        'RCV_GRLNO' => $cgrlno[$i]
                    ];
                    if (DB::table('RCV_TBL')->where($dataw)->count() > 0) {
                        DB::table('RCV_TBL')
                            ->where('RCV_PO', $cpo[$i])
                            ->where('RCV_DONO', $b)
                            ->where('RCV_ITMCD', $citem[$i])
                            ->where('RCV_GRLNO', $cgrlno[$i])
                            ->update([
                                'RCV_QTY' => $cqty[$i],
                                'RCV_PRPRC' => $cprice[$i],
                                'RCV_AMT' => $camt[$i],
                                'RCV_INVNO' => $cinvoice[$i],
                            ]);
                    }
                }
            }
            $fields = [
                'data' => $rsResume
            ];
            $fields_string = http_build_query($fields);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'http://192.168.0.29:8080/api-report-custom/api/stock/incomingPabeanByDOArray');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3600);
            $data = curl_exec($ch);
            curl_close($ch);
            $rsAPI = json_decode($data);
        } else {
            logger('Trying to synchronize but no data will be synchronized');
        }
        return [
            'datas' => $rs,
            'dataDO' => $rsResume, 'dataAPI' => $rsAPI
        ];
    }

    function getReportFGNGCustomer(Request $request)
    {
        $data = DB::table("RCV_TBL")->leftJoin("MITM_TBL", "RCV_ITMCD", '=', "MITM_ITMCD")
            ->whereYear("RCV_BCDATE",  date('Y'))
            ->groupBy("MITM_ITMCD", "MITM_ITMD1", "RCV_INVNO", "RCV_BCDATE")
            ->select(
                DB::raw("RTRIM(MITM_ITMCD) MITM_ITMCD"),
                DB::raw("RTRIM(MITM_ITMD1) MITM_ITMD1"),
                DB::raw("RTRIM(RCV_INVNO) RCV_INVNO"),
                "RCV_BCDATE",
                DB::raw("SUM(RCV_QTY) RQT")
            )
            ->orderBy("RCV_BCDATE")->get();
        return ['data' => $data];
    }

    function downloadTemplateUpload()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue([1, 1], 'DO');
        $sheet->setCellValue([2, 1], 'ITEMCODE');
        $sheet->setCellValue([3, 1], 'QTY');
        $sheet->setCellValue([4, 1], 'HSCODE');
        $sheet->setCellValue([5, 1], 'BM');
        $sheet->setCellValue([6, 1], 'PPN');
        $sheet->setCellValue([7, 1], 'PPH');
        $sheet->setCellValue([8, 1], 'NOMOR_URUT');
        $sheet->setCellValue([9, 1], 'NET_WEIGHT_PER_ITEM');
        foreach (range('A', 'I') as $r) {
            $sheet->getColumnDimension($r)->setAutoSize(true);
        }
        $sheet->freezePane('A2');
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $filename = "TMPL_RECEIVING";
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
    }
}
