<?php

namespace App\Http\Controllers;

use App\Models\C3LC;
use App\Models\RawMaterialLabelPrint;
use App\Models\ValueCheckingHistory;
use App\Traits\LabelingTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

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
        $itemValue = $request->itemValue;
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
            $valueasHome = '';

            for ($i = 0; $i < $ttldata; $i++) {
                $newqty += $cqty_com[$i];

                if ($greatestQty < $cqty_com[$i]) {
                    $greatestQty = $cqty_com[$i];
                    $lotasHome = substr($clot[0], 0, 23);
                    $valueasHome = $itemValue[$i] ?? '';
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

            // pastikan beda PSN cegah
            $countPSN = DB::table('V_SPLSCN_TBLC')->whereIn('SPLSCN_UNQCODE', $unique_com)
                ->groupBy('SPLSCN_DOC')
                ->select('SPLSCN_DOC')
                ->get()
                ->count();

            if ($countPSN > 1) {
                $myar[] = ['cd' => '0', 'msg' => 'Combine Label should be in one PSN'];
                return ['status' => $myar];
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
                    'composed' => 1,
                    'item_value' => $valueasHome
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
                        'C3LC_DOC' => $request->doc ?? NULL,
                        'C3LC_VALUE' => $itemValue[$i] ?? '',
                    ];

                    if ($unique_com[$i]) {
                        DB::table('raw_material_labels')->where('code', $unique_com[$i])->update(['combined' => 1]);
                    }
                }

                $rack = DB::table('ITMLOC_TBL')
                    ->leftJoin('MITM_TBL', 'ITMLOC_ITM', '=', 'MITM_ITMCD')
                    ->select('ITMLOC_LOC', DB::raw("RTRIM(MITM_SPTNO) SPTNO"))
                    ->where('ITMLOC_ITM', $citm[0])->first();

                C3LC::insert($C3Data);

                $printdata[] = [
                    'NEWQTY' => $newqty,
                    'NEWLOT' => $lotasHome,
                    'NEWVALUE' => $valueasHome,
                    'SER_ID' => $Response['data'],
                    'rackCode' => $rack->ITMLOC_LOC ?? '',
                    'itemName' => $rack->SPTNO ?? ''
                ];
                $myar[] = ['cd' => '1', 'msg' => 'Saved successfully'];

                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                $myar[] = ['cd' => '0', 'msg' => $e->getMessage() . " on line " . $e->getLine()];
                return ['status' => $myar, 'data' => $printdata];
            }
        } else {
            $myar[] = ['cd' => '0', 'msg' => 'It seems You are using wrong menu or function', $request->all()];
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
                'item_value'
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
                DB::raw("MAX(ITMLOC_LOC) LOC"),
                'item_value'
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

    function getReportJoinReels(Request $request)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A5', 'No');
        $sheet->setCellValue('B5', 'Date');
        $sheet->setCellValue('C5', 'Part Code');
        $sheet->setCellValue('D5', 'Part Name');
        $sheet->setCellValue('E5', 'Reel');

        $sheet->mergeCells('A5:A7', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('B5:B7', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('C5:C7', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('D5:D7', $sheet::MERGE_CELL_CONTENT_HIDE);


        $data = DB::table('C3LC_TBL')->leftJoin('MITM_TBL', 'C3LC_ITMCD', '=', 'MITM_ITMCD')
            ->whereDate('C3LC_LUPTD', '>=', $request->dateFrom)
            ->whereDate('C3LC_LUPTD', '<=', $request->dateTo)
            ->where('C3LC_REFF', 'like', 'CM%')
            ->orderBy('C3LC_LUPTD')
            ->orderBy('C3LC_LINE')
            ->get(['C3LC_TBL.*', DB::raw('RTRIM(MITM_SPTNO) SPTNO'), DB::raw('CONVERT(DATE, C3LC_LUPTD) CONVERT_DATE')]);

        $rowAt = 7;
        $tempGroup = '-';
        $columnAt = 5;
        $orderNumber = 0;
        $maxColumn = 3;

        foreach ($data as $r) {
            if ($tempGroup != $r->C3LC_REFF) {
                $tempGroup = $r->C3LC_REFF;
                $orderNumber++;
                $rowAt++;
                $columnAt = 5;

                $sheet->setCellValue([1, $rowAt], $orderNumber);
                $sheet->setCellValue([2, $rowAt], $r->CONVERT_DATE);
                $sheet->setCellValue([3, $rowAt], $r->C3LC_ITMCD);
                $sheet->setCellValue([4, $rowAt], $r->SPTNO);
            } else {
                $columnAt += 3;
                if ($maxColumn < $columnAt) {
                    $maxColumn = $columnAt;
                }
            }
            $sheet->setCellValue([$columnAt, $rowAt], '3N2');
            $sheet->setCellValue([$columnAt, $rowAt], $r->C3LC_QTY);
            $sheet->setCellValue([$columnAt + 1, $rowAt], $r->C3LC_LOTNO);
            $sheet->setCellValue([$columnAt + 2, $rowAt], (string)$r->C3LC_NEWID);
            $sheet->getCell([$columnAt + 2, $rowAt])->getStyle()->getNumberFormat()->setFormatCode('@');
        }

        $reelAt = 1;
        for ($i = 5; $i <= $maxColumn; $i += 3) {
            $sheet->setCellValue([$i, 6], $reelAt);
            $sheet->setCellValue([$i, 7], 'Qty');
            $sheet->setCellValue([$i + 1, 7], 'Lot Number');
            $sheet->setCellValue([$i + 2, 7], 'Unique Key');

            $sheet->mergeCells([$i, 6, $i + 2, 6], $sheet::MERGE_CELL_CONTENT_HIDE);
            $reelAt++;
        }

        for ($r = 8; $r <= $rowAt; $r++) {
            $_formula = '=';
            $_formula2 = '';
            for ($i = 5; $i <= $maxColumn; $i += 3) {
                $_formula .= $sheet->getCell([$i, $r])->getColumn() . $r . '+';
                $_formula2 .= $sheet->getCell([$i, $r])->getColumn() . $r . ',';
            }
            $_formula = substr($_formula, 0, strlen($_formula) - 1);
            $_formula2 = substr($_formula2, 0, strlen($_formula2) - 1);
            $sheet->setCellValue([$maxColumn + 3, $r], $_formula);
            $sheet->setCellValue([$maxColumn + 4, $r], "=count(" . $_formula2 . ")-1");
        }
        $sheet->setCellValue([$maxColumn + 3, 5], 'Total Qty');
        $sheet->setCellValue([$maxColumn + 4, 5], 'Jumlah Joint');

        $sheet->mergeCells([5, 5, ($maxColumn + 2), 5], $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells([$maxColumn + 3, 5, ($maxColumn + 3), 7], $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells([$maxColumn + 4, 5, ($maxColumn + 4), 7], $sheet::MERGE_CELL_CONTENT_HIDE);

        $sheet->getStyle([1, 5, ($maxColumn + 4), 7])->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle([1, 5, ($maxColumn + 4), 7])->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle([1, 5, ($maxColumn + 4), 7])->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('d3d3d3');

        $sheet->freezePane('C8');

        $sheet->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);

        $stringjudul = "Join Reels Report from " . $request->dateFrom . " to " . $request->dateTo;

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $filename = $stringjudul;
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
    }

    function getLabel(Request $request)
    {
        $data = DB::table('raw_material_labels')->where('code', $request->id)->first();
        return ['data' => $data];
    }

    function getActiveLabel(Request $request)
    {
        $data = $this->getPrintableLabel(['uniqueList' => $request->codes]);

        return ['data' => $data];
    }

    function logAction(Request $request)
    {
        $tobePrinted = DB::table('raw_material_labels')->where('code', $request->code)
            ->first();
        logger($request->all());
        if ($tobePrinted->code) {
            RawMaterialLabelPrint::create([
                'code' => $tobePrinted->code,
                'item_code' => $tobePrinted->item_code,
                'doc_code' => $tobePrinted->doc_code,
                'parent_code' => $tobePrinted->parent_code,
                'quantity' => $tobePrinted->quantity,
                'lot_code' => $tobePrinted->lot_code,
                'action' => $request->action,
                'created_by' => $request->user_id,
                'pc_name' => $request->machineName,
            ]);
        }
    }

    function updateLabelValue(Request $request)
    {
        $affectedRow = in_array($request->measurementStatus, ['O', 'T']) ?
            DB::table('raw_material_labels')->where('code', $request->code)
            ->update([
                'item_value' => $request->itemValue,
                'updated_by' => $request->userId,
                'updated_at' => date('Y-m-d H:i:s')
            ]) : 0;

        $tobeLogged = DB::table('raw_material_labels')
            ->where('code', $request->code)->first();

        if ($tobeLogged) {
            ValueCheckingHistory::create([
                'code' => $tobeLogged->code,
                'item_code' => $tobeLogged->item_code,
                'doc_code' => $tobeLogged->doc_code,
                'quantity' => $tobeLogged->quantity,
                'lot_code' => $tobeLogged->lot_code,
                'item_value' => $request->itemValue,
                'checking_status' => $request->measurementStatus,
                'created_by' => $request->userId,
                'client_ip' => $request->ip(),
            ]);
        }

        $message = $affectedRow ? 'Updated successfully' : 'Nothing updated';
        return ['message' => $message, $request->all()];
    }

    function reportValueChecking(Request $request)
    {
        $data = DB::table('value_checking_histories')
            ->leftJoin('MSTEMP_TBL', 'created_by', '=', 'MSTEMP_ID')
            ->leftJoin('MITM_TBL', 'item_code', '=', 'MITM_ITMCD')
            ->whereDate('created_at', '>=', $request->dateFrom)
            ->whereDate('created_at', '<=', $request->dateTo)
            ->orderBy('value_checking_histories.created_at')
            ->get([
                'value_checking_histories.created_at',
                DB::raw("CONCAT(MSTEMP_FNM, ' ', MSTEMP_LNM) FULL_NAME"),
                'code',
                'item_code',
                DB::raw('RTRIM(MITM_SPTNO) SPTNO'),
                DB::raw('RTRIM(MITM_ITMD1) ITMD1'),
                'doc_code',
                'quantity',
                'lot_code',
                'item_value',
                'checking_status',
            ]);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();


        $sheet->setCellValue('A2', 'Time');
        $sheet->setCellValue('B2', 'User');
        $sheet->setCellValue('C2', 'ID');
        $sheet->setCellValue('D2', 'Part Code');
        $sheet->setCellValue('E2', 'Part Name');
        $sheet->setCellValue('F2', 'Part Description');
        $sheet->setCellValue('G2', 'Document');
        $sheet->setCellValue('H2', 'Quantity');
        $sheet->setCellValue('I2', 'Lot Code');
        $sheet->setCellValue('J2', 'Part Value');
        $sheet->setCellValue('K2', 'Status');

        $rowAt = 3;
        foreach ($data as $r) {
            $sheet->setCellValue('A' . $rowAt, $r->created_at);
            $sheet->setCellValue('B' . $rowAt, $r->FULL_NAME);
            $sheet->setCellValue('C' . $rowAt, "'" . $r->code);
            $sheet->setCellValue('D' . $rowAt, $r->item_code);
            $sheet->setCellValue('E' . $rowAt, $r->SPTNO);
            $sheet->setCellValue('F' . $rowAt, $r->ITMD1);
            $sheet->setCellValue('G' . $rowAt, $r->doc_code);
            $sheet->setCellValue('H' . $rowAt, $r->quantity);
            $sheet->setCellValue('I' . $rowAt, $r->lot_code);
            $sheet->setCellValue('J' . $rowAt, $r->item_value);
            $sheet->setCellValue('K' . $rowAt, $r->checking_status);
            $rowAt++;
        }

        $sheet->freezePane('A3');

        foreach (range('A', 'K') as $r) {
            $sheet->getColumnDimension($r)->setAutoSize(true);
        }

        $sheet->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);

        $stringjudul = "Value Checking from " . $request->dateFrom . " to " . $request->dateTo;

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $filename = $stringjudul;
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
    }

    function registerLabel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'doc' => 'required',
        ], [
            'doc.required' => ':attribute is required',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 406);
        }
        $NewlyId = [];

        for ($i = 0; $i < $request->print_qty; $i++) {
            $_data = [
                'machineName' => $request->machineName ?? 'DF',
                'documentCode' => $request->doc,
                'itemCode' => $request->item_code,
                'qty' => $request->qty,
                'lotNumber' => $request->lot_number,
                'userID' => $request->user_id,
                'parent_code' => $request->uniqueBefore,
                'item_value' => $request->itemValue ?? '',
                'pallet' => $request->pallet ?? '',
                'org_qty' => $request->qty ?? NULL,
            ];
            $Response = $this->generateLabelId($_data);
            $NewlyId[] = $Response['data'];
        }

        $data = $this->getPrintableLabel(['uniqueList' => $NewlyId]);

        $balanceData = $this->balancingPerPallet(['doc' => $request->doc, 'item' => $request->item_code]);

        $dataProgress = $this->progressLabeling(['doc' => $request->doc]);

        return ['data' => $data, 'balance_data' => $balanceData, 'progress' => round($dataProgress->percentage ?? 0, 2)];
    }

    function registerLabelWithoutReference(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'doc' => 'required',
        ], [
            'doc.required' => ':attribute is required',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 406);
        }

        if (strlen(trim($request->doc)) <= 2) {
            return response()->json(['message' => 'DO Number is not valid'], 400);
        }

        $itemMaster = DB::table('MITM_TBL')->where('MITM_ITMCD', $request->item_code)->get(['MITM_ITMCD', 'MITM_SPTNO']);

        if ($itemMaster->isEmpty()) {
            return response()->json(['message' => 'Item is not found'], 400);
        }

        $NewlyId = [];

        for ($i = 0; $i < $request->print_qty; $i++) {
            $_data = [
                'machineName' => $request->machineName ?? 'DF',
                'documentCode' => $request->doc,
                'itemCode' => $request->item_code,
                'qty' => $request->qty,
                'lotNumber' => $request->lot_number,
                'userID' => $request->user_id,
                'item_value' => $request->itemValue ?? '',
                'org_qty' => $request->qty ?? NULL,
                'remark' => 'emergency',
            ];
            $Response = $this->generateLabelId($_data);
            $NewlyId[] = $Response['data'];
        }

        $data = $this->getPrintableLabel(['uniqueList' => $NewlyId]);

        return ['data' => $data];
    }

    function getPrintableLabel($params)
    {
        $data = DB::table('raw_material_labels')
            ->leftJoin('MITM_TBL', 'item_code', '=', 'MITM_ITMCD')
            ->leftJoin('ITMLOC_TBL', 'MITM_ITMCD', '=', 'ITMLOC_ITM')
            ->whereIn('code', $params['uniqueList'])
            ->orderBy('created_at')
            ->get([
                'code',
                DB::raw('RTRIM(MITM_ITMCD) ITMCD'),
                DB::raw('RTRIM(MITM_SPTNO) SPTNO'),
                DB::raw('CONVERT(INT,quantity) quantity'),
                DB::raw('RTRIM(ITMLOC_LOC) LOC'),
                'item_value',
            ]);

        return $data;
    }

    function delete(Request $request)
    {
        $params = [
            'code' => $request->code,
            'user_id' => $request->user_id
        ];

        $respons = $this->deleteLabel($params);

        $message = $respons['affected_rows'] > 0 ? 'Deleted successfully' : 'there is no deleted row';

        return [
            'message' => $message,
            'affected_rows' => $respons['affected_rows']
        ];
    }

    function logCompare(Request $request)
    {
        $affectedRow = DB::table('recheck_c3_s')->insert([
            'code' => $request->code,
            'compare_poin' => $request->poin,
            'compare_value_1' => $request->value_1,
            'compare_value_2' => $request->value_2,
            'compare_status' => $request->value_1 == $request->value_1 ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $request->user_id,
            'client_ip' => $request->ip()
        ]);

        return ['message' => $affectedRow ? 'OK' : 'could not log'];
    }
}
