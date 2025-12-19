<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;

class DeliveryController extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
    }

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
                    'DLVSCR_BB_ITMNW' => rtrim(number_format($request->BeratBersih[$i], 6, ".", ""), "0"),
                    'DLVSCR_BB_ITMUOM' => $request->Satuan[$i],
                    'DLVSCR_BB_BC_DEDUCTION_TYPE' => 1,
                    'DLVSCR_BB_BCURUT' => $request->SeriBarangAsal[$i],
                    'DLVSCR_BB_REMARK' => $request->Remark[$i],
                    'DLVSCR_BB_MATA_UANG' => $request->MataUang[$i],
                    'DLVSCR_BB_ZPRPRC' => (float)$request->Harga[$i],
                    'DLVSCR_BB_BM' => $request->BM[$i],
                    'DLVSCR_BB_LINE' => ($i + 1),
                ];
            }

            if (!empty($tobeSaved)) {
                $TOTAL_COLUMN = 19;
                DB::table("DLVSCR_BB_TBL")->where('DLVSCR_BB_TXID', $request->document)->delete();
                $chunks = collect($tobeSaved)->chunk(2000 / $TOTAL_COLUMN);
                foreach ($chunks as $chunk) {
                    DB::table("DLVSCR_BB_TBL")->insert($chunk->toArray());
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
        foreach ($data['data'] as &$r) {
            $r['DLVQTBAK'] = $r['DLVQT'];
            if (!in_array($r['SUPCD'], $suppliersCode)) {
                $suppliersCode[] = $r['SUPCD'];
            }
        }
        unset($r);

        $rsitem_p_price = $this->setPriceRS(base64_encode($request->doc));
        $rsplotrm_per_fgprice = $this->perprice($request->doc, $rsitem_p_price);


        $tempFgRmUse = '';
        $RMUsageDisplay = '';

        $suppliers = DB::table('MSUP_TBL')->whereIn('MSUP_SUPCD', $suppliersCode)
            ->get([
                DB::raw("RTRIM(MSUP_SUPCD) SUPCD"),
                DB::raw("RTRIM(MSUP_SUPCR) SUPCR")
            ]);

        $newList = [];
        usort($rsplotrm_per_fgprice, function ($a, $b) {
            $cmp = strcmp($a['RASSYCODE'], $b['RASSYCODE']);
            if ($cmp !== 0) {
                return $cmp;
            }

            // 2. RPRICEGROUP (numeric)
            if ($a['RPRICEGROUP'] != $b['RPRICEGROUP']) {
                return $a['RPRICEGROUP'] <=> $b['RPRICEGROUP'];
            }

            // 3. RITEMCD
            return strcmp($a['RITEMCD'], $b['RITEMCD']);
        });
        foreach ($rsplotrm_per_fgprice as $p) {
            foreach ($data['data'] as &$r) {
                if (
                    $p['RASSYCODE'] == $r['SER_ITMID']
                    && $p['RITEMCD'] == $r['SERD2_ITMCD']
                    && $p['RQTY'] > 0 && $r['RMQT'] > 0
                ) {
                    $theQty = -2;
                    if ($p['RQTY'] > $r['RMQT']) {
                        $theQty = $r['RMQT'];
                        $r['RMQT'] = 0;
                    } else {
                        $theQty = $p['RQTY'];
                        $r['RMQT'] -= $p['RQTY'];
                    }
                    $p['RQTY'] -= $theQty;



                    $newList[] = [
                        'SER_ITM_HSCODE' => $r['SER_ITM_HSCODE'],
                        'SER_ITMID' => $r['SER_ITMID'],
                        'SER_ITMNM' => $r['SER_ITMNM'],
                        'SER_ITM_UOM' => $r['SER_ITM_UOM'],
                        'DLVQT' => $r['DLVQT'],
                        'RCV_HSCD' => $r['RCV_HSCD'],
                        'PARTDESCRIPTION' => $r['PARTDESCRIPTION'],
                        'SERD2_ITMCD' => $r['SERD2_ITMCD'],
                        'PER' => $r['PER'],
                        'RMQT' => $theQty,
                        'BCTYPE' => $r['BCTYPE'],
                        'RPSTOCK_BCDATE' => $r['RPSTOCK_BCDATE'],
                        'BM' => $r['BM'],
                        'PART_UOM' => $r['PART_UOM'],
                        'PART_PRICE' => $r['PART_PRICE'],
                        'RPSTOCK_BCNUM' => $r['RPSTOCK_BCNUM'],
                        'SUPCD' => $r['SUPCD'],
                        'PPN' => $r['PPN'],
                        'PPH' => $r['PPH'],
                        'hargaFG' => $p['RPRICEGROUP'],
                    ];
                }
                if ($p['RQTY'] == 0) {
                    break;
                }
            }
            unset($r);
        }


        foreach ($newList as $r) {
            $berat = 0;
            $hargaFG = $r['hargaFG'];

            foreach ($packaging as $p) {
                if ($r['SER_ITMID'] == $p->SI_ITMCD) {
                    $berat += $p->MITM_GWG;
                }
            }

            foreach ($rsitem_p_price as $p) {
                if ($r['SER_ITMID'] == $p['SSO2_MDLCD'] && $r['hargaFG'] == $p['CIF']) {
                    $r['DLVQT'] = $p['SISOQTY'];
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

            if ($tempFG != $r['SER_ITMID'] . $r['hargaFG']) {
                $fgAt++;
                $tempFG = $r['SER_ITMID'] . $r['hargaFG'];
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
        $MITM_TBLFG  = DB::table('MITM_TBL')
            ->where('MITM_MODEL', '1')
            ->select(
                DB::raw('RTRIM(MITM_ITMCD) FGCD'),
                DB::raw('RTRIM(MITM_ITMD1) SER_ITMNM'),
                DB::raw('RTRIM(MITM_HSCD) SER_ITM_HSCODE'),
                DB::raw('RTRIM(MITM_STKUOM) SER_ITM_UOM'),
            );

        $data = DB::table('DLV_TBL')
            ->leftJoinSub($serd2A, 'A', 'DLV_SER', '=', 'A.SERD2_SER')
            ->leftJoin('SER_TBL', 'DLV_SER', '=', 'SER_ID')
            ->leftJoin('SERC_TBL', "DLV_SER", "=", "SERC_NEWID")
            ->leftJoinSub($serd2B, 'B', 'SERC_COMID', '=', 'B.SERD2_SER')
            ->leftJoin('MITM_TBL', 'B.SERD2_ITMCD', '=', 'MITM_ITMCD')
            ->leftJoin('MITMGRP_TBL', 'B.SERD2_ITMCD', '=', 'MITMGRP_ITMCD_GRD')
            ->leftJoinSub($psnSub, 'VPSN', 'SERC_COMJOB', '=', 'PPSN1_WONO')
            ->whereIn('SER_ITMID', $arFG)
            ->whereIn('DLV_ZNOMOR_AJU', $arAJU)
            ->whereNull('A.SERD2_SER')
            ->groupBy(
                "DLV_ZNOMOR_AJU",
                "SER_ITMID",
                'B.SERD2_ITMCD',
                'PPSN1_BOMRV',
                'MITM_ITMD1',
                'DLV_SER',
                "DLV_QTY",
                "MITM_STKUOM",
                "MITMGRP_ITMCD",
                "DLV_ID"
            )
            ->selectRaw('DLV_ID,DLV_ZNOMOR_AJU,SER_ITMID,B.SERD2_ITMCD,PPSN1_BOMRV,sum(B.SERD2_QTY) RMQT,DLV_QTY DLVQT,sum(B.SERD2_QTY)/DLV_QTY PER,RTRIM(MITM_ITMD1) PARTDESCRIPTION,DLV_SER, RTRIM(MITM_STKUOM) PART_UOM,MITMGRP_ITMCD');


        $dataFinal = DB::query()->fromSub($data, 'VDEEP1')
            ->leftJoinSub($MITM_TBLFG, 'MITM', 'SER_ITMID', '=', 'FGCD')
            ->selectRaw('DLV_ID,DLV_ZNOMOR_AJU,SER_ITMID,SERD2_ITMCD,PPSN1_BOMRV,
         RMQT, DLVQT, PER, PARTDESCRIPTION,DLV_SER, PART_UOM,
         MITMGRP_ITMCD,SER_ITMNM,SER_ITM_HSCODE,SER_ITM_UOM')
            ->orderBy('DLV_ZNOMOR_AJU')->get();
        return json_decode(json_encode($dataFinal), true);
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

        $deliveryConsigment = DB::table('DLV_TBL')
            ->leftJoin('SER_TBL', 'DLV_SER', '=', 'SER_ID')
            ->where('DLV_ID', $doc)->first(['DLV_CUSTCD', 'DLV_CONSIGN', 'SER_ITMID', 'DLV_RMRK']);

        if ($DeliveryCheck == 0 &&  !str_contains($doc, 'RTN') && !str_contains($doc, 'WS')) {
            if ($deliveryConsigment->DLV_CONSIGN == 'IEI' && $deliveryConsigment->DLV_CUSTCD == 'IEP001U') {
            } else {
                if (str_contains($doc, 'TS') || str_contains($deliveryConsigment->SER_ITMID, 'TRIAL') || str_contains(strtoupper($deliveryConsigment->DLV_RMRK ?? ''), 'OFFLINE')) {
                } else {
                    return response()->json(['message' => 'Delivery Checking Operation is required'], 501);
                }
            }
        }

        $affectedRows = DB::table('DLVH_TBL')
            ->where('DLVH_ID', $doc)
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

    public function getNotGateOut()
    {
        $data = DB::table('v_delivery_not_gate_out_yet')->groupBy('DLVH_ACT_TRANS')
            ->get(['DLVH_ACT_TRANS']);
        return ['data' => $data];
    }

    public function getNotGateOutDetail(Request $request)
    {
        $data = DB::table('v_delivery_not_gate_out_yet')->where('DLVH_ACT_TRANS', base64_decode($request->doc))
            ->get();
        return ['data' => $data];
    }

    public function setGateOut(Request $request)
    {
        $vechicleRegNumber = base64_decode($request->regNumber);
        $documents = DB::table('DLVH_TBL')->where('DLVH_ACT_TRANS', $vechicleRegNumber)
            ->whereNull('DLVH_DRIVER_NAME')
            ->get(['DLVH_ID'])->pluck('DLVH_ID')->toArray();

        $dataSI = DB::table('DLV_TBL')->whereIn('DLV_ID', $documents)
            ->leftJoin('SISCN_TBL', 'DLV_SER', '=', 'SISCN_SER')
            ->leftJoin('SI_TBL', 'SISCN_LINENO', '=', 'SI_LINENO')
            ->leftJoin('SER_TBL', 'DLV_SER', '=', 'SER_ID')
            ->get(['DLV_ID', 'DLV_SER', 'SI_WH', DB::raw("RTRIM(SER_ITMID) SER_ITMID"), 'SISCN_SERQTY']);

        $dateConfirmed = date('Y-m-d H:i:s');
        $datam = [];
        foreach ($dataSI as $r) {
            if (
                DB::table("ITH_TBL")->where("ITH_SER", $r->DLV_SER)
                ->where('ITH_FORM', "OUT-SHP-FG")->count() == 0
            ) {
                $thewh = '';
                if ($r->SI_WH == "AFWH3") {
                    $thewh = "ARSHP";
                } elseif ($r->SI_WH == "AFWH3RT") {
                    $thewh = "ARSHPRTN2";
                } else {
                    $thewh = "ARSHPRTN";
                }
                $datam[] = [
                    "ITH_ITMCD" => $r->SER_ITMID,
                    "ITH_DATE" => $dateConfirmed,
                    "ITH_FORM" => "OUT-SHP-FG",
                    "ITH_DOC" => $r->DLV_ID,
                    "ITH_QTY" => -$r->SISCN_SERQTY,
                    "ITH_WH" => $thewh,
                    "ITH_SER" => $r->DLV_SER,
                    "ITH_LUPDT" => $dateConfirmed,
                    "ITH_USRID" => $request->user_id,
                ];
            }
        }

        if (!empty($datam)) {
            try {
                DB::beginTransaction();
                $TOTAL_COLUMN = 9;
                $insert_data = collect($datam);
                $chunks = $insert_data->chunk(2000 / $TOTAL_COLUMN);
                foreach ($chunks as $chunk) {
                    DB::table('ITH_TBL')->insert($chunk->toArray());
                }

                DB::table('WMS_DLVCHK')->whereIn('dlv_id', $documents)
                    ->update([
                        'dlv_PicSend' => $request->user_id,
                        'dlv_DateSend' => $request->datetimeShip,
                        'dlv_stcfm' => 1
                    ]);

                DB::table('DLVH_TBL')->where('DLVH_ACT_TRANS', $vechicleRegNumber)
                    ->whereNull('DLVH_DRIVER_NAME')
                    ->update([
                        'DLVH_DRIVER_NAME' => $request->driverName,
                        'DLVH_CODRIVER_NAME' => $request->codriverName,
                        'gate_out_done_at' => $dateConfirmed
                    ]);
                DB::commit();

                return ['message' => 'Confirmed successfully'];
            } catch (Exception $e) {
                DB::rollBack();
                return response()->json(['message' => $e->getMessage()], 501);
            }
        } else {
            return ['message' => 'Confirmed successfully..'];
        }
    }

    function getDeliveryCheckingDetail(Request $request)
    {
        $data = DB::table('WMS_DLVCHK')
            ->leftJoin('SER_TBL', 'dlv_refno', '=', 'SER_ID')
            ->leftJoin('MITM_TBL', 'SER_ITMID', '=', 'MITM_ITMCD')
            ->where('dlv_id', base64_decode($request->doc))
            ->orderBy('SER_ITMID')
            ->get(['SER_ID', 'SER_ITMID', 'dlv_qty', DB::raw("RTRIM(MITM_ITMD1) ITMD1")]);

        return ['data' => $data];
    }

    function deleteDeliveryChecking(Request $request)
    {
        try {
            DB::beginTransaction();
            $bakDeliveryCheck = DB::table('WMS_DLVCHK')->where('dlv_id', $request->doc)
                ->where('dlv_refno', $request->id)->first();

            $affectedRows = DB::table('WMS_DLVCHK')->where('dlv_id', $request->doc)
                ->where('dlv_refno', $request->id)
                ->delete();

            if ($affectedRows) {
                DB::table('DLVCK_TBL')->where('DLVCK_TXID', $request->doc)
                    ->where('DLVCK_ITMCD', $request->itemCode)->update(['DLVCK_CNFQTY' => NULL]);

                DB::table('WMS_DLVCHK_LOGS')->insert([
                    'created_at' => date('Y-m-d H:i:s'),
                    'dlv_id' => $bakDeliveryCheck->dlv_id,
                    'dlv_itmcd' => $bakDeliveryCheck->dlv_itmcd,
                    'dlv_refno' => $bakDeliveryCheck->dlv_refno,
                    'dlv_qty' => $bakDeliveryCheck->dlv_qty,
                    'dlv_PIC' => $bakDeliveryCheck->dlv_PIC,
                    'dlv_date' => $bakDeliveryCheck->dlv_date,
                    'dlv_PicSend' => $bakDeliveryCheck->dlv_PicSend,
                    'dlv_DateSend' => $bakDeliveryCheck->dlv_DateSend,
                    'dlv_stchk' => $bakDeliveryCheck->dlv_stchk,
                    'dlv_stcfm' => $bakDeliveryCheck->dlv_stcfm,
                    'dlv_transno' => $bakDeliveryCheck->dlv_transno,
                    'created_by' => $request->user_id,
                ]);
            }
            DB::commit();
            return ['message' => 'Successfully'];
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 501);
        }
    }

    function reportGateOut(Request $request)
    {
        $data = DB::table('ITH_TBL')->leftJoin('DLVH_TBL', 'ITH_DOC', '=', 'DLVH_ID')
            ->leftJoin('DLV_TBL', 'ITH_DOC', '=', 'DLV_ID')
            ->leftJoin('MDEL_TBL', 'DLV_CONSIGN', '=', 'MDEL_DELCD')
            ->leftJoin('SER_TBL', 'ITH_SER', '=', 'SER_ID')
            ->leftJoin('MITM_TBL', 'SER_ITMID', '=', 'MITM_ITMCD')
            ->where('ITH_FORM', 'OUT-SHP-FG')
            ->where('ITH_DATE', '>=', $request->dateFrom)
            ->where('ITH_DATE', '<=', $request->dateTo)
            ->orderBy('ITH_LUPDT')
            ->orderBy('ITH_DOC')
            ->orderBy('MITM_ITMD1')
            ->groupBy('ITH_LUPDT', 'ITH_DOC', 'DLVH_ACT_TRANS', 'DLVH_DRIVER_NAME', 'MDEL_DELNM', 'MITM_ITMD1')
            ->get([
                'ITH_LUPDT',
                'ITH_DOC',
                'DLVH_ACT_TRANS',
                'DLVH_DRIVER_NAME',
                'MDEL_DELNM',
                DB::raw('RTRIM(MITM_ITMD1) ITMD1'),
            ]);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Transaction');
        $sheet->setCellValue('B1', 'Time');
        $sheet->setCellValue('C1', 'NoPol');
        $sheet->setCellValue('D1', 'Driver Name');
        $sheet->setCellValue('E1', '3rd Party');
        $sheet->setCellValue('F1', 'Document Number');
        $sheet->setCellValue('G1', 'Item Description');
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        $sheet->freezePane('A2');

        $rowAt = 2;
        $flagDate = '';
        foreach ($data as $r) {
            $sheet->setCellValue('A' . $rowAt, 'OUTGOING');
            $sheet->setCellValue('B' . $rowAt, $r->ITH_LUPDT);
            $sheet->setCellValue('C' . $rowAt, $r->DLVH_ACT_TRANS);
            $sheet->setCellValue('D' . $rowAt, $r->DLVH_DRIVER_NAME);
            $sheet->setCellValue('E' . $rowAt, $r->MDEL_DELNM);
            $sheet->setCellValue('F' . $rowAt, $r->ITH_DOC);
            $sheet->setCellValue('G' . $rowAt, $r->ITMD1);
            $rowAt++;
        }

        $sheet->getStyle('A1:G' . $rowAt - 1)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new Color('1F1812'));
        $sheet->getStyle('A1:G' . $rowAt - 1)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        foreach (range('A', 'G') as $r) {
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


        $stringjudul = "Gate-out Report from " . $request->dateFrom . " to " . $request->dateTo;
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $filename = $stringjudul;
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
    }

    public function setPriceRS($pdoc = '')
    {
        $doc = base64_decode($pdoc);
        $qry = "
            SELECT 
                DLV_ID,
                DLVQTY AS SISOQTY,
                UPPER(RTRIM(SI_ITMCD)) AS SSO2_MDLCD,
                (DLVQTY * ISNULL(MITM_NWG, 0.123)) AS NWG,
                (DLVQTY * ISNULL(MITM_GWG, 0.123)) AS GWG,
                RTRIM(ISNULL(MITM_HSCD, '')) AS MITM_HSCD,
                RTRIM(MITM_STKUOM) AS MITM_STKUOM,
                0 AS SISOQTY_X,
                RTRIM(MITM_ITMD1) AS MITM_ITMD1,
                RTRIM(MITM_ITMD2) AS MITM_ITMD2,
                SI_BSGRP,
                SI_CUSCD,
                MITM_BM,
                MITM_PPN,
                MITM_PPH,
                ISNULL(MITM_NWG, 0.123) AS MITM_NWG
            FROM (
                SELECT 
                    DLV_ID,
                    SISCN_LINENO,
                    SUM(DLV_QTY) AS DLVQTY
                FROM DLV_TBL
                INNER JOIN SISCN_TBL ON DLV_SER = SISCN_SER
                WHERE DLV_ID = ?
                GROUP BY DLV_ID, SISCN_LINENO
            ) V1
            LEFT JOIN SI_TBL ON SISCN_LINENO = SI_LINENO
            LEFT JOIN MITM_TBL ON SI_ITMCD = MITM_ITMCD
            ORDER BY SI_ITMCD
            ";

        $rs = DB::select($qry, [$doc]);
        $rs = json_decode(json_encode($rs), true);

        $qry = "
            SELECT 
                SISO_HLINE,
                SSO2_SLPRC,
                SUM(SISO_QTY) AS PLOTQTY,
                SISO_SOLINE,
                SISO_CPONO,
                MAX(SCNQT) AS SCNQT,
                RTRIM(MAX(ITMID)) AS ITMID,
                MAX(SSO2_BSGRP) AS SSO2_BSGRP,
                MAX(RTRIM(SSO2_CUSCD)) AS SSO2_CUSCD
            FROM SISO_TBL
            LEFT JOIN XSSO2 
                ON SISO_CPONO = SSO2_CPONO 
            AND SSO2_SOLNO = SISO_SOLINE
            LEFT JOIN (
                SELECT 
                    SISCN_LINENO,
                    SUM(SISCN_SERQTY) AS SCNQT,
                    UPPER(MAX(SER_ITMID)) AS ITMID
                FROM SISCN_TBL
                LEFT JOIN SER_TBL ON SISCN_SER = SER_ID
                WHERE SISCN_SER IN (
                    SELECT DLV_SER 
                    FROM DLV_TBL 
                    WHERE DLV_ID = ?
                )
                GROUP BY SISCN_LINENO
            ) VX ON SISO_HLINE = SISCN_LINENO
            WHERE 
                SISO_QTY > 0
                AND SISO_HLINE IN (
                    SELECT SISCN_LINENO 
                    FROM SISCN_TBL 
                    WHERE SISCN_SER IN (
                        SELECT DLV_SER 
                        FROM DLV_TBL 
                        WHERE DLV_ID = ?
                    )
                    GROUP BY SISCN_LINENO
                )
            GROUP BY 
                SISO_HLINE,
                SSO2_SLPRC,
                SSO2_MDLCD,
                SISO_SOLINE,
                SISO_CPONO
            ";

        $rscurrentPrice_plot = DB::select($qry, [$doc, $doc]);
        $rscurrentPrice_plot = json_decode(json_encode($rscurrentPrice_plot), true);
        // $rscurrentPrice_plot = $this->SISO_mod->select_currentPlot($doc);

        $rsPriceItemSer = [];
        $bsgrp = '';
        $cuscd = '';
        foreach ($rs as &$k) {
            $k['PLOTPRCQTY'] = 0;
            $bsgrp = $k['SI_BSGRP'];
            $cuscd = $k['SI_CUSCD'];
        }
        unset($k);
        $rsitem_iprice = []; //resume item|price|count
        foreach ($rscurrentPrice_plot as &$k) {
            if ($k['SISO_SOLINE'] == 'X') {
                $qry = "
                    SELECT
                        A.MSPR_BSGRP,
                        A.MSPR_CUSCD,
                        A.MSPR_CURCD,
                        MCUS_CUSNM,
                        UPPER(RTRIM(A.MSPR_ITMCD)) AS MSPR_ITMCD,
                        A.MSPR_BOMRV,
                        A.MSPR_EFFDT,
                        MITM_ITMD1 AS ITMCD_DESC,
                        A.MSPR_SLPRC
                    FROM SRVMEGA.PSI_MEGAEMS.dbo.MSPR_TBL A
                    LEFT JOIN SRVMEGA.PSI_MEGAEMS.dbo.MCUS_TBL
                        ON MCUS_CURCD = A.MSPR_CURCD
                    AND MCUS_CUSCD = A.MSPR_CUSCD
                    LEFT JOIN SRVMEGA.PSI_MEGAEMS.dbo.MITM_TBL
                        ON MITM_ITMCD = A.MSPR_ITMCD
                    LEFT JOIN SRVMEGA.PSI_MEGAEMS.dbo.MBSG_TBL
                        ON MBSG_BSGRP = A.MSPR_BSGRP
                    WHERE
                        A.MSPR_BSGRP = ?
                        AND A.MSPR_ITMCD = ?
                        AND A.MSPR_CUSCD = ?
                        AND A.MSPR_EFFDT = (
                            SELECT MAX(B.MSPR_EFFDT)
                            FROM SRVMEGA.PSI_MEGAEMS.dbo.MSPR_TBL B
                            WHERE B.MSPR_BSGRP = A.MSPR_BSGRP
                            AND B.MSPR_CUSCD = A.MSPR_CUSCD
                            AND B.MSPR_CURCD = A.MSPR_CURCD
                            AND B.MSPR_ITMCD = A.MSPR_ITMCD
                            AND B.MSPR_BOMRV = A.MSPR_BOMRV
                        )
                    ORDER BY
                        MSPR_BSGRP,
                        MSPR_CUSCD,
                        MSPR_CURCD,
                        MSPR_ITMCD,
                        MSPR_BOMRV,
                        MSPR_EFFDT
                    ";

                $rs_mst_price = DB::select($qry, [$bsgrp, $k['ITMID'], $cuscd]);
                $rs_mst_price = json_decode(json_encode($rs_mst_price), true);
                // $rs_mst_price = $this->XSO_mod->select_latestprice($bsgrp, $cuscd, "'" . $k['ITMID'] . "'");

                foreach ($rs_mst_price as $r) {
                    $k['SSO2_SLPRC'] = substr($r['MSPR_SLPRC'], 0, 1) == '.' ? '0' . $r['MSPR_SLPRC'] : $r['MSPR_SLPRC'];
                    $k['PLOTQTY'] = $k['SCNQT'];
                }
            } else {
                if ($k['PLOTQTY'] < $k['SCNQT']) {
                    $isfound = false;
                    foreach ($rsitem_iprice as &$i) {
                        if ($k['ITMID'] == $i['ITMID'] && $k['SSO2_SLPRC'] == $i['PRICE']) {
                            $i['COUNTER']++;
                            $isfound = true;
                        }
                    }
                    unset($i);
                    if (!$isfound) {
                        $rsitem_iprice[] = [
                            'ITMID' => $k['ITMID'],
                            'PRICE' => $k['SSO2_SLPRC'],
                            'COUNTER' => 1,
                        ];
                    }
                }
            }
        }
        unset($k);

        //1.0 filter which item use >1 price
        $rsitem_iprice_unique = [];
        foreach ($rsitem_iprice as $i) {
            $isfound = false;
            foreach ($rsitem_iprice_unique as &$u) {
                if ($i['ITMID'] == $u['ITMID']) {
                    $u['COUNTER']++;
                    $isfound = true;
                }
            }
            unset($u);
            if (!$isfound) {
                $rsitem_iprice_unique[] = [
                    'ITMID' => $i['ITMID'],
                    'COUNTER' => 1,
                ];
            }
        }
        //1.1 if it has multiprice then do not autocomplete plot
        foreach ($rscurrentPrice_plot as &$k) {
            if ($k['PLOTQTY'] < $k['SCNQT']) {
                $isfound = false;
                foreach ($rsitem_iprice_unique as $n) {
                    if ($k['ITMID'] === $n['ITMID']) {
                        if ($n['COUNTER'] > 1) {
                            $isfound = true;
                            break;
                        }
                    }
                }
                if (!$isfound) {
                    if ($k['SISO_SOLINE'] == 'X' || $k['SSO2_BSGRP'] == 'PSI1PPZIEP') {
                        $k['PLOTQTY'] = $k['SCNQT'];
                    }
                }
            }
        }
        unset($k);
        foreach ($rs as &$k) {
            foreach ($rscurrentPrice_plot as &$s) {
                $bal = $k['SISOQTY'] - $k['SISOQTY_X'];
                if ($k['SSO2_MDLCD'] === $s['ITMID'] && $bal > 0 && $s['PLOTQTY'] > 0) {
                    $qtyuse = 0;
                    if ($bal > $s['PLOTQTY']) {
                        $qtyuse = $s['PLOTQTY'];
                        $k['SISOQTY_X'] += $s['PLOTQTY'];
                        $s['PLOTQTY'] = 0;
                    } else {
                        $qtyuse = $bal;
                        $s['PLOTQTY'] -= $bal;
                        $k['SISOQTY_X'] += $bal;
                    }
                    $isfound = false;
                    foreach ($rsPriceItemSer as &$b) {
                        if ($b['SSO2_MDLCD'] == $s['ITMID'] && $b['SSO2_SLPRC'] == $s['SSO2_SLPRC']) {
                            $b['SISOQTY'] += $qtyuse;
                            $b['NWG'] += ($qtyuse * $k['MITM_NWG']);
                            $b['CIF'] = $b['SISOQTY'] * $s['SSO2_SLPRC'];
                            $isfound = true;
                            break;
                        }
                    }
                    unset($b);
                    if (!$isfound) {
                        $rsPriceItemSer[] = [
                            'DLV_ID' => $doc,
                            'SISOQTY' => $qtyuse,
                            'SSO2_SLPRC' => $s['SSO2_SLPRC'],
                            'SSO2_MDLCD' => $s['ITMID'] #
                            ,
                            'CIF' => $qtyuse * $s['SSO2_SLPRC'],
                            'NWG' => $qtyuse * $k['MITM_NWG'],
                            'MITM_HSCD' => $k['MITM_HSCD'],
                            'MITM_STKUOM' => $k['MITM_STKUOM'],
                            'SISOQTY_X' => 0,
                            'MITM_ITMD1' => $k['MITM_ITMD1'],
                            'MITM_ITMD2' => $k['MITM_ITMD2'],
                            'SISO_SOLINE' => $s['SISO_SOLINE'],
                            'SI_BSGRP' => $k['SI_BSGRP'],
                            'SI_CUSCD' => $k['SI_CUSCD'],
                            'CPO' => $s['SISO_CPONO'],
                            'BM' => $k['MITM_BM'],
                            'PPN' => $k['MITM_PPN'],
                            'PPH' => $k['MITM_PPH'],
                        ];
                    }
                    if ($k['SISOQTY'] === $k['SISOQTY_X']) {
                        break;
                    }
                }
            }
            unset($s);
        }
        unset($k);
        return $rsPriceItemSer;
    }

    public function perprice($psj, $prs)
    {
        $sj = $psj;
        $rsprice = $prs;
        $qry = "
                SELECT
                    UPPER(SER_ITMID) AS SER_ITMID,
                    SERD2_FGQTY,
                    SERD2_SER,
                    LOTNO,
                    UPPER(RTRIM(SERD2_ITMCD)) AS SERD2_ITMCD,
                    ISNULL(MITMGRP_ITMCD,'') AS ITMGR,
                    CEILING(SERD2_QTPER * 2) / 2 AS SERD2_QTPER
                FROM DLV_TBL
                INNER JOIN (
                    SELECT
                        SERD2_SER,
                        SERD2_ITMCD,
                        SERD2_FGQTY,
                        SUM(SERD2_QTY) / SERD2_FGQTY AS SERD2_QTPER,
                        MAX(SERD2_LOTNO) AS LOTNO
                    FROM SERD2_TBL
                    GROUP BY SERD2_SER, SERD2_ITMCD, SERD2_FGQTY
                ) V1 ON DLV_SER = SERD2_SER
                LEFT JOIN SER_TBL ON DLV_SER = SER_ID
                LEFT JOIN VFG_AS_BOM ON SERD2_ITMCD = PWOP_BOMPN
                LEFT JOIN MITMGRP_TBL ON SERD2_ITMCD = MITMGRP_ITMCD_GRD
                WHERE DLV_ID = ?
                AND PWOP_BOMPN IS NULL
                ORDER BY DLV_SER
                ";
        $rsrm = DB::select($qry, [$sj]);
        $rsrm = json_decode(json_encode($rsrm), true);

        $qry = "
            SELECT
                UPPER(SER_ITMID) AS SER_ITMID,
                SERD2_FGQTY,
                DLV_SER AS SERD2_SER,
                LOTNO,
                UPPER(RTRIM(SERD2_ITMCD)) AS SERD2_ITMCD,
                ISNULL(MITMGRP_ITMCD,'') AS ITMGR,
                SERD2_QTPER
            FROM DLV_TBL
            LEFT JOIN SERML_TBL ON DLV_SER = SERML_NEWID
            INNER JOIN (
                SELECT
                    SERD2_SER,
                    SERD2_ITMCD,
                    SERD2_FGQTY,
                    SUM(SERD2_QTY) / SERD2_FGQTY AS SERD2_QTPER,
                    MAX(SERD2_LOTNO) AS LOTNO
                FROM SERD2_TBL
                GROUP BY SERD2_SER, SERD2_ITMCD, SERD2_FGQTY
            ) V1 ON SERML_COMID = SERD2_SER
            LEFT JOIN SER_TBL ON DLV_SER = SER_ID
            LEFT JOIN MITMGRP_TBL ON SERD2_ITMCD = MITMGRP_ITMCD_GRD
            WHERE DLV_ID = ?
            ORDER BY DLV_SER
            ";
        $rssub = DB::select($qry, [$sj]);
        $rssub = json_decode(json_encode($rssub), true);

        $qry = "
            SELECT DLV_SER, SER_REFNO
            FROM DLV_TBL
            LEFT JOIN SERD2_TBL ON DLV_SER = SERD2_SER
            LEFT JOIN SER_TBL ON DLV_SER = SER_ID
            WHERE DLV_ID = ?
            AND SERD2_SER IS NULL
            ORDER BY DLV_SER
            ";
        $rsnull =  DB::select($qry, [$sj]);
        $rsnull = json_decode(json_encode($rsnull), true);

        foreach ($rsnull as $r) {
            $rscomb_d = DB::table('SERC_TBL')->where('SERC_NEWID', $r['DLV_SER'])
                ->select('SERC_COMID')->get();
            $rscomb_d = json_decode(json_encode($rscomb_d), true);

            $serlist = [];
            if (count($rscomb_d)) {
                foreach ($rscomb_d as $n) {
                    $serlist[] = $n['SERC_COMID'];
                }

                if (count($serlist) > 0) {
                    $rscom = $this->select_dlv_ser_rm_byreff_forpost($serlist);
                    $rsrm = array_merge($rsrm, $rscom);
                }
            } else {
                $rscomb_d = DB::table('SERC_TBL')->where('SERC_NEWID', $r['SER_REFNO'])
                    ->select('SERC_COMID')->get();
                foreach ($rscomb_d as $n) {
                    $serlist[] = $n['SERC_COMID'];
                }
                if (count($serlist) > 0) {
                    $rscom = $this->select_dlv_ser_rm_byreff_forpost($serlist);
                    $rsrm = array_merge($rsrm, $rscom);
                }
            }
        }
        $rsrm = array_merge($rsrm, $rssub);
        $result = [];
        foreach ($rsprice as &$r) {
            foreach ($rsrm as &$ra) {
                if ($r['SSO2_MDLCD'] == $ra['SER_ITMID']) {
                    if (intval($ra['SERD2_FGQTY']) > 0) {
                        $thereffno = $ra['SERD2_SER'];
                        $osreq = $r['SISOQTY'] - $r['SISOQTY_X'];
                        $plot = 0;
                        if ($osreq > 0) {
                            foreach ($rsrm as &$x) {
                                if ($thereffno == $x['SERD2_SER']) {
                                    if ($osreq > $x['SERD2_FGQTY']) {
                                        $plot = $x['SERD2_FGQTY'];
                                        $x['SERD2_FGQTY'] = 0;
                                    } else {
                                        $plot = $osreq;
                                        $x['SERD2_FGQTY'] -= $osreq;
                                    }
                                    $x['PRICEFOR'] = $r['SSO2_SLPRC'];
                                    $x['QTYFOR'] = $plot;
                                    $x['PRICEGROUP'] = $r['SSO2_SLPRC'] * $r['SISOQTY'];
                                    $result[] = $x;
                                }
                            }
                            unset($x);
                        }
                        $r['SISOQTY_X'] += $plot;
                    }
                }
            }
            unset($ra);
        }
        unset($r);

        $result_resume = [];
        foreach ($result as $r) {
            $isfound = false;
            foreach ($result_resume as &$v) {
                if (
                    $v['RITEMCD'] == $r['SERD2_ITMCD'] && $v['RLOTNO'] == $r['LOTNO']
                    && $v['RASSYCODE'] == $r['SER_ITMID'] && $v['RPRICEGROUP'] == round($r['PRICEGROUP'], 2)
                ) {
                    $v['RQTY'] += ($r['SERD2_QTPER'] * $r['QTYFOR']);
                    $isfound = true;
                    break;
                }
            }
            if (!$isfound) {
                $result_resume[] = [
                    'RASSYCODE' => $r['SER_ITMID'],
                    'RPRICEGROUP' => round($r['PRICEGROUP'], 2),
                    'RITEMCD' => $r['SERD2_ITMCD'],
                    'RITEMCDGR' => $r['ITMGR'],
                    'RLOTNO' => $r['LOTNO'],
                    'RQTY' => $r['SERD2_QTPER'] * $r['QTYFOR'],
                ];
            }
            unset($v);
        }
        return $result_resume;
    }

    function select_dlv_ser_rm_byreff_forpost($serlist)
    {
        $placeholders = implode(',', array_fill(0, count($serlist), '?'));
        $qry = "
                        SELECT
                            SERD2_SER,
                            LOTNO,
                            UPPER(RTRIM(SERD2_ITMCD)) AS SERD2_ITMCD,
                            ISNULL(MITMGRP_ITMCD,'') AS ITMGR,
                            SERD2_QTPER,
                            SERD2_FGQTY,
                            SERD2_FGQTY AS B4MINS,
                            0 AS PRICEFOR,
                            0 AS QTYFOR,
                            CASE
                                WHEN Z.SER_ITMID IS NOT NULL THEN Z.SER_ITMID
                                ELSE ISNULL(Y.SER_ITMID, X.SER_ITMID)
                            END AS SER_ITMID,
                            0 AS PRICEGROUP
                        FROM (
                            SELECT
                                SERD2_SER,
                                SERD2_ITMCD,
                                SERD2_FGQTY,
                                SUM(SERD2_QTY) / SERD2_FGQTY AS SERD2_QTPER,
                                MAX(SERD2_LOTNO) AS LOTNO
                            FROM SERD2_TBL
                            GROUP BY SERD2_SER, SERD2_ITMCD, SERD2_FGQTY
                        ) V1
                        LEFT JOIN SER_TBL X ON SERD2_SER = X.SER_ID
                        LEFT JOIN MITMGRP_TBL ON SERD2_ITMCD = MITMGRP_ITMCD_GRD
                        LEFT JOIN SERC_TBL ON SERD2_SER = SERC_COMID
                        LEFT JOIN SER_TBL Y ON SERC_NEWID = Y.SER_ID
                        LEFT JOIN SER_TBL Z ON SERC_NEWID = Z.SER_REFNO
                        WHERE SERD2_SER IN ($placeholders)
                        ORDER BY SERD2_SER
                        ";

        $rscom = DB::select($qry, $serlist);
        $rscom = json_decode(json_encode($rscom), true);
        return $rscom;
    }
}
