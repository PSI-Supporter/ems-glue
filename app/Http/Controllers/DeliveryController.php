<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

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

    function reportKonversiBahanBaku(Request $request) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('Q1', 'LAMPIRAN');
        $sheet->setCellValue('Q2', 'Surat Kepala KPPBC Tipe Madya Pabean A Bekasis');
        $sheet->setCellValue('Q3', 'Nomor     :');
        $sheet->setCellValue('R3', 'S-1488/KBC.0804/2024');
        $sheet->setCellValue('Q4', 'Tanggal');
        $sheet->setCellValue('R4', '-');
        $sheet->setCellValue('A6', 'TABEL PERHITUNGAN KONVERSI BAHAN BAKU');
        $sheet->mergeCells('A6:V6', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->getStyle('A6')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A6')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
       
        $sheet->setCellValue('A8', '1. Nama Barang : Assembled PCB');
        $sheet->setCellValue('A9', '2. Jumlah Barang :');
        

        $sheet->setCellValue('A11', 'BARANG DAN/ATAU BAHAN BAKU');
        $sheet->mergeCells('A11:G11', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->setCellValue('I11', 'HARGA');
        $sheet->mergeCells('I11:J11', $sheet::MERGE_CELL_CONTENT_HIDE);
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
        $sheet->setCellValue('O12', 'BM');
        $sheet->mergeCells('O12:P13', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->getStyle('O12')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('O12')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue('O14', 'Tarif(%)');
        $sheet->setCellValue('P14', 'Nilai (Rp)');
        $sheet->setCellValue('Q12', 'PPN');
        $sheet->mergeCells('Q12:R13', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->getStyle('Q12')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('Q12')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue('Q14', 'Tarif(%)');
        $sheet->setCellValue('R14', 'Nilai (Rp)');

        $sheet->setCellValue('S12', 'PPnBM');
        $sheet->mergeCells('S12:T13', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->getStyle('S12')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('S12')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue('S14', 'Tarif(%)');
        $sheet->setCellValue('T14', 'Nilai (Rp)');

        $sheet->setCellValue('U12', 'PPh Ps.22');
        $sheet->mergeCells('U12:V13', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->getStyle('U12')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('U12')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue('U14', 'Tarif(%)');
        $sheet->setCellValue('V14', 'Nilai (Rp)');

        $sheet->mergeCells('A12:A14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->getStyle('A12')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A12')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->mergeCells('B12:B14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->getStyle('B12')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('B12')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->mergeCells('C12:C14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->getStyle('C12')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('C12')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->mergeCells('D12:D14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->getStyle('D12')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('D12')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->mergeCells('E12:E14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->getStyle('E12')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('E12')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->mergeCells('F12:F14', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->getStyle('F12')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('F12')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        

        foreach (range('A', 'R') as $r) {
            $sheet->getColumnDimension($r)->setAutoSize(true);
        }
        $sheet->freezePane('A2');

        $writer = new Mpdf($spreadsheet);

        $stringjudul = "Konversi Pemakaian Bahan Baku " . $request->doc;
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $filename = $stringjudul;
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
    }
}
