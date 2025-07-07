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

        $data = [];
        if ($request->show_deleted) {
            if ($request->show_deleted == 'Y') {
                $data = DB::table('process_masters')->where($request->searchBy, $request->searchValue)
                    ->orderBy('process_seq', 'asc')
                    ->orderBy('created_at', 'asc')
                    ->get();
            } else {
                $data = ProcessMaster::select('*')
                    ->where($request->searchBy, $request->searchValue)
                    ->orderBy('process_seq', 'asc')
                    ->orderBy('created_at', 'asc')
                    ->get();
            }
        } else {
            $data = ProcessMaster::select('*')
                ->where($request->searchBy, $request->searchValue)
                ->orderBy('process_seq', 'asc')
                ->orderBy('created_at', 'asc')
                ->get();
        }


        return ['data' => $data];
    }

    function save(Request $request)
    {
        $data = $request->data;
        $tobeSaved = [];
        foreach ($data['master'] as $r) {
            $_validated_valid = NULL;
            $hour = substr($data['valid_from'], 11, 2);

            if ($hour === '24') {
                // Ganti jam jadi 00, sisanya tetap
                $_validated_valid = substr($data['valid_from'], 0, 11) . '00' . substr($data['valid_from'], -6);
            }

            $tobeSaved[] = [
                'line_code' => $r['line_code'],
                'assy_code' => $r['assy_code'],
                'cycle_time' => (float)$r['cycle_time'],
                'model_code' => $r['model_code'],
                'model_type' => $r['model_type'],
                'process_code' => $r['process_code'],
                'created_by' => $data['user_id'],
                'created_at' => date('Y-m-d H:i:s'),
                'valid_date_time' => $_validated_valid,
                'process_seq' => $r['process_seq'],
                'line_category' => $r['line_category'],
            ];
        }

        try {
            DB::beginTransaction();

            $insert_data = collect($tobeSaved);
            $chunks = $insert_data->chunk(2000 / 11);
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
                        ->orderBy('valid_date_time', 'desc')
                        ->get(['assy_code', 'cycle_time', 'process_code', 'line_category']);
                }
            }
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }

        return ['data' => $data];
    }

    function getByLine(Request $request)
    {
        $dataFiltered = DB::table('process_masters')->whereNull('deleted_at')
            ->where('line_code', $request->line_code)
            ->select(
                'assy_code',
                'model_code',
                'model_type',
                'valid_date_time',
                'process_code',
                'cycle_time'
            );

        $data_latest = DB::table('process_masters')
            ->whereNull('deleted_at')
            ->where('line_code', $request->line_code)
            ->groupBy('assy_code', 'process_code')
            ->select('assy_code', DB::raw('MAX(valid_date_time) lts_time'), 'process_code');

        $data = DB::query()->fromSub($dataFiltered, 'v1')
            ->joinSub($data_latest, 'v2', function ($join) {
                $join->on('v1.assy_code', '=', 'v2.assy_code')
                    ->on('v1.process_code', '=', 'v2.process_code')
                    ->on('valid_date_time', '=', 'lts_time');
            })
            ->orderBy('v1.model_code')
            ->orderBy('v1.assy_code')
            ->groupBy(
                'v1.assy_code',
                'v1.model_code',
                'model_type',
            )
            ->get([
                'v1.assy_code',
                'v1.model_code',
                DB::raw("MAX(CASE WHEN v1.process_code = 'SMT-A' OR v1.process_code = 'SMT-HW' THEN cycle_time END) side_a"),
                DB::raw("MAX(CASE WHEN v1.process_code = 'SMT-B' THEN cycle_time END) side_b"),
                DB::raw("MAX(CASE WHEN v1.process_code = 'SMT-A' OR v1.process_code = 'SMT-HW' THEN valid_date_time END) side_a_time"),
                DB::raw("MAX(CASE WHEN v1.process_code = 'SMT-B' THEN valid_date_time END) side_b_time"),
                'model_type',
            ]);
        return ['data' => $data];
    }

    function update(Request $request)
    {
        $affectedRow = DB::table('process_masters')->where('id', base64_decode($request->id))
            ->update([
                'process_seq' => $request->process_seq,
                'line_category' => $request->line_category,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $request->user_id
            ]);
        return ['message' => $affectedRow ? 'Updated successfully' : 'Could not be updated'];
    }
}
