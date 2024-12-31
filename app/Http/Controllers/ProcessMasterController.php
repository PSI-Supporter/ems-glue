<?php

namespace App\Http\Controllers;

use App\Models\ProcessMaster;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProcessMasterController extends Controller
{
    function import()
    {
        $reader = IOFactory::createReader(ucfirst('Xlsx'));
        $spreadsheet = $reader->load(public_path('assets/CT.xlsx'));
        $sheet = $spreadsheet->getActiveSheet();
        $rowIndex = 2;
        $data = [];
        while (!empty($sheet->getCell([1, $rowIndex])->getCalculatedValue())) {
            $_CT = trim($sheet->getCell([6, $rowIndex])->getCalculatedValue());
            if (is_numeric($_CT) && strlen($_CT) > 0) {
                $data[] = [
                    'line_code' => trim($sheet->getCell([1, $rowIndex])->getCalculatedValue()),
                    'assy_code' => trim($sheet->getCell([2, $rowIndex])->getCalculatedValue()),
                    'model_code' => trim($sheet->getCell([3, $rowIndex])->getCalculatedValue()),
                    'model_type' => trim($sheet->getCell([4, $rowIndex])->getCalculatedValue()),
                    'process_code' => trim($sheet->getCell([5, $rowIndex])->getCalculatedValue()),
                    'cycle_time' => $_CT,
                    'created_by' => '1210034',
                ];
            }
            $rowIndex++;
        }

        foreach (array_chunk($data, (1500 / 7) - 2) as $chunk) {
            ProcessMaster::insert($chunk);
        }

        return ['message' => 'go ahead', ['data' => $data]];
    }

    function getCycleTime(Request $request)
    {
        $data = ProcessMaster::select('cycle_time')
            ->where('assy_code', $request->assy_code)
            ->where('process_code', $request->process_code)
            ->where('line_code', $request->line_code)
            ->whereDate('created_at', '<=', $request->production_date)
            ->orderBy('created_at', 'desc')->first();
        return ['data' => $data];
    }

    function getHistory(Request $request)
    {
        $data = ProcessMaster::select('*')
            ->where($request->searchBy, $request->searchValue)
            ->orderBy('created_at', 'asc')
            ->get();
        return ['data' => $data];
    }

    function save(Request $request)
    {
        $data = $request->data;
        $tobeSaved = [];
        foreach ($data['master'] as $r) {
            $tobeSaved[] = [
                'line_code' => $r['line_code'],
                'assy_code' => $r['assy_code'],
                'cycle_time' => (float)$r['cycle_time'],
                'model_code' => $r['model_code'],
                'model_type' => $r['model_type'],
                'process_code' => $r['process_code'],
                'created_by' => $data['user_id'],
                'created_at' => date('Y-m-d H:i:s'),
                'valid_date_time' => $data['valid_from'],
            ];
        }

        try {
            DB::beginTransaction();

            $insert_data = collect($tobeSaved);
            $chunks = $insert_data->chunk(2000 / 9);
            foreach ($chunks as $chunk) {
                ProcessMaster::insert($chunk->toArray());
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 400);
        }
        return ['message' => 'Saved successfully', 'data' => $tobeSaved];
    }

    function getLine()
    {
        $data = DB::table('process_masters')->select(DB::raw('UPPER(line_code) line_code'))
            ->whereNull('deleted_at')
            ->groupBy('line_code')->orderBy('line_code')->get();
        return ['data' => $data];
    }

    function search(Request $request)
    {
        $data = [];
        try {
            $dataInput = $request->json()->all();

            $uniqueAssyCodeList = [];

            foreach ($dataInput['detail'] as $r) {
                if (!in_array($r['item_code'], $uniqueAssyCodeList)) {
                    $uniqueAssyCodeList[] = $r['item_code'];
                }
            }


            if ($dataInput['detail']) {
                if (is_array($dataInput['detail'])) {
                    $data = DB::table('process_masters')
                        ->whereNull('deleted_at')
                        ->where('line_code', $dataInput['line_code'])
                        ->whereIn('assy_code', $uniqueAssyCodeList)
                        ->whereDate('valid_date_time', '<=', $dataInput['production_date'])
                        ->get(['assy_code', 'cycle_time']);
                }
            }
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }

        return ['data' => $data];
    }
}
