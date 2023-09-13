<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;


class ItemController extends Controller
{
    function loadById(Request $request)
    {
        $RS = DB::table("ITMLOC_TBL")->select(DB::raw("RTRIM(MITM_ITMCD) MITM_ITMCD,RTRIM(MITM_ITMD1) ITMD1,ITMLOC_LOC,RTRIM(MITM_SPTNO) SPTNO"))
            ->join("MITM_TBL", "ITMLOC_ITM", "=", "MITM_ITMCD")
            ->where("ITMLOC_ITM", base64_decode($request->id))
            ->get();
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

    public function searchItemLocation( request $request)
    #search_itemlocation
    {
        $cid =$request->cid;
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
        $cid =$request->cid;
        $csrchkey = $request->csrchby;
        $rs = array();
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
            $sheet->setCellValue('A'. $n, $r['MITM_ITMCD']);
            $sheet->setCellValue('B'. $n, $r['MITM_ITMD1']);
            $sheet->setCellValue('C'. $n, $r['MITM_HSCD']);
            $sheet->setCellValue('D'. $n, $r['MITM_NWG']);
            $sheet->setCellValue('E'. $n, $r['MITM_GWG']);
            $sheet->setCellValue('F'. $n, $r['MITM_BM']);
            $sheet->setCellValue('G'. $n, $r['MITM_PPN']);
            $sheet->setCellValue('H'. $n, $r['MITM_PPH']);
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
            $sheet->setCellValue('A'. $n, $r['MITM_ITMCD']);
            $sheet->setCellValue('B'. $n, $r['MITM_ITMD1']);
            $sheet->setCellValue('C'. $n, $r['MITM_HSCD']);
            $sheet->setCellValue('D'. $n, $r['MITM_NWG']);
            $sheet->setCellValue('E'. $n, $r['MITM_GWG']);
            $sheet->setCellValue('F'. $n, $r['MITM_BM']);
            $sheet->setCellValue('G'. $n, $r['MITM_PPN']);
            $sheet->setCellValue('H'. $n, $r['MITM_PPH']);
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
    
}