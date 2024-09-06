<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;

class ITHController extends Controller
{
    function getStockMultipleItem(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'part_code' => 'required|array',
            'warehouse' => 'required',
        ], [
            'part_code.required' => 'part_code param is required',
            'part_code.array' => 'array is required',
            'warehouse.required' => 'warehouse code is required'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->all(), 406);
        }

        $data = DB::table('XITRN_TBL')->whereIn('ITRN_ITMCD', $request->part_code)
            ->where('ITRN_LOCCD', $request->warehouse)
            ->select(DB::raw('UPPER(RTRIM(ITRN_ITMCD)) AS ITEMCODE'), DB::raw('SUM(IOQT) AS STOCK'))
            ->groupBy('ITRN_ITMCD')->get();
        return ['data' => $data, $request];
    }

    function getStockMultipleItemWMS(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'part_code' => 'required|array',
            'warehouse' => 'required',
        ], [
            'part_code.required' => 'part_code param is required',
            'part_code.array' => 'array is required',
            'warehouse.required' => 'warehouse code is required'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->all(), 406);
        }

        $data = DB::table('ITH_TBL')->whereIn('ITH_ITMCD', $request->part_code)
            ->where('ITH_WH', $request->warehouse)
            ->select(DB::raw('UPPER(RTRIM(ITH_ITMCD)) AS ITEMCODE'), DB::raw('SUM(ITH_QTY) AS STOCK'))
            ->groupBy('ITH_ITMCD')->get();
        return ['data' => $data];
    }

    function generateShortagePartReport(Request $request)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Shortage Part Report');
        $sheet->setCellValue([1, 2], 'Part Code');
        $sheet->mergeCells('A2:A3'); #rowspan3
        $sheet->setCellValue([2, 2], 'Part Name');
        $sheet->mergeCells('B2:B3'); #rowspan3
        $sheet->setCellValue([3, 2], 'Warehouse');
        $sheet->mergeCells('C2:C3'); #rowspan3
        $sheet->setCellValue([4, 2], 'ARWH0PD');
        $sheet->mergeCells('D2:D3'); #rowspan3
        $sheet->setCellValue([5, 2], 'QA');
        $sheet->mergeCells('E2:E3'); #rowspan3
        $sheet->setCellValue([6, 2], 'PSN');
        $sheet->mergeCells('F2:I2'); #rowspan3
        $sheet->setCellValue([6, 3], 'DOC');
        $sheet->setCellValue([7, 3], 'LOGICAL RETURN');
        $sheet->setCellValue([8, 3], 'DOC');
        $sheet->setCellValue([9, 3], 'Actual Counting (Not Confirmed)');

        $sheet->getStyle('A2:I3')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A2:I3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A2:I3')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('d4d4d4');

        $data = DB::table('ITH_TBL')
            ->leftJoin('MITM_TBL', 'ITH_ITMCD', '=', 'MITM_ITMCD')
            ->where('ITH_DATE', '<=', $request->date)
            ->whereIn('ITH_ITMCD', $request->rm)
            ->whereIn('ITH_WH', ['ARWH1', 'ARWH2', 'QA', 'ARWH0PD'])
            ->groupBy('ITH_ITMCD', 'MITM_SPTNO')
            ->orderBy('ITH_ITMCD')
            ->get([
                'ITH_ITMCD',
                DB::raw('RTRIM(MITM_SPTNO) SPTNO'),
                DB::raw("SUM(CASE WHEN ITH_WH IN ('ARWH1','ARWH2') THEN ITH_QTY END) ARWH"),
                DB::raw("SUM(CASE WHEN ITH_WH = 'ARWH0PD' THEN ITH_QTY END) ARWH0PD"),
                DB::raw("SUM(CASE WHEN ITH_WH = 'QA' THEN ITH_QTY END) QA"),
            ]);

        $supplyReqData = DB::table('SPL_TBL')
            ->whereIn('SPL_ITMCD', $request->rm)
            ->groupBy('SPL_ITMCD', 'SPL_DOC')
            ->select(
                'SPL_ITMCD',
                'SPL_DOC',
                DB::raw('SUM(SPL_QTYREQ) TREQ'),
                DB::raw('MIN(SPL_LUPDT) MINTIME_SUP'),
                DB::raw('DATEDIFF(DAY, MIN(SPL_LUPDT), GETDATE()) DAYPASS')
            );

        $supplyActData = DB::table('SPLSCN_TBL')
            ->whereIn('SPLSCN_ITMCD', $request->rm)
            ->groupBy('SPLSCN_ITMCD', 'SPLSCN_DOC')
            ->select(
                'SPLSCN_ITMCD',
                'SPLSCN_DOC',
                DB::raw('SUM(SPLSCN_QTY) TSCN'),
            );

        $returnActData = DB::table('RETSCN_TBL')
            ->whereIn('RETSCN_ITMCD', $request->rm)
            ->groupBy('RETSCN_ITMCD', 'RETSCN_SPLDOC')
            ->select(
                'RETSCN_ITMCD',
                'RETSCN_SPLDOC',
                DB::raw('SUM(RETSCN_QTYAFT) TRTN'),
            );

        $returnJustCountingData = DB::table('RETSCN_TBL')
            ->whereIn('RETSCN_ITMCD', $request->rm)
            ->whereNull('RETSCN_CNFRMDT')
            ->groupBy('RETSCN_ITMCD', 'RETSCN_SPLDOC')
            ->select(
                'RETSCN_ITMCD',
                'RETSCN_SPLDOC',
                DB::raw('SUM(RETSCN_QTYAFT) TRTN'),
            );

        $PSNData = DB::query()->fromSub($supplyReqData, 'VSPL')
            ->leftJoinSub($supplyActData, 'VSPLSCN', function ($join) {
                $join->on('SPL_DOC', '=', 'SPLSCN_DOC')
                    ->on('SPL_ITMCD', '=', 'SPLSCN_ITMCD');
            })->leftJoinSub($returnActData, 'VRTNSCN', function ($join) {
                $join->on('SPL_DOC', '=', 'RETSCN_SPLDOC')
                    ->on('SPL_ITMCD', '=', 'RETSCN_ITMCD');
            })
            ->whereRaw("ISNULL(TREQ, 0) < ISNULL(TSCN, 0)")
            ->whereNull('TRTN')
            ->where('DAYPASS', '<', 31)
            ->orderBy('SPL_DOC', 'desc')
            ->get([
                'VSPL.*',
                'TSCN',
                'TRTN',
                DB::raw('ISNULL(TSCN,0)-TREQ TLGCRTN')
            ]);

        $PSNData2 = DB::query()->fromSub($supplyReqData, 'VSPL')
            ->leftJoinSub($supplyActData, 'VSPLSCN', function ($join) {
                $join->on('SPL_DOC', '=', 'SPLSCN_DOC')
                    ->on('SPL_ITMCD', '=', 'SPLSCN_ITMCD');
            })->leftJoinSub($returnJustCountingData, 'VRTNSCN', function ($join) {
                $join->on('SPL_DOC', '=', 'RETSCN_SPLDOC')
                    ->on('SPL_ITMCD', '=', 'RETSCN_ITMCD');
            })
            ->whereRaw("ISNULL(TREQ, 0) < ISNULL(TSCN, 0)")
            ->whereNotNull('TRTN')
            ->where('DAYPASS', '<', 31)
            ->orderBy('SPL_DOC', 'desc')
            ->get([
                'VSPL.*',
                'TSCN',
                'TRTN',
            ]);

        #first sheet
        $PSNDocs = array_merge(
            $PSNData->unique('SPL_DOC')->pluck('SPL_DOC')->toArray(),
            $PSNData2->unique('SPL_DOC')->pluck('SPL_DOC')->toArray()
        );

        $PPSN1Data = DB::table('XPPSN1')->whereIn('PPSN1_PSNNO', $PSNDocs)
            ->groupBy('PPSN1_PSNNO', 'PPSN1_WONO')
            ->get([
                DB::raw('RTRIM(PPSN1_PSNNO) PPSN1_PSNNO'),
                DB::raw('RTRIM(PPSN1_WONO) PPSN1_WONO')
            ]);

        $rowAt = 4;
        $sheet->freezePane('A' . $rowAt);

        foreach ($data as $r) {
            $sheet->setCellValue([1, $rowAt], $r->ITH_ITMCD);
            $sheet->setCellValue([2, $rowAt], $r->SPTNO);
            $sheet->setCellValue([3, $rowAt], $r->ARWH);
            $sheet->setCellValue([4, $rowAt], $r->ARWH0PD);
            $sheet->setCellValue([5, $rowAt], $r->QA);
            foreach ($PSNData as $p) {
                if ($r->ITH_ITMCD == $p->SPL_ITMCD) {
                    $comments = '';
                    foreach ($PPSN1Data as $j) {
                        if ($p->SPL_DOC == $j->PPSN1_PSNNO) {
                            $comments .= $j->PPSN1_WONO . ", ";
                        }
                    }

                    $rowAt++;
                    if (!empty($comments)) {
                        $sheet->getComment([6, $rowAt])->getText()->createTextRun($comments);
                    }
                    $sheet->setCellValue([6, $rowAt], $p->SPL_DOC);
                    $sheet->setCellValue([7, $rowAt], $p->TLGCRTN);
                }
            }
            foreach ($PSNData2 as $p) {
                if ($r->ITH_ITMCD == $p->SPL_ITMCD) {
                    $comments = '';
                    foreach ($PPSN1Data as $j) {
                        if ($p->SPL_DOC == $j->PPSN1_PSNNO) {
                            $comments .= $j->PPSN1_WONO . ", ";
                        }
                    }

                    $rowAt++;
                    if (!empty($comments)) {
                        $sheet->getComment([8, $rowAt])->getText()->createTextRun($comments);
                    }
                    $sheet->setCellValue([8, $rowAt], $p->SPL_DOC);
                    $sheet->setCellValue([9, $rowAt], $p->TRTN);
                }
            }
            $rowAt++;
        }

        $sheet->getStyle('C4:C' . $rowAt)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('D4:D' . $rowAt)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('E4:E' . $rowAt)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('G4:G' . $rowAt)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('I4:I' . $rowAt)->getNumberFormat()->setFormatCode('#,##0');

        foreach (range('A', 'I') as $r) {
            $sheet->getColumnDimension($r)->setAutoSize(true);
        }

        $sheet->getStyle('A2:I' . $rowAt - 1)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new Color('1F1812'));

        $sheet->getPageSetup()
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);

        # second sheet

        $PSNData = json_decode(json_encode($PSNData), true);
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('raw');
        $sheet->fromArray($PSNData, 'A1');

        $stringjudul = "Shortage Part Report " . $request->date;
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $filename = $stringjudul;
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
    }
}
