<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ItemTracerController extends Controller
{

    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
    }

    function getOustandingScan(Request $request)
    {
        $WOOutput = DB::table('WMS_CLS_JOB')->where('CLS_PSNNO', $request->PSNDoc)
            ->sum('CLS_QTY');

        $data = DB::select("exec WMS_sp_get_sum_CLS ?, ?, 'OK'", [$request->PSNDoc, $WOOutput + $request->qty]);

        $items = [];
        foreach ($data as $r) {
            if (!in_array($r->Item_code, $items)) {
                $items[] = $r->Item_code;
            }
        }

        $DBItems = DB::table('MITM_TBL')->whereIn("MITM_ITMCD", $items)
            ->get([
                DB::raw(
                    'RTRIM(MITM_ITMCD) ITMCD',
                ),
                DB::raw(
                    'RTRIM(MITM_ITMD1) ITMD1',
                ),
                DB::raw(
                    'RTRIM(MITM_SPTNO) SPTNO',
                ),
                DB::raw(
                    'RTRIM(MITM_STKUOM) UOM',
                ),
            ]);

        foreach ($data as &$r) {
            foreach ($DBItems as $d) {
                if ($r->Item_code == $d->ITMCD) {
                    $r->ITMD1  = $d->ITMD1;
                    $r->SPTNO  = $d->SPTNO;
                    $r->UOM  = $d->UOM;
                    break;
                }
            }
        }
        unset($r);

        return ['data' => $data];
    }

    function getReportTraceLot(Request $request)
    {
        $data = $this->getReportTraceLotData(['dateFrom' => $request->dateFrom, 'dateTo' => $request->dateTo]);
        return ['data' => $data['data']];
    }

    function getReportTraceLotData($filter)
    {
        $data0 = DB::table('WMS_SWMP_HIS')->whereDate('SWMP_LUPDT', '>=', $filter['dateFrom'])
            ->whereDate('SWMP_LUPDT', '<=', $filter['dateTo'])
            ->select(
                DB::raw("RTRIM(SWMP_WONO) ENG_WO"),
                DB::raw("RTRIM(SWMP_PROCD) PROCD"),
                DB::raw("RTRIM(SWMP_LINENO) LINENOM"),
                DB::raw("RTRIM(SWMP_MCMCZITM) MCZ"),
                DB::raw("'' OLD_ITEM_CODE"),
                DB::raw("'' OLD_LOT_CODE"),
                DB::raw("0 OLD_QTY"),
                DB::raw("'' OLD_UNIQUE"),
                DB::raw("RTRIM(SWMP_ITMCD) NEW_ITEM_CODE"),
                DB::raw("RTRIM(SWMP_LOTNO) NEW_LOT_CODE"),
                DB::raw("SWMP_QTY NEW_QTY"),
                DB::raw("RTRIM(SWMP_UNQ) NEW_UNIQUE"),
                DB::raw("SWMP_LUPDT DATE_AT"),
                DB::raw("RTRIM(SWMP_LUPBY) NIK"),
                DB::raw("RTRIM(SWMP_REMARK) REMARK"),
                DB::raw("RTRIM(SWMP_JOBNO) JOB"),
            );
        $data1 = DB::table('WMS_SWPS_HIS')->whereDate('SWPS_LUPDT', '>=', $filter['dateFrom'])
            ->whereDate('SWPS_LUPDT', '<=', $filter['dateTo'])
            ->union($data0)
            ->select(
                DB::raw("RTRIM(SWPS_WONO) ENG_WO"),
                DB::raw("RTRIM(SWPS_PROCD) PROCD"),
                DB::raw("RTRIM(SWPS_LINENO) LINENOM"),
                DB::raw("RTRIM(SWPS_MCMCZITM) MCZ"),
                DB::raw("RTRIM(SWPS_ITMCD) OLD_ITEM_CODE"),
                DB::raw("RTRIM(SWPS_LOTNO) OLD_LOT_CODE"),
                DB::raw("QTY OLD_QTY"),
                DB::raw("RTRIM(SWPS_UNQ) OLD_UNIQUE"),
                DB::raw("RTRIM(SWPS_NITMCD) NEW_ITEM_CODE"),
                DB::raw("RTRIM(SWPS_NLOTNO) NEW_LOT_CODE"),
                DB::raw("NQTY NEW_QTY"),
                DB::raw("RTRIM(SWPS_NUNQ) NEW_UNIQUE"),
                DB::raw("SWPS_LUPDT DATE_AT"),
                DB::raw("RTRIM(SWPS_LUPBY) NIK"),
                DB::raw("RTRIM(SWPS_REMARK) REMARK"),
                DB::raw("RTRIM(SWPS_JOBNO) JOB"),
            );
        $data = DB::query()->fromSub($data1, "VX")
            ->orderBy('DATE_AT')
            ->orderby('ENG_WO')->get();
        return ['data' => $data];
    }

    function getReportTraceLotAsSpreadsheet(Request $request)
    {
        $data = $this->getReportTraceLotData(['dateFrom' => $request->dateFrom, 'dateTo' => $request->dateTo]);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();


        $sheet->setCellValue('B1', 'TRACE LOT');
        $sheet->getStyle('B1')->applyFromArray([
            'font' => [
                'bold' => true
            ]
        ]);
        $sheet->getStyle('A8:Q8')->applyFromArray([
            'font' => [
                'bold' => true
            ]
        ]);
        $sheet->getStyle('A8:Q8')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('fce803');

        $sheet->getStyle('B1')->getFont()->setSize(24);
        $sheet->getStyle('B1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B1')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('B1:E2', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->setCellValue('B3', 'MODEL Name');
        $sheet->setCellValue('B4', 'MODEL Assy CODE');
        $sheet->setCellValue('B5', 'WO Number');

        $sheet->setCellValue('A8', 'No.');
        $sheet->setCellValue('B8', 'WO Number');
        $sheet->setCellValue('C8', 'Process');
        $sheet->setCellValue('D8', 'Line');
        $sheet->setCellValue('E8', 'MCZ');
        $sheet->setCellValue('F8', 'Old Item Code');
        $sheet->setCellValue('G8', 'Old Lot No');
        $sheet->setCellValue('H8', 'Old QTY');
        $sheet->setCellValue('I8', 'Old Unique');
        $sheet->setCellValue('J8', 'New Item Code');
        $sheet->setCellValue('K8', 'New Lot No');
        $sheet->setCellValue('L8', 'New QTY');
        $sheet->setCellValue('M8', 'New Unique');
        $sheet->setCellValue('N8', 'Date');
        $sheet->setCellValue('O8', 'NIK');
        $sheet->setCellValue('P8', 'REMARK');
        $sheet->setCellValue('Q8', 'JOB');

        $rowAt = 9;
        $orderNumber = 1;
        foreach ($data['data'] as $r) {
            $sheet->setCellValue('A' . $rowAt, $orderNumber);
            $sheet->setCellValue('B' . $rowAt, $r->ENG_WO);
            $sheet->setCellValue('C' . $rowAt, $r->PROCD);
            $sheet->setCellValue('D' . $rowAt, $r->LINENOM);
            $sheet->setCellValue('E' . $rowAt, $r->MCZ);
            $sheet->setCellValue('F' . $rowAt, $r->OLD_ITEM_CODE);
            $sheet->setCellValue('G' . $rowAt, $r->OLD_LOT_CODE);
            $sheet->setCellValue('H' . $rowAt, $r->OLD_QTY);
            $sheet->setCellValue('I' . $rowAt, $r->OLD_UNIQUE);
            $sheet->setCellValue('J' . $rowAt, $r->NEW_ITEM_CODE);
            $sheet->setCellValue('K' . $rowAt, $r->NEW_LOT_CODE);
            $sheet->setCellValue('L' . $rowAt, $r->NEW_QTY);
            $sheet->setCellValue('M' . $rowAt, $r->NEW_UNIQUE);
            $sheet->setCellValue('N' . $rowAt, $r->DATE_AT);
            $sheet->setCellValue('O' . $rowAt, $r->NIK);
            $sheet->setCellValue('P' . $rowAt, $r->REMARK);
            $sheet->setCellValue('Q' . $rowAt, $r->JOB);
            $rowAt++;
            $orderNumber++;
        }

        foreach (range('A', 'Q') as $r) {
            $sheet->getColumnDimension($r)->setAutoSize(true);
        }

        $sheet->freezePane('A9');

        $sheet->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);


        $stringjudul = "Trace Lot Report from " . $request->dateFrom . " to " . $request->dateTo;

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $filename = $stringjudul;
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
    }

    function getOutstandingScanDetail(Request $request)
    {
        $data = DB::table('SPLSCN_TBL')
            ->leftJoin('MITM_TBL', 'SPLSCN_ITMCD', '=', 'MITM_ITMCD')
            ->leftJoin('raw_material_labels', 'SPLSCN_UNQCODE', '=', 'code')
            ->where('SPLSCN_DOC', $request->doc)
            ->where('SPLSCN_ITMCD', $request->item_code)
            ->whereNull('splitted')
            ->get([
                'SPLSCN_DOC',
                'SPLSCN_UNQCODE',
                'SPLSCN_ITMCD',
                'SPLSCN_LOTNO',
                'SPLSCN_QTY',
                DB::raw("RTRIM(MITM_SPTNO) SPTNO"),
                'SPLSCN_PROCD',
                'SPLSCN_MC',
                'SPLSCN_ORDERNO',
                'splitted'
            ]);

        $PRDData1 = DB::table('WMS_SWPS_HIS')->where('SWPS_PSNNO', $request->doc)
            ->where('SWPS_ITMCD', $request->item_code)
            ->select(DB::raw('RTRIM(SWPS_ITMCD) NEW_ITEM_CODE'), DB::raw("RTRIM(SWPS_UNQ) NEW_UNIQUE"));

        $PRDData = DB::table('WMS_SWMP_HIS')
            ->where('SWMP_PSNNO', $request->doc)
            ->where('SWMP_ITMCD', $request->item_code)
            ->union($PRDData1)
            ->select([DB::raw('RTRIM(SWMP_ITMCD) NEW_ITEM_CODE'), DB::raw("RTRIM(SWMP_UNQ) NEW_UNIQUE")]);

        $PRDAct = DB::query()->fromSub($PRDData, 'VX')->get();

        foreach ($data as &$r) {
            $r->isUsed = false;
            foreach ($PRDAct as $a) {
                if ($r->SPLSCN_UNQCODE == $a->NEW_UNIQUE) {
                    $r->isUsed = true;
                }
            }
        }
        unset($r);

        return [
            'data' => $PRDAct,
            'dataPSN' => $data->filter(function ($item) {
                return $item->isUsed == false;
            })->values()
        ];
    }

    function adjustDetail(Request $request)
    {

        $lastRowPS = DB::table('WMS_SWPS_HIS')
            ->where('SWPS_PSNNO', $request->doc)
            ->where('SWPS_PROCD', $request->procd)
            ->where('SWPS_ITMCD', $request->item_code)
            ->whereRaw('ISNULL(SWPS_BAL,0)!=1')
            ->orderBy('SWPS_LUPDT', 'desc')
            ->first();
        $message = 'Adjusted successfully';

        $wo = NULL;
        $line_code = NULL;
        $mc_mcz_itm = NULL;
        $old_item_code = NULL;
        $old_lot_code = NULL;
        $old_qty = 0;
        $old_unique_key = NULL;
        $old_job_code = NULL;
        $old_spid = NULL;
        $old_mc = NULL;
        $old_mcz = NULL;
        $old_model_code = NULL;
        $old_bom_rev = NULL;
        $old_main_item_code = NULL;

        try {
            DB::beginTransaction();

            if ($lastRowPS->SWPS_ITMCD ?? 0 != 0) {
                $wo = $lastRowPS->SWPS_WONO;
                $line_code = $lastRowPS->SWPS_LINENO;
                $mc_mcz_itm = $lastRowPS->SWPS_MCMCZITM;
                $old_item_code = $lastRowPS->SWPS_NITMCD;
                $old_lot_code = $lastRowPS->SWPS_NLOTNO;
                $old_qty = $lastRowPS->NQTY;
                $old_unique_key = $lastRowPS->SWPS_NUNQ;
                $old_job_code = $lastRowPS->SWPS_JOBNO;
                $old_spid = $lastRowPS->SWPS_SPID;
                $old_mc = $lastRowPS->SWPS_MC;
                $old_mcz = $lastRowPS->SWPS_MCZ;
                $old_model_code = $lastRowPS->SWPS_MDLCD;
                $old_bom_rev = $lastRowPS->SWPS_BOMRV;
                $old_main_item_code = $lastRowPS->SWPS_MAINITMCD;

                DB::table('WMS_SWPS_HIS')
                    ->where('SWPS_WONO', $lastRowPS->SWPS_WONO)
                    ->where('SWPS_MCMCZITM', $lastRowPS->SWPS_MCMCZITM)
                    ->where('SWPS_NLOTNO', $lastRowPS->SWPS_NLOTNO)
                    ->where('SWPS_NUNQ', $lastRowPS->SWPS_NUNQ)
                    ->where('SWPS_SPID', $lastRowPS->SWPS_SPID)
                    ->where('SWPS_NITMCD', $lastRowPS->SWPS_NITMCD)
                    ->update(['SWPS_BAL' => 1]);
            } else {
                $lastRowMP = DB::table('WMS_SWMP_HIS')
                    ->where('SWMP_PSNNO', $request->doc)
                    ->where('SWMP_PROCD', $request->procd)
                    ->where('SWMP_ITMCD', $request->item_code)
                    ->whereRaw('ISNULL(SWMP_BAL,0) !=1')
                    ->orderBy('SWMP_LUPDT', 'desc')
                    ->first();

                if (!$lastRowMP) {
                    return ['message' => 'there is no previous supplies'];
                }

                $wo = $lastRowMP->SWMP_WONO;
                $line_code = $lastRowMP->SWMP_LINENO;
                $mc_mcz_itm = $lastRowMP->SWMP_MCMCZITM;                
                $old_item_code = $lastRowMP->SWMP_ITMCD;
                $old_lot_code = $lastRowMP->SWMP_LOTNO;
                $old_qty = $lastRowMP->SWMP_QTY;
                $old_unique_key = $lastRowMP->SWMP_UNQ;
                $old_job_code = $lastRowMP->SWMP_JOBNO;
                $old_spid = $lastRowMP->SWMP_SPID;
                $old_mc = $lastRowMP->SWMP_MC;
                $old_mcz = $lastRowMP->SWMP_MCZ;
                $old_model_code = $lastRowMP->SWMP_MDLCD;
                $old_bom_rev = $lastRowMP->SWMP_BOMRV;
                $old_main_item_code = $lastRowMP->SWMP_MAINITMCD;

                DB::table('WMS_SWMP_HIS')
                    ->where('SWMP_WONO', $lastRowMP->SWMP_WONO)
                    ->where('SWMP_MCMCZITM', $lastRowMP->SWMP_MCMCZITM)
                    ->where('SWMP_LOTNO', $lastRowMP->SWMP_LOTNO)
                    ->where('SWMP_UNQ', $lastRowMP->SWMP_UNQ)
                    ->where('SWMP_SPID', $lastRowMP->SWMP_SPID)
                    ->where('SWMP_ITMCD', $lastRowMP->SWMP_ITMCD)
                    ->update(['SWMP_BAL' => 1]);
            }

            $data = [
                'SWPS_WONO' => $wo,
                'SWPS_PROCD' => $request->procd,
                'SWPS_LINENO' => $line_code,
                'SWPS_MCMCZITM' => $mc_mcz_itm,
                'SWPS_ITMCD' => $old_item_code,
                'SWPS_LOTNO' => $old_lot_code,
                'SWPS_NITMCD' => $request->item_code,
                'SWPS_NLOTNO' => $request->lot_code,
                'SWPS_REMQT' => NULL,
                'SWPS_LUPDT' => date('Y-m-d H:i:s'),
                'SWPS_LUPBY' => $request->user_id,
                'SWPS_REMARK' => 'OK',
                'QTY' => $old_qty,
                'NQTY' => $request->item_qty,
                'SWPS_UNQ' => $old_unique_key,
                'SWPS_NUNQ' => $request->unique_code,
                'SWPS_PSNNO' => $request->doc,
                'SWPS_JOBNO' => $old_job_code,
                'SWPS_SPID' => $old_spid,
                'SWPS_MC' => $old_mc,
                'SWPS_MCZ' => $old_mcz,
                'SWPS_MDLCD' => $old_model_code,
                'SWPS_BOMRV' => $old_bom_rev,
                'SWPS_MAINITMCD' => $old_main_item_code,
                'SWPS_JUDGE' => 'ADJ',
                'SWPS_BAL' => 0
            ];
            DB::table('WMS_SWPS_HIS')->insert($data);
            DB::commit();
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage() . " on line " . $e->getLine()], 406);
        }

        return ['message' => $message, $request->all()];
    }
}
