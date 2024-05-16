<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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
}
