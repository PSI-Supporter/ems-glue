<?php

namespace App\Http\Controllers;

use App\Models\InventoryPapper;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

class InventoryController extends Controller
{

    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
    }

    #load data to view
    function loadInventory(Request $request)
    {
        $searchValue = $request->inventory;
        $Inv = DB::table('WMS_Inv')
            ->select('cLoc', 'cAssyNo', 'cModel', 'cQty', DB::raw("COUNT(*) as BOX"), DB::raw("SUM(cQty) as Total"))
            ->groupBy('cLoc', 'cAssyNo', 'cModel', 'cQty')
            ->paginate(200);
        return ['data' => $Inv];
    }

    function formInventory(Request $request)
    {
        $searchValue = $request->inventory;
        $Inv = DB::table('WMS_Inv')
            ->select('cLoc', 'cAssyNo', 'cModel', 'cQty', DB::raw("COUNT(*) as BOX"), DB::raw("SUM(cQty) as Total"))
            ->groupBy('cLoc', 'cAssyNo', 'cModel', 'cQty')
            ->orderBy('cLoc', 'ASC')
            ->orderBy('cAssyNo', 'ASC')
            ->paginate(200);
        return view('inv_view', ['Inv' => $Inv]);
    }

    function exportInv()
    {
        $Warehouses = DB::table('WMS_Inv')->select('mstloc_grp')->groupBy('mstloc_grp')->get();

        # Hapus sebelum insert
        InventoryPapper::whereNull('deleted_at')->update(['deleted_at' => date('Y-m-d H:i:s')]);

        foreach ($Warehouses as $Warehouse) {

            $data = DB::table('WMS_Inv')
                ->select('cLoc', DB::raw("ISNULL(UPPER(SER_ITMID),MAX(cAssyNo)) as cAssyNo"), 'cModel', 'cQty', 'mstloc_grp', DB::raw("COUNT(*) as BOX"), DB::raw("SUM(cQty) as Total"))
                ->leftJoin('SER_TBL', 'RefNo', '=', 'SER_ID')
                ->where('mstloc_grp', $Warehouse->mstloc_grp)
                ->groupBy('SER_ITMID', 'cLoc', 'cModel', 'cQty', 'mstloc_grp')
                ->orderBy('cLoc', 'ASC')
                ->orderBy('SER_ITMID', 'ASC')
                ->orderBy('cQty', 'DESC')
                ->get();

            $data = json_decode(json_encode($data), true);


            //untuk insert ke db inventory_pappers
            $InsertData = [];
            foreach ($data as $r) {
                $InsertData[] = [
                    'created_at' => now(),
                    'updated_at' => NULL,
                    'item_code' => $r['cAssyNo'],
                    'item_qty' => $r['cQty'],
                    'item_box' => $r['BOX'],
                    'checker_id' => '-',
                    'auditor_id' => NULL,
                    'created_by' => '-',
                    'updated_by' => NULL,
                    'deleted_at' => NULL,
                    'deleted_by' => NULL,
                    'item_location' => $r['cLoc'],
                    'item_location_group' => $r['mstloc_grp'],
                    'nomor_urut' => NULL
                ];
            }

            $tempStr = '';
            $nomor = 0;

            foreach ($InsertData as &$rs) {
                $theNumber = -1;
                foreach ($InsertData as $_r) {
                    if ($rs['item_code'] === $_r['item_code']) {
                        if ($_r['nomor_urut']) {
                            $theNumber = $_r['nomor_urut'];
                            break;
                        }
                    }
                }
                if ($rs['item_code'] != $tempStr) {
                    $tempStr = $rs['item_code'];

                    if ($theNumber > 0) {
                        $rs['nomor_urut'] = $theNumber;
                    } else {
                        $nomor++;
                        $rs['nomor_urut'] =  $nomor;
                    }
                } else {
                    $rs['nomor_urut'] = $theNumber > 0 ? $theNumber : $nomor;
                }
            }
            unset($rs);

            foreach (array_chunk($InsertData, (1500 / 13) - 2) as $chunk) {
                InventoryPapper::insert($chunk);
            }
        }

        $this->Export();
    }

    function Export()
    {
        $spreadSheet = new Spreadsheet();
        $sheet = $spreadSheet->getActiveSheet();
        $Warehouses = DB::table('WMS_Inv')->select('mstloc_grp')->groupBy('mstloc_grp')->get();
        foreach ($Warehouses as $Warehouse) {
            $sheet = $spreadSheet->createSheet();
            $sheet->setTitle($Warehouse->mstloc_grp);
            $sheet->freezePane('A4');

            $WarehouseDataSummary = InventoryPapper::select(
                'nomor_urut',
                'item_code',
                'item_qty',
                DB::raw(
                    "SUM(item_box) item_box",
                ),
                DB::raw(
                    "MIN(item_location) item_location",
                ),
            )
                ->where('item_location_group', $Warehouse->mstloc_grp)
                ->whereNull('deleted_at')
                ->groupBy('nomor_urut', 'item_code', 'item_qty');

            $WarehouseData = DB::query()->fromSub($WarehouseDataSummary, 'v1')->selectRaw('v1.*,RTRIM(MITM_ITMD1) ITMD1')
                ->leftJoin('MITM_TBL', 'item_code', '=', 'MITM_ITMCD')
                ->orderBy('nomor_urut', 'ASC')
                ->orderBy('item_location', 'ASC')
                ->orderBy('item_code', 'ASC')
                ->orderBy('item_qty', 'DESC')->get();
            $WarehouseData = json_decode(json_encode($WarehouseData), true);

            $ItemLocation = InventoryPapper::select('item_code', 'item_location', DB::raw("COUNT(*) AS TTLROW"))
                ->where('item_location_group', $Warehouse->mstloc_grp)
                ->whereNull('deleted_at')
                ->groupBy('item_code', 'item_location')
                ->orderBy('item_location', 'ASC')
                ->get();
            $ItemLocation = json_decode(json_encode($ItemLocation), true);

            #Resume Location Per Item
            $ResumeItemLocation = [];
            foreach ($ItemLocation as $r) {
                $isFound = false;
                foreach ($ResumeItemLocation as &$l) {
                    if ($r['item_code'] === $l['item_code']) {
                        $l['COUNTER']++;
                        $isFound = true;
                        break;
                    }
                }
                unset($l);

                if (!$isFound) {
                    $ResumeItemLocation[] = [
                        'item_code' => $r['item_code'],
                        'COUNTER' => 1,
                    ];
                }
            }

            foreach ($ResumeItemLocation as $r) {
                if ($r['COUNTER'] > 1) {
                    $strLocation = '';
                    foreach ($ItemLocation as $l) {
                        if ($r['item_code'] === $l['item_code']) {
                            $strLocation .= $l['item_location'] . ',';
                        }
                    }

                    foreach ($WarehouseData as &$w) {
                        if ($r['item_code'] === $w['item_code']) {
                            $w['item_location'] = $strLocation;
                        }
                    }
                    unset($w);
                }
            }

            $sheet->setCellValue([1, 3], 'No');
            $sheet->setCellValue([2, 3], 'Loc.');
            $sheet->setCellValue([3, 3], 'Part Code');
            $sheet->setCellValue([4, 3], 'Part Name');
            $sheet->setCellValue([5, 3], 'QTY');
            $sheet->setCellValue([6, 3], 'BOX');
            $sheet->setCellValue([7, 3], 'TOTAL');
            $sheet->setCellValue([8, 3], 'Checked By');
            $sheet->setCellValue([9, 3], 'Auditor');

            usort($WarehouseData, function ($a, $b) {
                $retval = $a['nomor_urut'] <=> $b['nomor_urut'];
                if ($retval == 0) {
                    $retval = $b['item_qty'] <=> $a['item_qty'];
                }
                return $retval;
            });

            $rowAt = 4;
            $tempUrut = '';
            foreach ($WarehouseData as $r) {
                $displayUrut = '';
                $displayLocation = '';
                $displayItemCode = '';
                $displayItemName = '';
                if ($tempUrut != $r['nomor_urut']) {
                    $displayUrut = $r['nomor_urut'];
                    $displayLocation = $r['item_location'];
                    $displayItemCode = $r['item_code'];
                    $displayItemName = $r['ITMD1'];
                    $tempUrut = $r['nomor_urut'];
                } else {
                    $displayUrut = '';
                    $displayLocation = '';
                    $displayItemCode = '';
                    $displayItemName = '';
                }
                if ($rowAt > 4) {
                    if ($displayUrut) {
                        $minI = 0;
                        $maxI = $rowAt - 1;
                        for ($i = $rowAt; $i > 3; $i--) {
                            if ($sheet->getCell([1, $i])->getValue() != '') {
                                $minI = $i;
                                break;
                            }
                        }
                        $sheet->setCellValue([4, $rowAt], 'Total');
                        $sheet->setCellValue([6, $rowAt], "=SUM(F" . $maxI . ":F" . $minI . ")");
                        $sheet->setCellValue([7, $rowAt], "=SUM(G" . $maxI . ":G" . $minI . ")");
                        $rowAt += 1;
                    }
                }
                $sheet->setCellValue([1, $rowAt], $displayUrut);
                $sheet->setCellValue([2, $rowAt], $displayLocation);
                $sheet->setCellValue([3, $rowAt], $displayItemCode);
                $sheet->setCellValue([4, $rowAt], $displayItemName);
                $sheet->setCellValue([5, $rowAt], $r['item_qty']);
                $sheet->setCellValue([6, $rowAt], $r['item_box']);
                $sheet->setCellValue([7, $rowAt], $r['item_qty'] * $r['item_box']);
                $rowAt++;
            }

            $minI = 0;
            $maxI = $rowAt - 1;
            for ($i = $rowAt; $i > 3; $i--) {
                if ($sheet->getCell([1, $i])->getValue() != '') {
                    $minI = $i;
                    break;
                }
            }

            $sheet->setCellValue([4, $rowAt], 'Total');
            $sheet->setCellValue([6, $rowAt], "=SUM(F" . $maxI . ":F" . $minI . ")");
            $sheet->setCellValue([7, $rowAt], "=SUM(G" . $maxI . ":G" . $minI . ")");

            foreach (range('A', 'V') as $v) {
                $sheet->getColumnDimension($v)->setAutoSize(true);
            }
        }

        $Excel_writer = new Xls($spreadSheet);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="WMS_Inventory.xls"');
        header('Cache-Control: max-age=0');
        ob_end_clean();
        $Excel_writer->save('php://output');
    }

    function removeLine(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'pin' => 'required',
                'id' => [
                    Rule::exists('SER_TBL', 'SER_ID')
                ],
            ],
            [
                'pin.required' => ':attribute is required',
                'id.exists' => 'id (:input) is not registerd yet',
            ]
        );

        if ($validator->fails()) {
            return response()->json($validator->errors()->all(), 406);
        }

        if ($request->pin !== 'MTHSMTMTH') {
            return response()->json([['PIN is not valid']], 406);
        }

        logger($request->ip() . ": hapus " . $request->id);

        DB::table("WMS_Inv")->where("REFNO", $request->id)->delete();

        return [
            'message' => 'Go ahead',
            'data' => [
                'pin' => $request->pin,
                'id' => $request->id,
            ]
        ];
    }

    function accountingMutasiBarangJadiReport(Request $request)
    {
        // $SaldoAwal = DB::table('XFTRN_TBL')
        //     // ->where('FTRN_ITMCD', $request->item)
        //     ->where('FTRN_ISUDT', '<', $request->dateFrom)
        //     ->whereIn('FTRN_LOCCD', ['AFWH3', 'QAFG', 'AWIP1', 'AFWH3RT'])
        //     ->groupBy('FTRN_ITMCD', 'FTRN_LOCCD')
        //     ->get([DB::raw('RTRIM(FTRN_ITMCD) ITMCD'), DB::raw('SUM(IOQT) BEGINNINGQT'), 'FTRN_LOCCD']);

        // $SaldoAwalRawMaterial = DB::table('XITRN_TBL')
        //     // ->where('FTRN_ITMCD', $request->item)
        //     ->where('ITRN_ISUDT', '<', $request->dateFrom)
        //     ->whereIn('ITRN_LOCCD', ['PLANT1', 'PLANT2', 'ARWH0PD', 'ARWH2', 'ARWH1', 'QA'])
        //     ->groupBy('ITRN_ITMCD', 'ITRN_LOCCD')
        //     ->get([DB::raw('RTRIM(ITRN_ITMCD) ITMCD'), DB::raw('SUM(IOQT) BEGINNINGQT'), 'ITRN_LOCCD']);

        // $t = new DateTime($request->dateFrom);
        // $t->modify('-10 years');

        $tEOM = new DateTime($request->dateFrom);
        $tEOM->modify('-1 days');

        // $dateTenYearAgo = $t->format('Y-m-d');
        $dateEOMPreviousMonth = $tEOM->format('Y-m-d');

        // NEW

        $IGRN_TBL = DB::table('XIGRN_TBL')
            ->leftJoin('XPFGI_TBL', function ($join) {
                $join
                    ->on('PFGI_BSGRP', '=', 'IGRN_BSGRP')
                    ->on('PFGI_MDLCD', '=', 'IGRN_ITMCD')
                    ->on('PFGI_GRLNO', '=', 'IGRN_GRLNO');
            })
            ->leftJoin('XPGRN_TBL', function ($join) {
                $join
                    ->on('IGRN_BSGRP', '=', 'PGRN_BSGRP')
                    ->on('IGRN_ITMCD', '=', 'PGRN_ITMCD')
                    ->on('IGRN_GRLNO', '=', 'PGRN_GRLNO');
            })
            ->leftJoin('XMITM_V', 'MITM_ITMCD', '=', 'IGRN_ITMCD')
            // ->where('IGRN_ITMCD', $request->item)
            ->where('IGRN_PYEAR', $tEOM->format('Y'))
            ->where('IGRN_PMTH', $tEOM->format('m'))
            ->whereIn('IGRN_LOCCD', ['AFWH3', 'QAFG', 'AWIP1', 'AFWH3RT'])
            ->get(
                [
                    'IGRN_BSGRP',
                    'IGRN_GRLNO',
                    'IGRN_LOCCD',
                    DB::raw('RTRIM(IGRN_ITMCD) ITMCD'),
                    'IGRN_DATE',
                    DB::raw("'' MAKERPART"),
                    DB::raw("ISNULL(PFGI_SUPNO,'') SUPNO"),
                    DB::raw("ISNULL(PFGI_SUPCD,'') SUPCD"),
                    DB::raw("'USD' SUPCR"),
                    DB::raw("ISNULL(PFGI_ROKQT, ISNULL(PGRN_ROKQT, 0)) OKQT_FROM_FGI_OR_PGRN"),
                    DB::raw('IGRN_BALQT OKQT'),
                    DB::raw("1 FLAGAJA"),
                    DB::raw("ISNULL(PFGI_LOCPC, isnull(PGRN_PRPRC, 0)) LOCPCPRPRC_FROM_FGI_OR_PGRN"),
                    DB::raw("ISNULL(PFGI_LOCPC, isnull(PGRN_LOCPC, 0)) LOCPC_FROM_FGI_OR_PGRN"),
                    DB::raw("ROUND(ISNULL(PFGI_ASYCT,0) * ISNULL(PFGI_XRATE,0), 6) ROUND_ASYCTRATE"),
                    DB::raw("DATEDIFF(DAY, IGRN_DATE, '" . $dateEOMPreviousMonth . "') DATEDIFF_IGRN_AND_SELECTED")
                ]
            );

        $IGRN_TBL = json_decode(json_encode($IGRN_TBL), true);
        $HEADERS = [
            'IGRN_BSGRP',
            'IGRN_GRLNO',
            'IGRN_LOCCD',
            'ITMCD',
            'IGRN_DATE',
            "MAKERPART",
            "SUPNO",
            "SUPCD",
            "SUPCR",
            "OKQT_FROM_FGI_OR_PGRN",
            "OKQT",
            "FLAGAJA",
            "LOCPCPRPRC_FROM_FGI_OR_PGRN",
            "LOCPC_FROM_FGI_OR_PGRN",
            "ROUND_ASYCTRATE",
            "DATEDIFF_IGRN_AND_SELECTED"
        ];
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray($HEADERS, null, 'A1');
        $sheet->fromArray($IGRN_TBL, null, 'A2');

        $filename = "$request->dateFrom FG";

        $Excel_writer = new Xls($spreadsheet);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        ob_end_clean();
        $Excel_writer->save('php://output');
    }

    function accountingMutasiBahanBakuReport(Request $request)
    {
        // $t = new DateTime($request->dateFrom);
        // $t->modify('-10 years');

        $tEOM = new DateTime($request->dateFrom);
        $tEOM->modify('-1 days');

        // $dateTenYearAgo = $t->format('Y-m-d');
        $dateEOMPreviousMonth = $tEOM->format('Y-m-d');

        // NEW

        $IGRN_TBL = DB::table('XIGRN_TBL')
            ->leftJoin('XPGRN_TBL', function ($join) {
                $join
                    ->on('PGRN_BSGRP', '=', 'IGRN_BSGRP')
                    ->on('PGRN_ITMCD', '=', 'IGRN_ITMCD')
                    ->on('PGRN_GRLNO', '=', 'IGRN_GRLNO');
            })
            ->leftJoin('XMITM_V', 'MITM_ITMCD', '=', 'IGRN_ITMCD')
            // ->where('IGRN_ITMCD', $request->item)
            ->where('IGRN_PYEAR', $tEOM->format('Y'))
            ->where('IGRN_PMTH', $tEOM->format('m'))
            ->whereIn('IGRN_LOCCD', ['ARWH0PD', 'ARWH2', 'ARWH1', 'QA', 'PLANT1', 'PLANT2'])
            ->get(
                [
                    'IGRN_BSGRP',
                    'IGRN_GRLNO',
                    'IGRN_LOCCD',
                    DB::raw('RTRIM(IGRN_ITMCD) ITMCD'),
                    'IGRN_DATE',
                    DB::raw("ISNULL(PGRN_SPART,'') MAKERPART"),
                    DB::raw("ISNULL(PGRN_SUPNO,'') SUPNO"),
                    DB::raw("ISNULL(PGRN_SUPCD,'') SUPCD"),
                    DB::raw("'USD' SUPCR"),
                    DB::raw("ISNULL(PGRN_ROKQT, 0) OKQT_FROM_FGI_OR_PGRN"),
                    DB::raw('IGRN_BALQT OKQT'),
                    DB::raw('ISNULL(PGRN_XRATE,0) PGRN_XRATE'),
                    DB::raw("ISNULL(PGRN_LOCPC,0) LOCPC_FROM_FGI_OR_PGRN"),
                    DB::raw("DATEDIFF(DAY, IGRN_DATE, '" . $dateEOMPreviousMonth . "') DATEDIFF_IGRN_AND_SELECTED"),
                    DB::raw("CASE WHEN PGRN_XRATE = 1 THEN ROUND(IGRN_BALQT * (ISNULL(PGRN_PRPRC,0) + 0), 6)
				        ELSE ROUND(IGRN_BALQT * (ISNULL(PGRN_LOCPC,0) + 0), 6) END AS LBALAM")
                ]
            );
        $IGRN_TBL = json_decode(json_encode($IGRN_TBL), true);
        $HEADERS = [
            'IGRN_BSGRP',
            'IGRN_GRLNO',
            'IGRN_LOCCD',
            'ITMCD',
            'IGRN_DATE',
            "MAKERPART",
            "SUPNO",
            "SUPCD",
            "SUPCR",
            "OKQT_FROM_FGI_OR_PGRN",
            'OKQT',
            'PGRN_XRATE',
            "LOCPC_FROM_FGI_OR_PGRN",
            "DATEDIFF_IGRN_AND_SELECTED",
            "LBALAM"
        ];
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray($HEADERS, null, 'A1');
        $sheet->fromArray($IGRN_TBL, null, 'A2');

        $filename = "$request->dateFrom RM";

        $Excel_writer = new Xls($spreadsheet);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        ob_end_clean();
        $Excel_writer->save('php://output');
    }
}
