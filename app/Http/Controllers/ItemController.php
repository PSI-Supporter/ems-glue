<?php

namespace App\Http\Controllers;

use App\Models\RawMaterialLabelPrint;
use App\Traits\LabelingTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf;

class ItemController extends Controller
{
    use LabelingTrait;

    function loadById(Request $request)
    {
        $partMeasurement = DB::table('ENG_TECPRTLC')->where('PRTCD', base64_decode($request->id))->first();
        $RS = DB::table("ITMLOC_TBL")->select(DB::raw("RTRIM(MITM_ITMCD) MITM_ITMCD,RTRIM(MITM_ITMD1) ITMD1,ITMLOC_LOC,RTRIM(MITM_SPTNO) SPTNO"))
            ->join("MITM_TBL", "ITMLOC_ITM", "=", "MITM_ITMCD")
            ->where("ITMLOC_ITM", base64_decode($request->id))
            ->get();

        foreach ($RS as $r) {
            $r->STDMIN = $partMeasurement->STDMIN ?? '';
            $r->STDMAX = $partMeasurement->STDMAX ?? '';
            $r->MEAS = $partMeasurement->MEAS ?? '';
        }
        unset($r);

        $result[] = count($RS) ? ['cd' => '1', 'msg' => 'found'] : ['cd' => '0', 'msg' => 'not found'];
        return ['data' => $RS, 'status' => $result];
    }

    function loadItems(Request $request)
    {
        $searchValue = $request->itemName;
        $Items = DB::table('MITM_TBL')->select('MITM_ITMCD', 'MITM_ITMD1')
            ->where('MITM_ITMD1', 'LIKE', '%' . $searchValue . '%')
            ->get();
        return ['data' => $Items];
    }

    function formItem(Request $request)
    {
        $searchValue = $request->itemName;
        $Items = DB::table('MITM_TBL')->select('MITM_ITMCD', 'MITM_ITMD1')
            ->where('MITM_ITMD1', 'LIKE', '%' . $searchValue . '%')
            ->get();
        return view('form_item', ['items' => $Items]);
    }



    #----------------------------------------------TRIAL VIEW DATA------------------------------------------------------------------------
    #----------------------------------------------TRIAL VIEW DATA------------------------------------------------------------------------


    function loadTrans(Request $request)
    {
        $searchValue = $request->transName;
        $Trans = DB::table('MSTTRANS_TBL')->select('MSTTRANS_ID', 'MSTTRANS_TYPE', 'MSTTRANS_LUPDT', 'MSTTRANS_USRID')
            ->where('MSTTRANS_TYPE', 'LIKE', '%' . $searchValue . '%')
            ->get();
        return ['data' => $Trans];
    }

    function formTrans(Request $request)
    {
        $searchValue = $request->transName;
        $Trans = DB::table('MSTTRANS_TBL')->select('MSTTRANS_ID', 'MSTTRANS_TYPE', 'MSTTRANS_LUPDT', 'MSTTRANS_USRID')
            ->where('MSTTRANS_TYPE', 'LIKE', '%' . $searchValue . '%')
            ->get();
        return view('form_trans', ['trans' => $Trans]);
    }

    function loadTruk(Request $request)
    {
        $searchValue = $request->transName;
        $Trans = DB::table('MSTTRANS_TBL')->select('MSTTRANS_ID', 'MSTTRANS_TYPE', 'MSTTRANS_LUPDT', 'MSTTRANS_USRID')
            ->get();
        return ['data' => $Trans];
    }

    function formTruk(Request $request)
    {
        $searchValue = $request->transName;
        $Trans = DB::table('MSTTRANS_TBL')->select('MSTTRANS_ID', 'MSTTRANS_TYPE', 'MSTTRANS_LUPDT', 'MSTTRANS_USRID')
            ->get();
        return view('form_truk', ['Trans' => $Trans]);
    }







    #----------------------------------------------SECTION ITEM CONTROLLER FROM CI------------------------------------------------------------------------
    #----------------------------------------------SECTION ITEM CONTROLLER FROM CI------------------------------------------------------------------------
    #----------------------------------------------SECTION ITEM CONTROLLER FROM CI------------------------------------------------------------------------
    #----------------------------------------------SECTION ITEM CONTROLLER FROM CI------------------------------------------------------------------------
    #----------------------------------------------SECTION ITEM CONTROLLER FROM CI------------------------------------------------------------------------
    #----------------------------------------------SECTION ITEM CONTROLLER FROM CI------------------------------------------------------------------------
    #----------------------------------------------SECTION ITEM CONTROLLER FROM CI------------------------------------------------------------------------




    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
    }
    public function index()
    {
        echo "sorry";
    }
    public function search(request $request)
    # search
    {
        $cid = $request->cid;
        $csrchkey = $request->csrchby;
        $rs = [];
        switch ($csrchkey) {
            case 'itemcd':
                $rs = db::table('MITM_TBL')->selectRaw("rtrim(MITM_ITMCD) MITM_ITMCD, RTRIM(MITM_ITMD1) MITM_ITMD1, MITM_GWG, MITM_NWG
                , ISNULL(MITM_HSCD,'') MITM_HSCD, MITM_BM, MITM_PPN, MITM_PPH,MITM_BOXWEIGHT")
                    ->where('MITM_ITMCD', 'LIKE', '%' . $cid . '%')
                    ->get()->toArray();
                break;
            case 'itemnm':
                $rs = db::table('MITM_TBL')->selectRaw("rtrim(MITM_ITMCD) MITM_ITMCD, RTRIM(MITM_ITMD1) MITM_ITMD1, MITM_GWG, MITM_NWG
                , ISNULL(MITM_HSCD,'') MITM_HSCD, MITM_BM, MITM_PPN, MITM_PPH,MITM_BOXWEIGHT")
                    ->where('MITM_ITMD1', 'LIKE', '%' . $cid . '%')
                    ->get()->toArray();
                break;
            case 'spt':
                $rs = db::table('MITM_TBL')->selectRaw("rtrim(MITM_ITMCD) MITM_ITMCD, RTRIM(MITM_ITMD1) MITM_ITMD1, MITM_GWG, MITM_NWG
                , ISNULL(MITM_HSCD,'') MITM_HSCD, MITM_BM, MITM_PPN, MITM_PPH,MITM_BOXWEIGHT")
                    ->where('MITM_SPTNO', 'LIKE', '%' . $cid . '%')
                    ->get()->toArray();
                break;
        }
        echo json_encode($rs);
    }

    public function searchItemLocation(request $request)
    #search_itemlocation
    {
        $cid = $request->cid;
        $csrchkey = $request->csrchby;
        $rs = [];
        switch ($csrchkey) {
            case 'itemcd':
                $rs = db::table('MITM_TBL')->selectRaw("rtrim(MITM_ITMCD) MITM_ITMCD, RTRIM(MITM_ITMD1) MITM_ITMD1, MITM_GWG, MITM_NWG
                , ISNULL(MITM_HSCD,'') MITM_HSCD, MITM_BM, MITM_PPN, MITM_PPH,MITM_BOXWEIGHT")
                    ->where('MITM_ITMCD', 'LIKE', '%' . $cid . '%')
                    ->get()->toArray();
                break;
            case 'itemnm':
                $rs = db::table('MITM_TBL')->selectRaw("rtrim(MITM_ITMCD) MITM_ITMCD, RTRIM(MITM_ITMD1) MITM_ITMD1, MITM_GWG, MITM_NWG
                , ISNULL(MITM_HSCD,'') MITM_HSCD, MITM_BM, MITM_PPN, MITM_PPH,MITM_BOXWEIGHT")
                    ->where('MITM_ITMD1', 'LIKE', '%' . $cid . '%')
                    ->get()->toArray();
                break;
            case 'spt':
                $rs = db::table('MITM_TBL')->selectRaw("rtrim(MITM_ITMCD) MITM_ITMCD, RTRIM(MITM_ITMD1) MITM_ITMD1, MITM_GWG, MITM_NWG
                , ISNULL(MITM_HSCD,'') MITM_HSCD, MITM_BM, MITM_PPN, MITM_PPH,MITM_BOXWEIGHT")
                    ->where('MITM_SPTNO', 'LIKE', '%' . $cid . '%')
                    ->get()->toArray();
                break;
        }
        echo json_encode($rs);
    }

    public function searchFG(request $request)
    # searchfg
    {
        $cid = $request->cid;
        $csrchkey = $request->csrchby;
        $rs = array();
        switch ($csrchkey) {
            case 'itemcd':
                $rs = db::table('MITM_TBL')->selectRaw("rtrim(MITM_ITMCD) MITM_ITMCD, RTRIM(MITM_ITMD1) MITM_ITMD1, MITM_GWG, MITM_NWG
                , ISNULL(MITM_HSCD,'') MITM_HSCD, MITM_BM, MITM_PPN, MITM_PPH,MITM_BOXWEIGHT")
                    ->where('MITM_ITMCD', 'LIKE', '%' . $cid . '%')
                    ->where('MITM_MODEL', '1')
                    ->get()->toArray();
                break;
            case 'itemnm':
                $rs = db::table('MITM_TBL')->selectRaw("rtrim(MITM_ITMCD) MITM_ITMCD, RTRIM(MITM_ITMD1) MITM_ITMD1, MITM_GWG, MITM_NWG
                , ISNULL(MITM_HSCD,'') MITM_HSCD, MITM_BM, MITM_PPN, MITM_PPH,MITM_BOXWEIGHT")
                    ->where('MITM_ITMD1', 'LIKE', '%' . $cid . '%')
                    ->where('MITM_MODEL', '1')
                    ->get()->toArray();
                break;
            case 'spt':
                $rs = db::table('MITM_TBL')->selectRaw("rtrim(MITM_ITMCD) MITM_ITMCD, RTRIM(MITM_ITMD1) MITM_ITMD1, MITM_GWG, MITM_NWG
                , ISNULL(MITM_HSCD,'') MITM_HSCD, MITM_BM, MITM_PPN, MITM_PPH,MITM_BOXWEIGHT")
                    ->where('MITM_SPTNO', 'LIKE', '%' . $cid . '%')
                    ->where('MITM_MODEL', '1')
                    ->get()->toArray();
                break;
        }
        echo json_encode($rs);
    }

    public function searchFGExim(Request $request)
    # searchfg_exim
    {
        $search = $request->insearch;
        $searchby = $request->insearchby;
        $rs = [];
        switch ($searchby) {
            case 'itemcd':
                $rs = db::table('MITM_TBL')->selectRaw("rtrim(MITM_ITMCD) MITM_ITMCD, RTRIM(MITM_ITMD1) MITM_ITMD1, MITM_GWG, MITM_NWG
                , ISNULL(MITM_HSCD,'') MITM_HSCD, MITM_BM, MITM_PPN, MITM_PPH,MITM_BOXWEIGHT")
                    ->where('MITM_ITMCD', 'LIKE', '%' . $search . '%')
                    ->where('MITM_MODEL', '1')
                    ->get()->toArray();
                break;
            case 'itemnm':
                $rs = db::table('MITM_TBL')->selectRaw("rtrim(MITM_ITMCD) MITM_ITMCD, RTRIM(MITM_ITMD1) MITM_ITMD1, MITM_GWG, MITM_NWG
                , ISNULL(MITM_HSCD,'') MITM_HSCD, MITM_BM, MITM_PPN, MITM_PPH,MITM_BOXWEIGHT")
                    ->where('MITM_ITMD1', 'LIKE', '%' . $search . '%')
                    ->where('MITM_MODEL', '1')
                    ->get()->toArray();
                break;
        }
        $rs = json_decode(json_encode($rs), true);
        foreach ($rs as &$r) {
            $r['MITM_NWG'] = substr($r['MITM_NWG'], 0, 1) == '.' ? '0' . $r['MITM_NWG'] : $r['MITM_NWG'];
            $r['MITM_GWG'] = substr($r['MITM_GWG'], 0, 1) == '.' ? '0' . $r['MITM_GWG'] : $r['MITM_GWG'];
        }
        unset($r);
        die('{"data": ' . json_encode($rs) . '}');
    }

    public function searchRMExim(Request $request)
    # searchrm_exim
    {
        $search = $request->insearch;
        $searchby = $request->insearchby;
        $rs = [];
        switch ($searchby) {
            case 'itemcd':
                $rs = db::table('MITM_TBL')->selectRaw("rtrim(MITM_ITMCD) MITM_ITMCD, RTRIM(MITM_ITMD1) MITM_ITMD1, MITM_GWG, MITM_NWG
                , ISNULL(MITM_HSCD,'') MITM_HSCD, MITM_BM, MITM_PPN, MITM_PPH,MITM_BOXWEIGHT")
                    ->where('MITM_ITMCD', 'LIKE', '%' . $search . '%')
                    ->get()->toArray();
                break;
            case 'itemnm':
                $rs = db::table('MITM_TBL')->selectRaw("rtrim(MITM_ITMCD) MITM_ITMCD, RTRIM(MITM_ITMD1) MITM_ITMD1, MITM_GWG, MITM_NWG
                , ISNULL(MITM_HSCD,'') MITM_HSCD, MITM_BM, MITM_PPN, MITM_PPH,MITM_BOXWEIGHT")
                    ->where('MITM_ITMD1', 'LIKE', '%' . $search . '%')
                    ->get()->toArray();
                break;
        }
        $rs = json_decode(json_encode($rs), true);
        foreach ($rs as &$r) {
            $r['MITM_NWG'] = substr($r['MITM_NWG'], 0, 1) == '.' ? '0' . $r['MITM_NWG'] : $r['MITM_NWG'];
            $r['MITM_GWG'] = substr($r['MITM_GWG'], 0, 1) == '.' ? '0' . $r['MITM_GWG'] : $r['MITM_GWG'];
        }
        unset($r);
        die('{"data": ' . json_encode($rs) . '}');
    }

    public function searchRMEximXls()
    # searchrm_exim_xls
    {
        $search = $searchby = '';
        if (isset($_COOKIE["CKPSEARCH"])) {
            $search = $_COOKIE["CKPSEARCH"];
        } else {
            exit('nothing to be exported');
        }

        $search = $_COOKIE["CKPSEARCH"];
        $searchby = $_COOKIE["CKPSEARCH_BY"];
        $rs = [];
        switch ($searchby) {
            case 'itemcd':
                $rs = db::table('MITM_TBL')->selectRaw("rtrim(MITM_ITMCD) MITM_ITMCD, RTRIM(MITM_ITMD1) MITM_ITMD1, MITM_GWG, MITM_NWG
                , ISNULL(MITM_HSCD,'') MITM_HSCD, MITM_BM, MITM_PPN, MITM_PPH,MITM_BOXWEIGHT")
                    ->where('MITM_ITMCD', 'LIKE', '%' . $search . '%')
                    ->get()->toArray();
                break;
            case 'itemnm':
                $rs = db::table('MITM_TBL')->selectRaw("rtrim(MITM_ITMCD) MITM_ITMCD, RTRIM(MITM_ITMD1) MITM_ITMD1, MITM_GWG, MITM_NWG
                , ISNULL(MITM_HSCD,'') MITM_HSCD, MITM_BM, MITM_PPN, MITM_PPH,MITM_BOXWEIGHT")
                    ->where('MITM_ITMD1', 'LIKE', '%' . $search . '%')
                    ->get()->toArray();
                break;
        }
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('master_hscode');
        $sheet->setCellValue('A1', 'Item Code');
        $sheet->setCellValue('B1', 'Item Name');
        $sheet->setCellValue('C1', 'HS Code');
        $sheet->setCellValue('D1', 'Net Weight');
        $sheet->setCellValue('E1', 'Gross Weight');
        $sheet->setCellValue('F1', 'BM');
        $sheet->setCellValue('G1', 'PPN');
        $sheet->setCellValue('H1', 'PPH');
        $n = 2;
        $rs = json_decode(json_encode($rs), true);
        foreach ($rs as &$r) {
            $r['MITM_NWG'] = substr($r['MITM_NWG'], 0, 1) == '.' ? '0' . $r['MITM_NWG'] : $r['MITM_NWG'];
            $r['MITM_GWG'] = substr($r['MITM_GWG'], 0, 1) == '.' ? '0' . $r['MITM_GWG'] : $r['MITM_GWG'];
            $sheet->setCellValue('A' . $n, $r['MITM_ITMCD']);
            $sheet->setCellValue('B' . $n, $r['MITM_ITMD1']);
            $sheet->setCellValue('C' . $n, $r['MITM_HSCD']);
            $sheet->setCellValue('D' . $n, $r['MITM_NWG']);
            $sheet->setCellValue('E' . $n, $r['MITM_GWG']);
            $sheet->setCellValue('F' . $n, $r['MITM_BM']);
            $sheet->setCellValue('G' . $n, $r['MITM_PPN']);
            $sheet->setCellValue('H' . $n, $r['MITM_PPH']);
            $n++;
        }
        unset($r);
        foreach (range('A', 'H') as $v) {
            $sheet->getColumnDimension($v)->setAutoSize(true);
        }
        $sheet->getStyle('A1:A' . $n)->getAlignment()->setHorizontal('left');
        $stringjudul = "master hscode";
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $filename = $stringjudul; //save our workbook as this file name

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
    }
    public function searchFGEximXls(request $request)
    # searchfg_exim_xls
    {
        $search = $searchby = '';
        if (isset($_COOKIE["CKPSEARCH"])) {
            $search = $_COOKIE["CKPSEARCH"];
        } else {
            exit('nothing to be exported');
        }

        $search = $_COOKIE["CKPSEARCH"];
        $searchby = $_COOKIE["CKPSEARCH_BY"];
        $rs = [];
        switch ($searchby) {
            case 'itemcd':
                $rs = db::table('MITM_TBL')->selectRaw("rtrim(MITM_ITMCD) MITM_ITMCD, RTRIM(MITM_ITMD1) MITM_ITMD1, MITM_GWG, MITM_NWG
                , ISNULL(MITM_HSCD,'') MITM_HSCD, MITM_BM, MITM_PPN, MITM_PPH,MITM_BOXWEIGHT")
                    ->where('MITM_ITMCD', 'LIKE', '%' . $search . '%')
                    ->get()->toArray();
                break;
            case 'itemnm':
                $rs = db::table('MITM_TBL')->selectRaw("rtrim(MITM_ITMCD) MITM_ITMCD, RTRIM(MITM_ITMD1) MITM_ITMD1, MITM_GWG, MITM_NWG
                , ISNULL(MITM_HSCD,'') MITM_HSCD, MITM_BM, MITM_PPN, MITM_PPH,MITM_BOXWEIGHT")
                    ->where('MITM_ITMD1', 'LIKE', '%' . $search . '%')
                    ->get()->toArray();
        }
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('master_hscode');
        $sheet->setCellValue('A1', 'Item Code');
        $sheet->setCellValue('B1', 'Item Name');
        $sheet->setCellValue('C1', 'HS Code');
        $sheet->setCellValue('D1', 'Net Weight');
        $sheet->setCellValue('E1', 'Gross Weight');
        $sheet->setCellValue('F1', 'BM');
        $sheet->setCellValue('G1', 'PPN');
        $sheet->setCellValue('H1', 'PPH');
        $n = 2;
        $rs = json_decode(json_encode($rs), true);
        foreach ($rs as &$r) {
            $r['MITM_NWG'] = substr($r['MITM_NWG'], 0, 1) == '.' ? '0' . $r['MITM_NWG'] : $r['MITM_NWG'];
            $r['MITM_GWG'] = substr($r['MITM_GWG'], 0, 1) == '.' ? '0' . $r['MITM_GWG'] : $r['MITM_GWG'];
            $sheet->setCellValue('A' . $n, $r['MITM_ITMCD']);
            $sheet->setCellValue('B' . $n, $r['MITM_ITMD1']);
            $sheet->setCellValue('C' . $n, $r['MITM_HSCD']);
            $sheet->setCellValue('D' . $n, $r['MITM_NWG']);
            $sheet->setCellValue('E' . $n, $r['MITM_GWG']);
            $sheet->setCellValue('F' . $n, $r['MITM_BM']);
            $sheet->setCellValue('G' . $n, $r['MITM_PPN']);
            $sheet->setCellValue('H' . $n, $r['MITM_PPH']);
            $n++;
        }
        unset($r);
        foreach (range('A', 'H') as $v) {
            $sheet->getColumnDimension($v)->setAutoSize(true);
        }
        $sheet->getStyle('A1:A' . $n)->getAlignment()->setHorizontal('left');
        $stringjudul = "master hscode fg";
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $filename = $stringjudul; //save our workbook as this file name

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
    }

    function SpreadsheetToPdf(Request $request)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('master_hscode');
        $spreadsheet->getActiveSheet()->getStyle('A5:H5')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('bde0fe');
        $sheet->setCellValue('A1', 'Name');
        $sheet->setCellValue('B1', $request->name);
        $sheet->setCellValue('A5', 'Item Code');
        $sheet->setCellValue('B5', 'Item Name');
        $sheet->setCellValue('C5', 'HS Code');
        $sheet->setCellValue('D5', 'Net Weight');
        $sheet->setCellValue('E5', 'Gross Weight');
        $sheet->setCellValue('F5', 'BM');
        $sheet->setCellValue('G5', 'PPN');
        $sheet->setCellValue('H5', 'PPH');
        $barisY = 5;
        for ($i = 1; $i < 200; $i++) {
            $sheet->setCellValue('A' . $i + 5, 'Item Code ' . $i);
        }
        foreach (range('A', 'L') as $v) {
            $sheet->getColumnDimension($v)->setAutoSize(true);
        }

        $sheet->getStyle('A5:H' . ($i - 1))->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        $sheet->mergeCells('B6:B10', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->setCellValue('B6', 'COBA');
        $sheet->getStyle('B6')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('B6')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $spreadsheet->getActiveSheet()->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 5);

        // PDF
        $writer = new Mpdf($spreadsheet);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="kamu.pdf"');
        $writer->save('php://output');
    }

    public function formItemReport()
    {
        return view('master.item_report');
    }

    public function toXRAYItem()
    {
        $unregisterdItem = DB::table('wms_v_unregistered_item_in_xray')->get();
        $dataTobeStored = [];
        foreach ($unregisterdItem as $r) {
            $dataTobeStored[] = [
                'item_code' => $r->MITM_ITMCD,
                'item_name' => $r->MITM_SPTNO,
                'rack_code' => $r->ITMLOC_LOC,
            ];
        }
        $affectedRows = 0;
        foreach (array_chunk($dataTobeStored, (2100 / 3) - 2) as $chunk) {
            $affectedRows += DB::connection('mysql_xray')->table('items')->insert($chunk);
        }
        return ['data' => $unregisterdItem, 'affecteRows' => $affectedRows];
    }

    public function splitC3(Request $request)
    {
        $message = '';

        $newUnique = [];
        if ($request->uniqueBefore) {
            // is already splited or combined
            $rowsCount = DB::table('raw_material_labels')
                ->where(function ($query) use ($request) {
                    $query->where('code', $request->uniqueBefore)->where('splitted', 1);
                })->orWhere(function ($query) use ($request) {
                    $query->where('code', $request->uniqueBefore)->where('combined', 1);
                })->count();
            if ($rowsCount > 0) {
                return ['cd' => '0', 'msg' => 'Already splitted or combined'];
            }
        }

        $data = [];

        if ($request->old_qty != $request->new_qty) {
            // Split mode
            if ($request->mode == 1) {
                // two labels mode
                $qtyAfter1 = $request->new_qty;
                $qtyAfter2 = $request->old_qty - $request->new_qty;
                $Response = $this->generateLabelId([
                    'machineName' => $request->machineName ?? 'DF',
                    'documentCode' => 'split-doc',
                    'itemCode' => $request->item_code,
                    'qty' => $qtyAfter1,
                    'lotNumber' => $request->lot_number,
                    'userID' => $request->user_id,
                    'parent_code' => $request->uniqueBefore,
                ]);
                $newUnique[] = $Response['data'];

                $Response = $this->generateLabelId([
                    'machineName' => $request->machineName ?? 'DF',
                    'documentCode' => 'split-doc',
                    'itemCode' => $request->item_code,
                    'qty' => $qtyAfter2,
                    'lotNumber' => $request->lot_number,
                    'userID' => $request->user_id,
                    'parent_code' => $request->uniqueBefore,
                ]);
                $newUnique[] = $Response['data'];
            } else {
                // multiple label mode
                $restValue = $request->old_qty % $request->new_qty ? 1 : 0;
                $countLabel = floor($request->old_qty / $request->new_qty);
                $lastLabelQty = $request->old_qty;
                for ($i = 0; $i < $countLabel; $i++) {
                    $Response = $this->generateLabelId([
                        'machineName' => $request->machineName ?? 'DF',
                        'documentCode' => 'split-doc',
                        'itemCode' => $request->item_code,
                        'qty' => $request->new_qty,
                        'lotNumber' => $request->lot_number,
                        'userID' => $request->user_id,
                        'parent_code' => $request->uniqueBefore,
                    ]);
                    $newUnique[] = $Response['data'];
                    $lastLabelQty -= $request->new_qty;
                }

                if ($restValue) {
                    $Response = $this->generateLabelId([
                        'machineName' => $request->machineName ?? 'DF',
                        'documentCode' => 'split-doc',
                        'itemCode' => $request->item_code,
                        'qty' => $lastLabelQty,
                        'lotNumber' => $request->lot_number,
                        'userID' => $request->user_id,
                        'parent_code' => $request->uniqueBefore,
                    ]);
                    $newUnique[] = $Response['data'];
                }
            }
            if ($newUnique) {
                $message = 'Splitted successfully';
                $data = DB::table('raw_material_labels')
                    ->leftJoin('MITM_TBL', 'item_code', '=', 'MITM_ITMCD')
                    ->leftJoin('ITMLOC_TBL', 'MITM_ITMCD', '=', 'ITMLOC_ITM')
                    ->whereIn('code', $newUnique)
                    ->get([
                        'code',
                        DB::raw('RTRIM(MITM_SPTNO) SPTNO'),
                        DB::raw('CONVERT(INT,quantity) quantity'),
                        DB::raw('RTRIM(ITMLOC_LOC) LOC'),
                    ]);
            }


            if ($request->uniqueBefore) {
                // update parent label
                $affectedRows = DB::table('raw_material_labels')
                    ->where('code', $request->uniqueBefore)
                    ->update(['splitted' => '1']);
                $lastLocation = DB::table('ITH_TBL')
                    ->where('ITH_SER', $request->uniqueBefore)
                    ->groupBy('ITH_WH')
                    ->havingRaw('SUM(ITH_QTY)>0')
                    ->first('ITH_WH');

                if ($lastLocation) {
                    DB::table('ITH_TBL')->insert([
                        'ITH_ITMCD' => $request->item_code,
                        'ITH_WH' =>  $lastLocation->ITH_WH,
                        'ITH_DOC' => 'split-' . $request->uniqueBefore,
                        'ITH_DATE' => date('Y-m-d'),
                        'ITH_FORM' => 'SPLIT-C3',
                        'ITH_QTY' => -1 * $request->old_qty,
                        'ITH_SER' => $request->uniqueBefore,
                        'ITH_USRID' =>  $request->user_id,
                        'ITH_LUPDT' =>  date('Y-m-d H:i:s')
                    ]);

                    $dataTobeStored = [];

                    foreach ($data as $r) {
                        $dataTobeStored[] = [
                            'ITH_ITMCD' => $request->item_code,
                            'ITH_WH' =>  $lastLocation->ITH_WH,
                            'ITH_DOC' => 'split-' . $request->uniqueBefore,
                            'ITH_DATE' => date('Y-m-d'),
                            'ITH_FORM' => 'SPLIT-C3',
                            'ITH_QTY' => $r->quantity,
                            'ITH_SER' => $r->code,
                            'ITH_USRID' =>  $request->user_id,
                            'ITH_LUPDT' =>  date('Y-m-d H:i:s')
                        ];
                    }

                    if ($dataTobeStored) {
                        DB::table('ITH_TBL')->insert($dataTobeStored);
                    }
                }
            }
        } else {
            // reprint mode
            $message = 'Reprint successfully';
            if ($request->uniqueBefore) {
                $newUnique[] = $request->uniqueBefore;
                $tobePrinted = DB::table('raw_material_labels')->where('code', $request->uniqueBefore)
                    ->first();
                if ($tobePrinted->code) {
                    RawMaterialLabelPrint::create([
                        'code' => $tobePrinted->code,
                        'item_code' => $tobePrinted->item_code,
                        'doc_code' => $tobePrinted->doc_code,
                        'parent_code' => $tobePrinted->parent_code,
                        'quantity' => $tobePrinted->quantity,
                        'lot_code' => $tobePrinted->lot_code,
                        'action' => 'reprint',
                        'created_by' => $request->user_id,
                        'pc_name' => $request->machineName,
                    ]);
                }
            } else {
                $Response = $this->generateLabelId([
                    'machineName' => $request->machineName ?? 'DF',
                    'documentCode' => 'reprint-doc',
                    'itemCode' => $request->item_code,
                    'qty' => $request->old_qty,
                    'lotNumber' => $request->lot_number,
                    'userID' => $request->user_id,
                ]);
                $newUnique[] = $Response['data'];
            }

            $data = DB::table('raw_material_labels')
                ->leftJoin('MITM_TBL', 'item_code', '=', 'MITM_ITMCD')
                ->leftJoin('ITMLOC_TBL', 'MITM_ITMCD', '=', 'ITMLOC_ITM')
                ->whereIn('code', $newUnique)
                ->orderBy('created_at')
                ->get([
                    'code',
                    DB::raw('RTRIM(MITM_SPTNO) SPTNO'),
                    DB::raw('CONVERT(INT,quantity) quantity'),
                    DB::raw('RTRIM(ITMLOC_LOC) LOC'),
                ]);
        }
        return ['cd' => "1", 'msg' => $message,  'data' => $data];
    }
}
