<?php

namespace App\Http\Controllers;

use App\Models\ProcessMaster;
use Illuminate\Http\Request;
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
}
