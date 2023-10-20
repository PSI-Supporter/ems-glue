<?php

namespace App\Http\Controllers;

use App\Models\InventoryPapper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style;
use PhpOffice\PhpSpreadsheet\Style\Border;

class InventoryController extends Controller
{
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

    public function ExportExcel($data_inv)
    {
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '4000M');
        try {
            $spreadSheet = new Spreadsheet();
            $spreadSheet->getActiveSheet()->getDefaultColumnDimension()->setWidth(20);
            $spreadSheet->getActiveSheet()->fromArray($data_inv);
            $Excel_writer = new Xls($spreadSheet);
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="WMS_Inventory.xls"');
            header('Cache-Control: max-age=0');
            ob_end_clean();
            $Excel_writer->save('php://output');
            exit();
        } catch (Exception $e) {
            return;
        }
    }
    /**
     *This function loads the customer data from the database then converts it
     * into an Array that will be exported to Excel
     */

    function exportInv()
    {
        $Warehouses = DB::table('WMS_Inv')->select('mstloc_grp')->groupBy('mstloc_grp')->get();

        $spreadSheet = new Spreadsheet();
        $sheet = $spreadSheet->getActiveSheet();
        
        foreach ($Warehouses as $Warehouse) {

            $data = DB::table('WMS_Inv')
                ->select('cLoc',DB::raw("SER_ITMID as cAssyNo"), 'cModel', 'cQty', 'mstloc_grp', DB::raw("COUNT(*) as BOX"), DB::raw("SUM(cQty) as Total"))
                ->leftJoin('SER_TBL', 'RefNo', '=', 'SER_ID')
                ->where('mstloc_grp', $Warehouse->mstloc_grp)
                ->groupBy('SER_ITMID', 'cLoc', 'cModel', 'cQty', 'mstloc_grp')
                ->orderBy('cLoc', 'ASC')
                ->orderBy('SER_ITMID', 'ASC')
                ->orderBy('cQty', 'DESC')
                ->get();

            $data = json_decode(json_encode($data), true);
            $data_array = array();
            $locBefore = NULL;
            $cdBefore = NULL;
            $totalBox = 0;
            $totalQty = 0;
            $firstDifferent = NULL;
            $i = 0;
            $TotData = count($data);

            //untuk ekspor ke excel-------->>>

            foreach ($data as $data_item) {

                if ($locBefore != $data_item['cAssyNo']) {
                    if ($firstDifferent) {
                        $data_array[] = array(
                            'No' => NULL,
                            'Loc' => NULL,
                            'Part Code' => NULL,
                            'Part Name' => NULL,
                            'QTY' => 'Total',
                            'BOX' => $totalBox,
                            'Total' => $totalQty
                        );
                    } else {
                        $firstDifferent = true;
                    }

                    $totalQty = $data_item['Total'];
                    $totalBox = $data_item['BOX'];
                    $locBefore = $data_item['cAssyNo'];
                    $fixLoc = $data_item['cLoc'];
                } else {
                    $fixLoc = NULL;
                    $totalQty += $data_item['Total'];
                    $totalBox += $data_item['BOX'];
                }

                if ($cdBefore != $data_item['cAssyNo']) {
                    $cdBefore = $data_item['cAssyNo'];
                    $fixCd = $data_item['cAssyNo'];
                } else {
                    $fixCd = NULL;
                }

                $data_array[] = array(

                    'No' => NULL,
                    'Loc' => $fixLoc,
                    'Part Code' => $fixCd,
                    'Part Name' => $data_item['cModel'],
                    'QTY' => $data_item['cQty'],
                    'BOX' => $data_item['BOX'],
                    'Total' => $data_item['Total']
                );
                $i++;
                if ($i == $TotData) {
                    $data_array[] = array(
                        'No' => NULL,
                        'Loc' => NULL,
                        'Part Code' => NULL,
                        'Part Name' => NULL,
                        'QTY' => 'Total',
                        'BOX' => $totalBox,
                        'Total' => $totalQty
                    );
                }
            }
            foreach ($data_array as &$rs) {
                $rs['Loc'] = '';
            }
            unset($rs);
            foreach ($data_array as &$rr) {
                $locStr = '';
                $locArray = [];

                foreach ($data as $lok) {
                    if ($rr['Part Code'] == $lok['cAssyNo']) {
                        $locStr .= $lok['cLoc'] . ',';
                        if (!in_array($lok['cLoc'], $locArray)) {
                            $locArray[] = $lok['cLoc'];
                        }
                    }
                }
                $rr['Loc'] = implode(',', $locArray);
            }
            $nobf = '';
            $noArray = '';
            foreach ($data_array as &$n) {
                if ($n['Loc'] != $nobf) {
                    $noArray++;
                    $n['No'] = $noArray;
                } else {
                    $n['No'] = NULL;
                }
            }
            unset($rr);

            //untuk insert ke db inventory_pappers
            $InsertData = [];
            $satu = NULL;
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
                    'item_location_group' => $r['mstloc_grp']
                ];
            }

            $tempStr = '';
            $nomor = 0;

            foreach ($InsertData as &$rs) {
                if ($rs['item_code'] != $tempStr) {
                    $tempStr = $rs['item_code'];
                    $nomor++;
                    $rs['nomor_urut'] = $nomor;
                } else {
                    $rs['nomor_urut'] = $nomor;
                }
            }
            unset($rs);

            foreach (array_chunk($InsertData, (1500 / 13) - 2) as $chunk) {
                InventoryPapper::insert($chunk);
            }

            array_unshift($data_array, array("No", "Loc", "Part Code", "Part Name", "QTY", "BOX", "Total"));

            $sheet = $spreadSheet->createSheet();
            $sheet->setTitle($Warehouse->mstloc_grp);
            $sheet->getDefaultColumnDimension()->setWidth(20);
            $sheet->fromArray($data_array);
        }

        $Excel_writer = new Xls($spreadSheet);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="WMS_Inventory.xls"');
        header('Cache-Control: max-age=0');
        ob_end_clean();
        $Excel_writer->save('php://output');
    }
}
