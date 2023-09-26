<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\IOFactory;

class InventoryController extends Controller
{
    #load data to view
    function loadInventory(Request $request)
    {
        $searchValue = $request->inventory;
        $Inv = DB::table('WMS_Inv')
            ->select('cLoc', 'cAssyNo', 'cModel', 'cQty', DB::raw("COUNT(*) as BOX"), DB::raw("SUM(cQty) as Total"))
            ->groupBy('cLoc', 'cAssyNo', 'cModel', 'cQty')
            ->paginate(20);
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
            ->paginate(20);
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
        $data = DB::table('WMS_Inv')
            ->select('cLoc', 'cAssyNo', 'cModel', 'cQty', DB::raw("COUNT(*) as BOX"), DB::raw("SUM(cQty) as Total"))
            ->groupBy('cLoc', 'cAssyNo', 'cModel', 'cQty')
            ->orderBy('cLoc', 'ASC')
            ->orderBy('cAssyNo', 'ASC')
            ->get();
        $data_array[] = array("cLoc", "cAssyNo", "cModel", "cQty", "BOX", "Total");
        $locBefore = NULL;
        $cdBefore = NULL;
        $totalBox = 0;
        $totalQty = 0;
        foreach ($data as $data_item) {
            $arrayTotal = [];
            $totalQty += $data_item->Total;
            $totalBox += $data_item->BOX;
            if ($locBefore != $data_item->cLoc) {
                $locBefore = $data_item->cLoc;
                $fixLoc = $data_item->cLoc;
                $arrayTotal = array(
                    'Loc' => NULL,
                    'Part Code' => NULL,
                    'Part Name' => NULL,
                    'QTY' => 'Total',
                    'BOX' => $totalBox,
                    'Total' => $totalQty
                );
            } else {
                $fixLoc = NULL;
            }

            if ($cdBefore != $data_item->cAssyNo) {
                $cdBefore = $data_item->cAssyNo;
                $fixCd = $data_item->cAssyNo;
            } else {
                $fixCd = NULL;
            }

            $data_array[] = array(
                'Loc' => $fixLoc,
                'Part Code' => $fixCd,
                'Part Name' => $data_item->cModel,
                'QTY' => $data_item->cQty,
                'BOX' => $data_item->BOX,
                'Total' => $data_item->Total
            );
            if (!empty($arrayTotal)) {
                $data_array[] = $arrayTotal;
            }
        }
        $this->ExportExcel($data_array);
    }
}
