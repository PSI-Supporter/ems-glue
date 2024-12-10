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
                    'DLVSCR_BB_ITMQT' => (float)$request->Qty[$i],
                    'DLVSCR_BB_HSCD' => $request->HSCODE[$i],
                    'DLVSCR_BB_KODE_KANTOR' => $request->KodeKantor[$i],
                    'DLVSCR_BB_BCTYPE' => $request->BCType[$i],
                    'DLVSCR_BB_AJU' => $request->NomorAju[$i],
                    'DLVSCR_BB_NOPEN' => $request->NomorPendaftaran[$i],
                    'DLVSCR_BB_TGLPEN' => $request->TanggalPendaftaran[$i],
                    'DLVSCR_BB_ITMNW' => (float)$request->BeratBersih[$i],
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
        $sheet->setCellValue('A8', '1. Nama Perusahaan : PT. SMT Indonesia');
        $sheet->setCellValue('A9', '2. Alamat Perushan : JL. Cisokan 5 Plot 5C-2 EJIP Industrial Park. Sukaresmi, Cikarang Selatan. Kabupaten Bekasi, Jawa Barat, 17857');

        $sheet->setCellValue('A11', 'BARANG');
        $sheet->mergeCells('A11:H11', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->setCellValue('I11', 'BAHAN BAKU');
        $sheet->mergeCells('I11:O11', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->setCellValue('P11', 'HARGA');
        $sheet->mergeCells('P11:S11', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->setCellValue('T11', 'DOKUMEN PABEAN');
        $sheet->mergeCells('T11:V11', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->setCellValue('Y11', 'NILAI PUNGUTAN');
        $sheet->mergeCells('Y11:AB11', $sheet::MERGE_CELL_CONTENT_HIDE);

        $sheet->setCellValue('A12', 'No.');
        $sheet->setCellValue('B12', 'HS Code');
        $sheet->setCellValue('C12', 'Nama Barang');
        $sheet->setCellValue('D12', 'Kode Barang');
        $sheet->setCellValue('E12', 'Satuan');
        $sheet->setCellValue('F12', 'Jumlah');
        $sheet->setCellValue('G12', 'Berat');
        $sheet->setCellValue('H12', 'Harga Penyerahan');
        $sheet->setCellValue('I12', 'No.');
        $sheet->setCellValue('J12', 'HS Code');
        $sheet->setCellValue('K12', 'Nama Barang');
        $sheet->setCellValue('L12', 'Kode Barang');
        $sheet->setCellValue('M12', 'Koefisien (BOM)');
        $sheet->setCellValue('N12', 'Jumlah terpakai');
        $sheet->setCellValue('O12', 'Satuan');
        $sheet->setCellValue('P12', 'Unit Price');
        $sheet->setCellValue('Q12', 'Valuta');
        $sheet->setCellValue('R12', 'NDPBM');
        $sheet->setCellValue('S12', 'Total');
        $sheet->setCellValue('S13', 'Harga');
        $sheet->setCellValue('S14', '(Rp)');
        $sheet->setCellValue('T12', 'Jenis');
        $sheet->setCellValue('T13', 'Dokumen');
        $sheet->setCellValue('T14', '');
        $sheet->setCellValue('U12', 'No Daftar');
        $sheet->mergeCells('U12:U14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->getStyle('U12')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('U12')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue('V12', 'Tanggal');
        $sheet->setCellValue('V13', 'Dokumen');
        $sheet->setCellValue('W11', 'BM');
        $sheet->mergeCells('W11:X13', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->getStyle('W12')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('W12')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue('W14', 'Tarif(%)');
        $sheet->setCellValue('X14', 'Nilai (Rp)');
        $sheet->setCellValue('Y12', 'PPN Import');
        $sheet->mergeCells('Y12:Z13', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->getStyle('Y12')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('Y12')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue('Y14', 'Tarif(%)');
        $sheet->setCellValue('Z14', 'Nilai (Rp)');

        $sheet->setCellValue('AA12', 'PPN Lokal');
        $sheet->mergeCells('AA12:AB13', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->getStyle('AA12')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('AA12')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue('AA14', 'Tarif(%)');
        $sheet->setCellValue('AB14', 'Nilai (Rp)');

        $sheet->setCellValue('AC11', 'PPh Ps.22');
        $sheet->mergeCells('AC11:AD13', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->getStyle('AC12')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('AC12')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue('AC14', 'Tarif(%)');
        $sheet->setCellValue('AD14', 'Nilai (Rp)');

        $sheet->mergeCells('A12:A14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('B12:B14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('C12:C14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('D12:D14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('E12:E14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('F12:F14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('G12:G14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('H12:H14', $sheet::MERGE_CELL_CONTENT_HIDE);

        $sheet->mergeCells('I12:I14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('I8:K8', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('I9:K9', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('J12:J14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('K12:K14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('L12:L14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('M12:M14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('N12:N14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('O12:O14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('P12:P14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('Q12:Q14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('R12:R14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->getStyle('A11:AD14')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A11:AD14')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A11:AD14')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('d4d4d4');
        $sheet->getStyle('A11:AD14')->getAlignment()->setWrapText(true);

        $dataHead = DB::table('DLV_TBL')
            ->leftJoin('SER_TBL', 'DLV_SER', '=', 'SER_ID')
            ->where('DLV_ID', $request->doc)
            ->groupBy('DLV_BCDATE', 'DLV_NOPEN', 'DLV_ZNOMOR_AJU', 'DLV_RPDATE')
            ->select('DLV_BCDATE', DB::raw("SUM(SER_QTY) TOTALQT"), 'DLV_NOPEN', 'DLV_ZNOMOR_AJU', 'DLV_RPDATE')
            ->first();

        $kurs = DB::table('MEXRATE_TBL')->where('MEXRATE_DT', $dataHead->DLV_BCDATE)->get(['MEXRATE_CURR', 'MEXRATE_VAL']);
        $data = $this->conversion_test_data(['doc' => $request->doc]);
        $packaging = DB::select('exec SP_PACKINGLIST_BY_DONO ?', [$request->doc]);
        $prices = DB::table('DLVPRC_TBL')
            ->leftJoin('SER_TBL', 'DLVPRC_SER', '=', 'SER_ID')
            ->groupBy('SER_ITMID', 'DLVPRC_PRC')
            ->where('DLVPRC_TXID', $request->doc)
            ->get(['SER_ITMID', 'DLVPRC_PRC', DB::raw("SUM(DLVPRC_QTY) * DLVPRC_PRC HARGA_PENYERAHAN")]);

        $sheet->setCellValue('A6', 'No Aju : ' . $dataHead->DLV_ZNOMOR_AJU);
        $sheet->mergeCells('A6:C6', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->setCellValue('A7', 'No BC & Tgl BC : ' . $dataHead->DLV_NOPEN . ', ' . $dataHead->DLV_RPDATE);
        $sheet->mergeCells('A7:C7', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('A8:H8', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->mergeCells('A9:H9', $sheet::MERGE_CELL_CONTENT_HIDE);
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

        $tempFgRmUse = '';
        $RMUsageDisplay = '';

        $suppliers = DB::table('MSUP_TBL')->whereIn('MSUP_SUPCD', $suppliersCode)
            ->get([DB::raw("RTRIM(MSUP_SUPCD) SUPCD"), DB::raw("RTRIM(MSUP_SUPCR) SUPCR")]);

        foreach ($data['data'] as $r) {
            $berat = 0;
            $hargaFG = 0;

            foreach ($packaging as $p) {
                if ($r['SER_ITMID'] == $p->SI_ITMCD) {
                    $berat += $p->MITM_GWG;
                }
            }

            foreach ($prices as $p) {
                if ($r['SER_ITMID'] == $p->SER_ITMID) {
                    $hargaFG += $p->HARGA_PENYERAHAN;
                }
            }

            if ($tempFgRmUse != $r['SER_ITMID'] . $r['SERD2_ITMCD'] . $r['PER']) {
                $tempFgRmUse = $r['SER_ITMID'] . $r['SERD2_ITMCD'] . $r['PER'];
                $RMUsageDisplay = $r['PER'];
            } else {
                $RMUsageDisplay = '';
                $sheet->getStyle('J' . $rowAt)->getFont()->getColor()->setARGB(Color::COLOR_WHITE);
                $sheet->getStyle('K' . $rowAt)->getFont()->getColor()->setARGB(Color::COLOR_WHITE);
                $sheet->getStyle('L' . $rowAt)->getFont()->getColor()->setARGB(Color::COLOR_WHITE);
            }

            if ($tempFG != $r['SER_ITMID']) {
                $fgAt++;
                $tempFG = $r['SER_ITMID'];
                $rmAt = 1;
            } else {
                $rmAt++;
                $sheet->getStyle('A' . $rowAt)->getFont()->getColor()->setARGB(Color::COLOR_WHITE);
                $sheet->getStyle('B' . $rowAt)->getFont()->getColor()->setARGB(Color::COLOR_WHITE);
                $sheet->getStyle('C' . $rowAt)->getFont()->getColor()->setARGB(Color::COLOR_WHITE);
                $sheet->getStyle('D' . $rowAt)->getFont()->getColor()->setARGB(Color::COLOR_WHITE);
                $sheet->getStyle('E' . $rowAt)->getFont()->getColor()->setARGB(Color::COLOR_WHITE);
                $sheet->getStyle('F' . $rowAt)->getFont()->getColor()->setARGB(Color::COLOR_WHITE);
                $sheet->getStyle('G' . $rowAt)->getFont()->getColor()->setARGB(Color::COLOR_WHITE);
                $sheet->getStyle('H' . $rowAt)->getFont()->getColor()->setARGB(Color::COLOR_WHITE);
            }
            $currency = $suppliers->firstWhere('SUPCD', $r['SUPCD'])->SUPCR;
            $ndpbm = in_array($currency, ['IDR', 'RPH']) ? 1 : $kurs->firstWhere('MEXRATE_CURR', $currency)->MEXRATE_VAL;

            $_BM = $r['BM'] >= 5 ? 5 : $r['BM'];
            $_PPN = $r['PPN'] == 10 ? 11 : $r['PPN'];


            $sheet->setCellValue('A' . $rowAt, $fgAt);
            $sheet->setCellValue('B' . $rowAt, $r['SER_ITM_HSCODE']);
            $sheet->setCellValue('C' . $rowAt, $r['SER_ITMNM']);
            $sheet->setCellValue('D' . $rowAt, $r['SER_ITMID']);
            $sheet->setCellValue('E' . $rowAt, $r['SER_ITM_UOM']);
            $sheet->setCellValue('F' . $rowAt, $r['DLVQT']);
            $sheet->setCellValue('G' . $rowAt, $berat);
            $sheet->setCellValue('H' . $rowAt, $hargaFG);
            $sheet->setCellValue('I' . $rowAt, (string)$rmAt);
            $sheet->setCellValue('J' . $rowAt, $r['RCV_HSCD']);
            $sheet->setCellValue('K' . $rowAt, $r['PARTDESCRIPTION']);
            $sheet->setCellValue('L' . $rowAt, $r['SERD2_ITMCD']);
            $sheet->setCellValue('M' . $rowAt, $RMUsageDisplay);
            $sheet->setCellValue('N' . $rowAt, $r['RMQT']);
            $sheet->setCellValue('O' . $rowAt, $r['PART_UOM']);
            $sheet->setCellValue('P' . $rowAt, $r['PART_PRICE']);
            $sheet->setCellValue('Q' . $rowAt, $currency);
            $sheet->setCellValue('R' . $rowAt,  $ndpbm);
            $sheet->setCellValue('S' . $rowAt, "=(N" . $rowAt . "*" . "P" . $rowAt . ")*R" . $rowAt);
            $sheet->setCellValue('T' . $rowAt, $r['BCTYPE']);
            $sheet->setCellValue('U' . $rowAt, $r['RPSTOCK_BCNUM']);
            $sheet->setCellValue('V' . $rowAt, $r['RPSTOCK_BCDATE']);
            $sheet->setCellValue('W' . $rowAt, $r['BCTYPE'] == '40' ? 0 : $_BM);
            $sheet->setCellValue('X' . $rowAt, "=(S" . $rowAt . "*" . "W" . $rowAt . "/100)");
            $sheet->setCellValue('Y' . $rowAt, $r['BCTYPE'] == '40' ? 0 : $_PPN);
            $sheet->setCellValue('Z' . $rowAt, "=(S" . $rowAt . "+X" . $rowAt . ")*" . "Y" . $rowAt . "/100");
            $sheet->setCellValue('AA' . $rowAt, $r['BCTYPE'] == '40' ? $_PPN : 0);
            $sheet->setCellValue('AB' . $rowAt, "=(S" . $rowAt . "+X" . $rowAt . ")*" . "AA" . $rowAt . "/100");
            $sheet->setCellValue('AC' . $rowAt, $r['BCTYPE'] == '40' ? 0 : $r['PPH']);
            $sheet->setCellValue('AD' . $rowAt, "=(S" . $rowAt . "+X" . $rowAt . ")*" . "AC" . $rowAt . "/100");
            $rowAt++;
        }

        $sheet->getStyle('A11:AD' . $rowAt - 1)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new Color('1F1812'));

        $sheet->setCellValue('X' . $rowAt, "=CEILING(SUM(X15:X" . $rowAt - 1 . "),1000)");
        $sheet->setCellValue('Z' . $rowAt, "=CEILING(SUM(Z15:Z" . $rowAt - 1 . "),1000)");
        $sheet->setCellValue('AB' . $rowAt, "=CEILING(SUM(AB15:AB" . $rowAt - 1 . "),1000)");
        $sheet->setCellValue('AD' . $rowAt, "=CEILING(SUM(AD15:AD" . $rowAt - 1 . "),1000)");

        $sheet->getStyle('F11:F' . $rowAt)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('H11:H' . $rowAt)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('N11:N' . $rowAt)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('S11:S' . $rowAt)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('X11:X' . $rowAt)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('Z11:Z' . $rowAt)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('AB11:AB' . $rowAt)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('AD11:AD' . $rowAt)->getNumberFormat()->setFormatCode('#,##0.00');



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

        foreach (range('A', 'Z') as $r) {
            $sheet->getColumnDimension($r)->setAutoSize(true);
        }
        $sheet->getColumnDimension('AA')->setAutoSize(true);
        $sheet->getColumnDimension('AB')->setAutoSize(true);
        $sheet->getColumnDimension('AC')->setAutoSize(true);
        $sheet->getColumnDimension('AD')->setAutoSize(true);

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
            ->leftJoinSub($serd2B, 'B', 'SERC_COMID', '=', 'B.SERD2_SER')
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
                                'SER_ITMNM' => $r['SER_ITMNM'],
                                'SER_ITM_HSCODE' => $r['SER_ITM_HSCODE'],
                                'SER_ITM_UOM' => $r['SER_ITM_UOM'],
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
                                'SER_ITMNM' => $r['SER_ITMNM'],
                                'SER_ITM_HSCODE' => $r['SER_ITM_HSCODE'],
                                'SER_ITM_UOM' => $r['SER_ITM_UOM'],
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


    public function getDeliveryResume(Request $request)
    {
        $data = DB::table('DLV_TBL')->leftJoin('SER_TBL', 'DLV_SER', '=', 'SER_ID')
            ->leftJoin('MITM_TBL', 'SER_ITMID', '=', 'MITM_ITMCD')
            ->where('DLV_ID', base64_decode($request->doc))
            ->groupBy('MITM_ITMCD', 'MITM_ITMD1')
            ->orderBy('MITM_ITMCD')
            ->get([
                DB::raw("RTRIM(MITM_ITMCD) ITMCD"),
                DB::raw("RTRIM(MITM_ITMD1) ITMD1"),
                DB::raw("SUM(DLV_QTY) QTY"),
                DB::raw("COUNT(MITM_ITMCD) COUNTQT"),
            ]);

        $firstElem = $data->first();

        if (!$firstElem->ITMCD) {
            $data = DB::table('DLV_TBL')
                ->leftJoin('MITM_TBL', 'DLV_ITMCD', '=', 'MITM_ITMCD')
                ->where('DLV_ID', base64_decode($request->doc))
                ->groupBy('MITM_ITMCD', 'MITM_ITMD1')
                ->orderBy('MITM_ITMCD')
                ->get([
                    DB::raw("RTRIM(MITM_ITMCD) ITMCD"),
                    DB::raw("RTRIM(MITM_ITMD1) ITMD1"),
                    DB::raw("SUM(DLV_QTY) QTY"),
                    DB::raw("'-' COUNTQT"),
                ]);
        }

        return ['data' => $data];
    }

    public function setActualPlatNumber(Request $request)
    {
        $doc = base64_decode($request->doc);
        $DeliveryCheck = DB::table('WMS_DLVCHK')->where('dlv_id', $doc)->count();

        if ($DeliveryCheck == 0) {
            return response()->json(['message' => 'Delivery Checking Operation is required'], 501);
        }

        $affectedRows = DB::table('DLVH_TBL')
            ->where('DLVH_ID', $doc)
            ->whereNull('DLVH_ACT_TRANS')
            ->update([
                'DLVH_ACT_TRANS' => $request->DLVH_TRANS,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $request->user_id
            ]);

        if ($affectedRows) {
            return ['message' => 'saved successfully'];
        } else {
            return response()->json(['message' => 'Sorry could not be updated'], 501);
        }
    }
}
