<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;

class DeliveryController extends Controller
{
    function saveDetailLimbah(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'document' => 'required',
                'userId' => 'required',
                'NamaBarang' => 'required|array',
                'NamaBarang.*' => 'string',
                'KodeBarang' => 'required|array',
                'Qty' => 'required|array',
                'Qty.*' => 'numeric',
                'SeriBarangAsal' => 'required|array',
                'SeriBarangAsal.*' => 'numeric',
                'Satuan' => 'required|array',
                'BeratBersih' => 'required|array',
                'BM' => 'required|array',
            ],
            [
                'userId.required' => ':attribute is required',
                'document.unique' => 'The document is already added',
                'NamaBarang.required' => ':attribute is required',
                'NamaBarang.*.required' => ':attribute is required',
                'KodeBarang.required' => 'Nama Barang is required',
                'Qty.*.numeric' => ':attribute should be numeric',
                'SeriBarangAsal.*.numeric' => ':attribute should be numeric',
                'BM.required' => ':attribute is required',
                'BM.*.numeric' => ':attribute should be numeric',
            ]
        );

        if ($validator->fails()) {
            return response()->json($validator->errors()->all(), 406);
        }

        $ttlDocument = DB::table("DLV_TBL")->where('DLV_ID', $request->document)->count();

        $message = "";
        if ($ttlDocument) {
            $ttlDetail = count($request->KodeBarang);
            $tobeSaved = [];
            for ($i = 0; $i < $ttlDetail; $i++) {
                $tobeSaved[] = [
                    'DLVSCR_BB_TXID' => $request->document,
                    'DLVSCR_BB_ITMD1' => $request->NamaBarang[$i],
                    'DLVSCR_BB_ITMID' => $request->KodeBarang[$i],
                    'DLVSCR_BB_ITMQT' => $request->Qty[$i],
                    'DLVSCR_BB_HSCD' => $request->HSCODE[$i],
                    'DLVSCR_BB_KODE_KANTOR' => $request->KodeKantor[$i],
                    'DLVSCR_BB_BCTYPE' => $request->BCType[$i],
                    'DLVSCR_BB_AJU' => $request->NomorAju[$i],
                    'DLVSCR_BB_NOPEN' => $request->NomorPendaftaran[$i],
                    'DLVSCR_BB_TGLPEN' => $request->TanggalPendaftaran[$i],
                    'DLVSCR_BB_ITMNW' => $request->BeratBersih[$i],
                    'DLVSCR_BB_ITMUOM' => $request->Satuan[$i],
                    'DLVSCR_BB_BC_DEDUCTION_TYPE' => 1,
                    'DLVSCR_BB_BCURUT' => $request->SeriBarangAsal[$i],
                    'DLVSCR_BB_REMARK' => $request->Remark[$i],
                    'DLVSCR_BB_MATA_UANG' => $request->MataUang[$i],
                    'DLVSCR_BB_ZPRPRC' => $request->Harga[$i],
                    'DLVSCR_BB_BM' => $request->BM[$i],
                    'DLVSCR_BB_LINE' => ($i + 1),
                ];
            }
            if (!empty($tobeSaved)) {
                DB::table("DLVSCR_BB_TBL")->where('DLVSCR_BB_TXID', $request->document)->delete();
                foreach (array_chunk($tobeSaved, (1500 / 16) - 2) as $chunk) {
                    DB::table("DLVSCR_BB_TBL")->insert($chunk);
                }
                $message = "Saved successfully";
            }
        } else {
            $message = "not OK";
        }

        return [
            'message' => $message,
            'ttlDoc' => $ttlDocument
        ];
    }

    function getDetailLimbah(Request $request)
    {
        $data = DB::table("DLVSCR_BB_TBL")->where('DLVSCR_BB_TXID', base64_decode($request->id))->get();
        return ['data' => $data];
    }

    function reportKonversiBahanBaku(Request $request)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('Q1', 'LAMPIRAN');
        $sheet->setCellValue('Q2', 'Surat Kepala KPPBC Tipe Madya Pabean A Bekasis');
        $sheet->mergeCells('Q2:V2', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->setCellValue('Q3', 'Nomor   :');
        $sheet->setCellValue('R3', 'S-1488/KBC.0804/2024');
        $sheet->setCellValue('Q4', 'Tanggal');
        $sheet->setCellValue('R4', '19 Juli 2024');
        $sheet->setCellValue('A5', 'TABEL PERHITUNGAN KONVERSI BAHAN BAKU');
        $sheet->mergeCells('A5:V5', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->getStyle('A5')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A5')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A6', 'No Aju :');
        $sheet->setCellValue('A7', 'No BC & Tgl BC :');
        $sheet->setCellValue('A8', '1. Nama Barang : Assembly PCB');

        $sheet->setCellValue('A11', 'BARANG DAN/ATAU BAHAN BAKU');
        $sheet->mergeCells('A11:G11', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->setCellValue('H11', 'HARGA');
        $sheet->mergeCells('H11:K11', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->setCellValue('L11', 'DOKUMEN PABEAN');
        $sheet->mergeCells('L11:N11', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->setCellValue('Q11', 'NILAI PUNGUTAN');
        $sheet->mergeCells('Q11:T11', $sheet::MERGE_CELL_CONTENT_HIDE);

        $sheet->setCellValue('A12', 'No.');
        $sheet->setCellValue('B12', 'HS Code');
        $sheet->setCellValue('C12', 'Nama Barang');
        $sheet->setCellValue('D12', 'Kode Barang');
        $sheet->setCellValue('E12', 'Koefisien (BOM)');
        $sheet->setCellValue('F12', 'Jumlah terpakai');
        $sheet->setCellValue('G12', 'Satuan');
        $sheet->setCellValue('H12', 'Unit Price');
        $sheet->setCellValue('I12', 'Valuta');
        $sheet->setCellValue('J12', 'NDPBM');
        $sheet->setCellValue('K12', 'Total');
        $sheet->setCellValue('K13', 'Harga');
        $sheet->setCellValue('K14', '(Rp)');
        $sheet->setCellValue('L12', 'Jenis');
        $sheet->setCellValue('L13', 'Dokumen');
        $sheet->setCellValue('L14', '');
        $sheet->setCellValue('M12', 'No Daftar');
        $sheet->mergeCells('M12:M14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->getStyle('M12')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('M12')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue('N12', 'Tanggal');
        $sheet->setCellValue('N13', 'Dokumen');
        $sheet->setCellValue('O11', 'BM');
        $sheet->mergeCells('O11:P13', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->getStyle('O12')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('O12')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue('O14', 'Tarif(%)');
        $sheet->setCellValue('P14', 'Nilai (Rp)');
        $sheet->setCellValue('Q12', 'PPN Import');
        $sheet->mergeCells('Q12:R13', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->getStyle('Q12')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('Q12')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue('Q14', 'Tarif(%)');
        $sheet->setCellValue('R14', 'Nilai (Rp)');

        $sheet->setCellValue('S12', 'PPN Lokal');
        $sheet->mergeCells('S12:T13', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->getStyle('S12')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('S12')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue('S14', 'Tarif(%)');
        $sheet->setCellValue('T14', 'Nilai (Rp)');

        $sheet->setCellValue('U11', 'PPh Ps.22');
        $sheet->mergeCells('U11:V13', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->getStyle('U12')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('U12')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue('U14', 'Tarif(%)');
        $sheet->setCellValue('V14', 'Nilai (Rp)');

        $sheet->mergeCells('A12:A14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('A8:C8', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('A9:C9', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('B12:B14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('C12:C14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('D12:D14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('E12:E14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('F12:F14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('G12:G14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('H12:H14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('I12:I14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('J12:J14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->getStyle('A11:V14')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A11:V14')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A11:V14')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('d4d4d4');

        $dataHead = DB::table('DLV_TBL')
            ->leftJoin('SER_TBL', 'DLV_SER', '=', 'SER_ID')
            ->where('DLV_ID', $request->doc)
            ->groupBy('DLV_BCDATE', 'DLV_NOPEN', 'DLV_ZNOMOR_AJU', 'DLV_RPDATE')
            ->select('DLV_BCDATE', DB::raw("SUM(SER_QTY) TOTALQT"), 'DLV_NOPEN', 'DLV_ZNOMOR_AJU', 'DLV_RPDATE')
            ->first();

        $kurs = DB::table('MEXRATE_TBL')->where('MEXRATE_DT', $dataHead->DLV_BCDATE)->get(['MEXRATE_CURR', 'MEXRATE_VAL']);
        $data = $this->conversion_test_data(['doc' => $request->doc]);

        $sheet->setCellValue('A6', 'No Aju : ' . $dataHead->DLV_ZNOMOR_AJU);
        $sheet->mergeCells('A6:C6', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->setCellValue('A7', 'No BC & Tgl BC : ' . $dataHead->DLV_NOPEN . ', ' . $dataHead->DLV_RPDATE);
        $sheet->mergeCells('A7:C7', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->freezePane('A15');

        $rowAt = 15;

        $fgAt = 0;
        $rmAt = 0;
        $tempFG = '';
        $suppliersCode = [];
        foreach ($data['data'] as $r) {
            if (!in_array($r['SUPCD'], $suppliersCode)) {
                $suppliersCode[] = $r['SUPCD'];
            }
        }

        $suppliers = DB::table('MSUP_TBL')->whereIn('MSUP_SUPCD', $suppliersCode)
            ->get([DB::raw("RTRIM(MSUP_SUPCD) SUPCD"), DB::raw("RTRIM(MSUP_SUPCR) SUPCR")]);

        foreach ($data['data'] as $r) {

            if ($tempFG != $r['SER_ITMID']) {
                $fgAt++;
                $tempFG = $r['SER_ITMID'];
                $rmAt = 1;
            } else {
                $rmAt++;
            }
            $currency = $suppliers->firstWhere('SUPCD', $r['SUPCD'])->SUPCR;
            $ndpbm = in_array($currency, ['IDR', 'RPH']) ? 1 : $kurs->firstWhere('MEXRATE_CURR', $currency)->MEXRATE_VAL;

            $_BM = $r['BM'] >= 5 ? 5 : $r['BM'];
            $_PPN = $r['PPN'] == 10 ? 11 : $r['PPN'];
            $sheet->setCellValue('A' . $rowAt,  " " . $fgAt . '.' . (string)$rmAt);
            $sheet->setCellValue('B' . $rowAt, $r['RCV_HSCD']);
            $sheet->setCellValue('C' . $rowAt, $r['PARTDESCRIPTION']);
            $sheet->setCellValue('D' . $rowAt, $r['SERD2_ITMCD']);
            $sheet->setCellValue('E' . $rowAt, $r['PER']);
            $sheet->setCellValue('F' . $rowAt, $r['RMQT']);
            $sheet->setCellValue('G' . $rowAt, $r['PART_UOM']);
            $sheet->setCellValue('H' . $rowAt, $r['PART_PRICE']);
            $sheet->setCellValue('I' . $rowAt, $currency);
            $sheet->setCellValue('J' . $rowAt,  $ndpbm);
            $sheet->setCellValue('K' . $rowAt, "=(F" . $rowAt . "*" . "H" . $rowAt . ")*J" . $rowAt);
            $sheet->setCellValue('L' . $rowAt, $r['BCTYPE']);
            $sheet->setCellValue('M' . $rowAt, $r['RPSTOCK_BCNUM']);
            $sheet->setCellValue('N' . $rowAt, $r['RPSTOCK_BCDATE']);
            $sheet->setCellValue('O' . $rowAt, $r['BCTYPE'] == '40' ? 0 : $_BM);
            $sheet->setCellValue('P' . $rowAt, "=(K" . $rowAt . "*" . "O" . $rowAt . "/100)");
            $sheet->setCellValue('Q' . $rowAt, $r['BCTYPE'] == '40' ? 0 : $_PPN);
            $sheet->setCellValue('R' . $rowAt, "=(K" . $rowAt . "+P" . $rowAt . ")*" . "Q" . $rowAt . "/100");
            $sheet->setCellValue('S' . $rowAt, $r['BCTYPE'] == '40' ? $_PPN : 0);
            $sheet->setCellValue('T' . $rowAt, "=(K" . $rowAt . "+P" . $rowAt . ")*" . "S" . $rowAt . "/100");
            $sheet->setCellValue('U' . $rowAt, $r['BCTYPE'] == '40' ? 0 : $r['PPH']);
            $sheet->setCellValue('V' . $rowAt, "=(K" . $rowAt . "+P" . $rowAt . ")*" . "U" . $rowAt . "/100");
            $rowAt++;
        }
        $sheet->setCellValue('A9', '2. Jumlah Barang : ' . number_format($dataHead->TOTALQT) . ' PCS');

        $sheet->getStyle('A11:V' . $rowAt - 1)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new Color('1F1812'));

        $sheet->setCellValue('P' . $rowAt, "=CEILING(SUM(P15:P" . $rowAt - 1 . "),1000)");
        $sheet->setCellValue('R' . $rowAt, "=CEILING(SUM(R15:R" . $rowAt - 1 . "),1000)");
        $sheet->setCellValue('T' . $rowAt, "=CEILING(SUM(T15:T" . $rowAt - 1 . "),1000)");
        $sheet->setCellValue('V' . $rowAt, "=CEILING(SUM(V15:V" . $rowAt - 1 . "),1000)");

        $sheet->getStyle('F11:F' . $rowAt)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('K11:K' . $rowAt)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('P11:P' . $rowAt)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('R11:R' . $rowAt)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('T11:T' . $rowAt)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('V11:V' . $rowAt)->getNumberFormat()->setFormatCode('#,##0.00');



        $rowAt++;
        $sheet->setCellValue('B' . $rowAt, 'Konversi yang kami sampaikan di atas adalah benar. Apabila konversi yang Kami sampaikan tidak benar, Kami bersedia menerima sanksi sesuai peraturan yang berlaku.');
        $sheet->mergeCells('B' . $rowAt . ':V' . $rowAt, $sheet::MERGE_CELL_CONTENT_HIDE);
        $rowAt++;
        $sheet->setCellValue('B' . $rowAt, 'Mengetahui');
        $rowAt += 2;
        $sheet->mergeCells('A' . $rowAt . ':C' . $rowAt, $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->setCellValue('A' . $rowAt, 'Pihak Yang mengeluarkan Konversi');
        $sheet->setCellValue('J' . $rowAt, 'Pihak Exim');
        $sheet->setCellValue('T' . $rowAt, 'Pimpinan Perusahaan');
        $sheet->mergeCells('T' . $rowAt . ':U' . $rowAt, $sheet::MERGE_CELL_CONTENT_HIDE);

        $rowAt += 5;
        $sheet->mergeCells('A' . $rowAt . ':B' . $rowAt, $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->setCellValue('A' . $rowAt, 'Hadi Cahyono');
        $sheet->setCellValue('A' . $rowAt + 1, 'Manager');

        $sheet->setCellValue('J' . $rowAt, 'Sri Wahyu');
        $sheet->setCellValue('J' . $rowAt + 1, 'Asst. Manager');
        $sheet->mergeCells('J' . $rowAt + 1 . ':K' . $rowAt + 1, $sheet::MERGE_CELL_CONTENT_HIDE);

        $sheet->setCellValue('T' . $rowAt, 'Indra Andesa');
        $sheet->setCellValue('T' . $rowAt + 1, 'Asst. GM');

        foreach (range('A', 'V') as $r) {
            $sheet->getColumnDimension($r)->setAutoSize(true);
        }

        $sheet->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);

        $sheet->getPageMargins()->setTop(0.2);
        $sheet->getPageMargins()->setRight(0.2);
        $sheet->getPageMargins()->setLeft(0.2);
        $sheet->getPageMargins()->setBottom(0.2);
        $sheet->getPageSetup()->setFitToWidth(1);



        $stringjudul = "Konversi Pemakaian Bahan Baku " . $request->doc;
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $filename = $stringjudul;
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
    }

    private function select_combine_byAju_and_FG($arAJU, $arFG)
    {
        $psnSub  = DB::table('XPPSN1')->groupBy('PPSN1_WONO', 'PPSN1_BOMRV')->select('PPSN1_WONO', 'PPSN1_BOMRV');
        $serd2A  = DB::table('SERD2_TBL');
        $serd2B  = DB::table('SERD2_TBL');

        $data = DB::table('DLV_TBL')
            ->leftJoinSub($serd2A, 'A', 'DLV_SER', '=', 'A.SERD2_SER')
            ->leftJoin('SER_TBL', 'DLV_SER', '=', 'SER_ID')
            ->leftJoin('SERC_TBL', "DLV_SER", "=", "SERC_NEWID")
            ->leftJoinSub($serd2B, 'B', 'DLV_SER', '=', 'B.SERD2_SER')
            ->leftJoin('MITM_TBL', 'B.SERD2_ITMCD', '=', 'MITM_ITMCD')
            ->leftJoinSub($psnSub, 'VPSN', 'SERC_COMJOB', '=', 'PPSN1_WONO')
            ->whereIn('SER_ITMID', $arFG)
            ->whereIn('DLV_ZNOMOR_AJU', $arAJU)
            ->whereNull('A.SERD2_SER')
            ->groupBy("DLV_ZNOMOR_AJU", "SER_ITMID", 'B.SERD2_ITMCD', 'PPSN1_BOMRV', 'MITM_ITMD1', 'DLV_SER', "DLV_QTY", "MITM_STKUOM")
            ->selectRaw('DLV_ZNOMOR_AJU,SER_ITMID,B.SERD2_ITMCD,PPSN1_BOMRV,sum(B.SERD2_QTY) RMQT,DLV_QTY DLVQT,sum(B.SERD2_QTY)/DLV_QTY PER,RTRIM(MITM_ITMD1) PARTDESCRIPTION,DLV_SER, RTRIM(MITM_STKUOM) PART_UOM')
            ->orderBy('DLV_ZNOMOR_AJU')->get();
        return json_decode(json_encode($data), true);
    }

    function selectColumnsWhereRemarkIn($arrayDeliveryOrderNumber)
    {
        $rcvSub  = DB::table('RCV_TBL')->groupBy('RCV_RPNO', 'RCV_DONO', 'RCV_ITMCD')
            ->selectRaw('RCV_RPNO,RCV_DONO,RCV_ITMCD,max(RCV_PRPRC) RCV_PRPRC, MAX(RCV_HSCD) RCV_HSCD, MAX(RCV_BM) BM, MAX(RCV_PPN) PPN, MAX(RCV_PPH) PPH, MAX(RTRIM(RCV_SUPCD)) SUPCD');
        $data = DB::table('ZRPSAL_BCSTOCK')
            ->leftJoinSub($rcvSub, 'v1', function ($join) {
                $join->on('RPSTOCK_NOAJU', '=', 'RCV_RPNO')
                    ->on('RPSTOCK_DOC', '=', 'RCV_DONO')
                    ->on('RPSTOCK_ITMNUM', '=', 'RCV_ITMCD');
            })
            ->selectRaw('RPSTOCK_REMARK,RPSTOCK_DOC,RPSTOCK_NOAJU,UPPER(RTRIM(RPSTOCK_ITMNUM)) RPSTOCK_ITMNUM,RCV_PRPRC,RPSTOCK_BCTYPE, ABS(SUM(RPSTOCK_QTY)) BCQT, RCV_HSCD, RPSTOCK_BCNUM, BM, PPN, PPH, RPSTOCK_BCDATE, SUPCD')
            ->whereIn('RPSTOCK_REMARK', $arrayDeliveryOrderNumber)
            ->groupByRaw('RPSTOCK_REMARK,RPSTOCK_DOC,RPSTOCK_NOAJU,RPSTOCK_ITMNUM,RCV_PRPRC, RPSTOCK_BCTYPE, RCV_HSCD, RPSTOCK_BCNUM, BM, PPN, PPH,RPSTOCK_BCDATE,SUPCD')
            ->orderBy('RPSTOCK_BCDATE')->get();
        return json_decode(json_encode($data), true);
    }

    public function conversion_test_data($params = [])
    {
        $rs = DB::select('exec wms_sp_conversion_test_by_do ?', [$params['doc']]);
        $rs = json_decode(json_encode($rs), true);

        $arIndex = [];
        $arAJU = [];
        $arAJUUnique = [];
        $arFG = [];

        $arrayDeliveryOrderNumber = [];
        $i = 0;
        foreach ($rs as $r) {
            if (!in_array($r['DLV_ZNOMOR_AJU'], $arAJUUnique)) {
                $arAJUUnique[] = $r['DLV_ZNOMOR_AJU'];
                $arrayDeliveryOrderNumber[] = $r['DLV_ID'];
            }
            if (!$r['PER']) {
                $arIndex[] = $i;
                $arAJU[] = $r['DLV_ZNOMOR_AJU'];
                $arFG[] = $r['SER_ITMID'];
            }
            $i++;
        }

        $rsnull = count($arAJU) && count($arFG) ? $this->select_combine_byAju_and_FG($arAJU, $arFG) : [];
        $arrayBC = [];
        if (!empty($arrayDeliveryOrderNumber)) {
            $arrayBC = $this->selectColumnsWhereRemarkIn($arrayDeliveryOrderNumber);
        }

        $NewRS = [];
        if (count($rs)) {
            foreach ($rs as &$r) {
                $r['PART_PRICE'] = null;
                $r['PLOTQT'] = 0;
                $r['BCTYPE'] = '';
                foreach ($arrayBC as &$b) {
                    # Jika item rank
                    if ($r['MITMGRP_ITMCD']) {
                        if ($r['DLV_ID'] === $b['RPSTOCK_REMARK'] && $r['MITMGRP_ITMCD'] === $b['RPSTOCK_ITMNUM'] && $b['BCQT'] > 0) {
                            $need = $r['RMQT'] - $r['PLOTQT'];
                            $_qty = $need;
                            if ($need > $b['BCQT']) {
                                $_qty = $b['BCQT'];
                                $r['PLOTQT'] += $b['BCQT'];
                                $b['BCQT'] = 0;
                            } else {
                                $r['PLOTQT'] += $need;
                                $b['BCQT'] -= $need;
                            }

                            $r['BCTYPE'] = $b['RPSTOCK_BCTYPE'];

                            $r['PART_PRICE'] = (float) $b['RCV_PRPRC'];

                            $NewRS[] = [
                                'DLV_ZNOMOR_AJU' => $r['DLV_ZNOMOR_AJU'],
                                'SER_ITMID' => $r['SER_ITMID'],
                                'DLVQT' => $r['DLVQT'],
                                'SERD2_ITMCD' => $r['SERD2_ITMCD'],
                                'PARTDESCRIPTION' => $r['PARTDESCRIPTION'],
                                'PART_UOM' => $r['PART_UOM'],
                                'PER' => $r['PER'],
                                'RMQT' => $_qty,
                                'PART_PRICE' => $r['PART_PRICE'],
                                'PPSN1_BOMRV' => $r['PPSN1_BOMRV'],
                                'BCTYPE' => $r['BCTYPE'],
                                'MITMGRP_ITMCD' => $r['MITMGRP_ITMCD'],
                                'SUPCD' => $b['SUPCD'],
                                'RPSTOCK_NOAJU' => $b['RPSTOCK_NOAJU'],
                                'RPSTOCK_BCNUM' => $b['RPSTOCK_BCNUM'],
                                'RPSTOCK_BCDATE' => $b['RPSTOCK_BCDATE'],
                                'RCV_HSCD' => $b['RCV_HSCD'],
                                'BM' => $b['BM'],
                                'PPN' => $b['PPN'],
                                'PPH' => $b['PPH'],
                            ];

                            if ($r['RMQT'] == $r['PLOTQT']) {
                                break;
                            }
                        }
                    } else {
                        if ($r['DLV_ID'] === $b['RPSTOCK_REMARK'] && $r['SERD2_ITMCD'] === $b['RPSTOCK_ITMNUM'] && $b['BCQT'] > 0) {
                            $need = $r['RMQT'] - $r['PLOTQT'];
                            $_qty = $need;
                            if ($need > $b['BCQT']) {
                                $_qty = $b['BCQT'];
                                $r['PLOTQT'] += $b['BCQT'];
                                $b['BCQT'] = 0;
                            } else {
                                $r['PLOTQT'] += $need;
                                $b['BCQT'] -= $need;
                            }

                            $r['BCTYPE'] = $b['RPSTOCK_BCTYPE'];

                            $r['PART_PRICE'] = (float) $b['RCV_PRPRC'];

                            $NewRS[] = [
                                'DLV_ZNOMOR_AJU' => $r['DLV_ZNOMOR_AJU'],
                                'SER_ITMID' => $r['SER_ITMID'],
                                'DLVQT' => $r['DLVQT'],
                                'SERD2_ITMCD' => $r['SERD2_ITMCD'],
                                'PARTDESCRIPTION' => $r['PARTDESCRIPTION'],
                                'PART_UOM' => $r['PART_UOM'],
                                'PER' => $r['PER'],
                                'RMQT' => $_qty,
                                'PART_PRICE' => $r['PART_PRICE'],
                                'PPSN1_BOMRV' => $r['PPSN1_BOMRV'],
                                'BCTYPE' => $r['BCTYPE'],
                                'MITMGRP_ITMCD' => $r['MITMGRP_ITMCD'],
                                'SUPCD' => $b['SUPCD'],
                                'RPSTOCK_NOAJU' => $b['RPSTOCK_NOAJU'],
                                'RPSTOCK_BCNUM' => $b['RPSTOCK_BCNUM'],
                                'RPSTOCK_BCDATE' => $b['RPSTOCK_BCDATE'],
                                'RCV_HSCD' => $b['RCV_HSCD'],
                                'BM' => $b['BM'],
                                'PPN' => $b['PPN'],
                                'PPH' => $b['PPH'],
                            ];

                            if ($r['RMQT'] == $r['PLOTQT']) {
                                break;
                            }
                        }
                    }
                }
                unset($b);
            }
            unset($r);

            $sort = [];
            foreach ($NewRS as $k => $v) {
                $sort['DLV_ZNOMOR_AJU'][$k] = $v['DLV_ZNOMOR_AJU'];
                $sort['SER_ITMID'][$k] = $v['SER_ITMID'];
            }
            array_multisort($sort['DLV_ZNOMOR_AJU'], SORT_ASC, $sort['SER_ITMID'], SORT_ASC, $NewRS);
        }

        $NewRSNull = [];

        # Plot Combined RS
        foreach ($rsnull as &$r) {
            $r['PART_PRICE'] = null;
            $r['PLOTQT'] = 0;
            $r['BCTYPE'] = '';
            foreach ($arrayBC as &$b) {
                # Jika item rank
                if ($r['MITMGRP_ITMCD']) {
                    if ($r['DLV_ID'] === $b['RPSTOCK_REMARK'] && $r['MITMGRP_ITMCD'] === $b['RPSTOCK_ITMNUM'] && $b['BCQT'] > 0) {
                        $need = $r['RMQT'] - $r['PLOTQT'];
                        $_qty = $need;

                        if ($need > $b['BCQT']) {
                            $_qty = $b['BCQT'];
                            $r['PLOTQT'] += $b['BCQT'];
                            $b['BCQT'] = 0;
                        } else {
                            $r['PLOTQT'] += $need;
                            $b['BCQT'] -= $need;
                        }

                        $r['BCTYPE'] = $b['RPSTOCK_BCTYPE'];

                        $r['PART_PRICE'] = (float) $b['RCV_PRPRC'];

                        $NewRSNull[] = [
                            'DLV_ZNOMOR_AJU' => $r['DLV_ZNOMOR_AJU'],
                            'SER_ITMID' => $r['SER_ITMID'],
                            'DLVQT' => $r['DLVQT'],
                            'SERD2_ITMCD' => $r['SERD2_ITMCD'],
                            'PARTDESCRIPTION' => $r['PARTDESCRIPTION'],
                            'PART_UOM' => $r['PART_UOM'],
                            'PER' => $r['PER'],
                            'RMQT' => $_qty,
                            'PART_PRICE' => $r['PART_PRICE'],
                            'PPSN1_BOMRV' => $r['PPSN1_BOMRV'],
                            'BCTYPE' => $r['BCTYPE'],
                            'MITMGRP_ITMCD' => $r['MITMGRP_ITMCD'],
                            'SUPCD' => $b['SUPCD'],
                            'RPSTOCK_NOAJU' => $b['RPSTOCK_NOAJU'],
                            'RPSTOCK_BCNUM' => $b['RPSTOCK_BCNUM'],
                            'RPSTOCK_BCDATE' => $b['RPSTOCK_BCDATE'],
                            'RCV_HSCD' => $b['RCV_HSCD'],
                            'BM' => $b['BM'],
                            'PPN' => $b['PPN'],
                            'PPH' => $b['PPH'],
                        ];

                        if ($r['RMQT'] == $r['PLOTQT']) {
                            break;
                        }
                    }
                } else {
                    if ($r['DLV_ID'] === $b['RPSTOCK_REMARK'] && $r['SERD2_ITMCD'] === $b['RPSTOCK_ITMNUM'] && $b['BCQT'] > 0) {
                        $need = $r['RMQT'] - $r['PLOTQT'];
                        $_qty = $need;

                        if ($need > $b['BCQT']) {
                            $_qty = $b['BCQT'];
                            $r['PLOTQT'] += $b['BCQT'];
                            $b['BCQT'] = 0;
                        } else {
                            $r['PLOTQT'] += $need;
                            $b['BCQT'] -= $need;
                        }

                        $r['BCTYPE'] = $b['RPSTOCK_BCTYPE'];

                        $r['PART_PRICE'] = (float) $b['RCV_PRPRC'];

                        $NewRSNull[] = [
                            'DLV_ZNOMOR_AJU' => $r['DLV_ZNOMOR_AJU'],
                            'SER_ITMID' => $r['SER_ITMID'],
                            'DLVQT' => $r['DLVQT'],
                            'SERD2_ITMCD' => $r['SERD2_ITMCD'],
                            'PARTDESCRIPTION' => $r['PARTDESCRIPTION'],
                            'PART_UOM' => $r['PART_UOM'],
                            'PER' => $r['PER'],
                            'RMQT' => $_qty,
                            'PART_PRICE' => $r['PART_PRICE'],
                            'PPSN1_BOMRV' => $r['PPSN1_BOMRV'],
                            'BCTYPE' => $r['BCTYPE'],
                            'MITMGRP_ITMCD' => $r['MITMGRP_ITMCD'],
                            'SUPCD' => $b['SUPCD'],
                            'RPSTOCK_NOAJU' => $b['RPSTOCK_NOAJU'],
                            'RPSTOCK_BCNUM' => $b['RPSTOCK_BCNUM'],
                            'RPSTOCK_BCDATE' => $b['RPSTOCK_BCDATE'],
                            'RCV_HSCD' => $b['RCV_HSCD'],
                            'BM' => $b['BM'],
                            'PPN' => $b['PPN'],
                            'PPH' => $b['PPH'],
                        ];

                        if ($r['RMQT'] == $r['PLOTQT']) {
                            break;
                        }
                    }
                }
            }
            unset($b);
        }
        unset($r);
        return ['data' => $NewRS, 'data_' => $NewRSNull];
    }
}
