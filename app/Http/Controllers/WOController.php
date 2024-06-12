<?php

namespace App\Http\Controllers;

use App\Models\ProductionInput;
use App\Models\ProductionTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class WOController extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
    }

    function getOutstanding(Request $request)
    {
        $data = DB::table('XWO')->select('PDPP_WONO', 'PDPP_WORQT', 'PDPP_BOMRV')
            ->where('PDPP_MDLCD', $request->item_code)
            ->where('PDPP_COMFG', 0)
            ->whereRaw("PDPP_WORQT!=PDPP_GRNQT");

        return ['data' => $data->get()];
    }

    function getProcess(Request $request)
    {
        $data = DB::table('VCIMS_MBO2_TBL')->select(DB::raw('RTRIM(MBO2_PROCD) MBO2_PROCD'), 'MBO2_SEQNO')
            ->where('MBO2_MDLCD', $request->item_code)
            ->where('MBO2_BOMRV', $request->bomRev)
            ->orderBy('MBO2_SEQNO');
        return ['data' => $data->get()];
    }

    function saveOutput(Request $request)
    {
        $data = $request->data;
        $tobeSaved = [];
        $message = '';
        try {

            $ProcessMaster = DB::table('VCIMS_MBO2_TBL')->select(DB::raw('RTRIM(MBO2_PROCD) MBO2_PROCD'), 'MBO2_SEQNO', DB::raw("0 total_qty"))
                ->where('MBO2_MDLCD', $data['item_code'])
                ->where('MBO2_BOMRV', $data['item_bom_rev'])
                ->orderBy('MBO2_SEQNO')->get();

            // validate input vs lot size
            // .query saved entry of input
            $ProductionSavedInputs = DB::table('production_inputs')
                ->whereNull('deleted_at')
                ->where('wo_code', $data['wo_code'])
                ->where('process_code', $data['process_code'])
                ->select('production_date', 'shift_code', 'line_code', 'input_qty')->get();

            $savedInputTotal = 0;
            // .#1            

            foreach ($ProductionSavedInputs as $r) {
                if (
                    $r->production_date == $data['production_date']
                    && $r->shift_code == $data['shift_code']
                    && $r->line_code == $data['line_code']
                ) {
                } else {
                    $savedInputTotal += $r->input_qty;
                }
            }

            $_currentContextInput = $savedInputTotal + $data['input_qty'];

            if ($_currentContextInput > $data['wo_size']) {
                return response()->json([
                    'message' => 'Input greater than lot size ',
                    'data' => $savedInputTotal . ' + ' . $data['input_qty'] . '>' . $data['wo_size']
                ], 400);
            }
            // end of validation

            // validate previous process output
            $savedRows = DB::table("production_output")->select('process_code', 'process_seq', DB::raw("SUM(ok_qty)+SUM(ng_qty) as total_qty"))
                ->where('wo_code', $data['wo_code'])
                ->groupBy('process_code', 'process_seq')
                ->orderBy('process_seq')->get();
            if (count($savedRows) === 0 && $data['process_seq'] != 1) {
                return response()->json(['message' => 'please start from process 1'], 400);
            }

            // .plot saved qty to master
            foreach ($ProcessMaster as &$m) {
                foreach ($savedRows as $s) {
                    if ($s->process_code === $m->MBO2_PROCD) {
                        $m->total_qty = $s->total_qty;
                        break;
                    }
                }
            }
            unset($m);


            // .query saved entry of output
            $ProductionSavedOutputs = DB::table('production_output')
                ->whereNull('deleted_at')
                ->where('wo_code', $data['wo_code'])
                ->where('process_code', $data['process_code'])
                ->select('production_date', 'shift_code', 'line_code', 'ok_qty', 'ng_qty')->get();

            $savedOutputotal = 0;

            foreach ($ProductionSavedOutputs as $r) {
                if (
                    $r->production_date == $data['production_date']
                    && $r->shift_code == $data['shift_code']
                    && $r->line_code == $data['line_code']
                ) {
                } else {
                    $savedOutputotal += ($r->ok_qty + $r->ng_qty);
                }
            }

            // .total output current request input
            $_TotalCurrentInput = 0;
            foreach ($data['output'] as $r) {
                $_TotalCurrentInput += ($r['outputOK'] + $r['outputNG']);
            }

            $_currentContextOutput = $savedOutputotal + $_TotalCurrentInput;

            // is {context current entry of output} greater than {context entry of input} per process
            if ($_currentContextOutput > $_currentContextInput) {
                return response()->json([
                    'message' => 'output of production > input-qty',
                    'data' => $_currentContextOutput . '>' . $_currentContextInput
                ], 400);
            }

            foreach ($ProcessMaster as $r) {
                if ($r->MBO2_SEQNO == 1 && $_TotalCurrentInput > $data['wo_size']) {
                    return response()->json(['message' => 'output of production > lot size'], 400);
                }

                if ($r->MBO2_PROCD === $data['process_code']) { // iterasi dari atas ke bawah
                    $_seq = $r->MBO2_SEQNO;
                    if ($_seq != 1) {
                        foreach ($ProcessMaster as $_s) { // iterasi dari atas ke bawah (lagi) dengan batasan
                            if ($_s->MBO2_SEQNO < $_seq) {
                                if ($_s->total_qty < $_TotalCurrentInput) {
                                    return response()->json(['message' => 'output of process #' . $_seq . ' > #' . $_s->MBO2_SEQNO], 400);
                                }

                                // is {context entry of input} > {output of previous process}
                                if ($_currentContextInput > $_s->total_qty) {
                                    return response()->json(['message' => 'input of process #' . $_seq . ' > output of #' . $_s->MBO2_SEQNO], 400);
                                }
                            }
                        }
                    }
                    break;
                }
            }
            // end of validation


            DB::beginTransaction();

            $countRowsInput = DB::table('production_inputs')
                ->where('wo_code', $data['wo_code'])
                ->where('production_date', $data['production_date'])
                ->where('shift_code', $data['shift_code'])
                ->where('line_code', $data['line_code'])
                ->where('process_code', $data['process_code'])
                ->count();
            if ($countRowsInput) {
                DB::table('production_inputs')
                    ->where('wo_code', $data['wo_code'])
                    ->where('production_date', $data['production_date'])
                    ->where('shift_code', $data['shift_code'])
                    ->where('line_code', $data['line_code'])
                    ->where('process_code', $data['process_code'])
                    ->update([
                        'input_qty' => $data['input_qty'],
                        'updated_at' => date('Y-m-d H:i:s'),
                        'updated_by' => $data['user_id'],
                    ]);
            } else {
                ProductionInput::create([
                    'created_by' => $data['user_id'],
                    'wo_code' => $data['wo_code'],
                    'item_code' => $data['item_code'],
                    'production_date' => $data['production_date'],
                    'shift_code' => $data['shift_code'],
                    'line_code' => $data['line_code'],
                    'process_code' => $data['process_code'],
                    'input_qty' => $data['input_qty'],
                    'process_seq' => $data['process_seq'],
                ]);
            }

            foreach ($data['output'] as $r) {
                $countRows = DB::table("production_output")
                    ->where('wo_code', $data['wo_code'])
                    ->where('process_code', $data['process_code'])
                    ->where('item_code', $data['item_code'])
                    ->where('running_at', $r['output_at'])
                    ->where('line_code', strtoupper($data['line_code']))
                    ->count();

                if ($countRows) {
                    DB::table("production_output")
                        ->where('wo_code', $data['wo_code'])
                        ->where('process_code', $data['process_code'])
                        ->where('running_at', $r['output_at'])
                        ->where('line_code', strtoupper($data['line_code']))
                        ->update([
                            'ok_qty' => $r['outputOK'],
                            'ng_qty' => $r['outputNG'],
                            'updated_by' => $data['user_id'],
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                } else {
                    $tobeSaved[] = [
                        'created_by' => $data['user_id'],
                        'created_at' => date('Y-m-d H:i:s'),
                        'wo_code' => $data['wo_code'],
                        'item_code' => $data['item_code'],
                        'shift_code' => $data['shift_code'],
                        'production_date' => $data['production_date'],
                        'line_code' => strtoupper($data['line_code']),
                        'process_code' => $data['process_code'],
                        'process_seq' => $data['process_seq'],
                        'running_at' => $r['output_at'],
                        'ok_qty' => $r['outputOK'],
                        'ng_qty' => $r['outputNG'],
                        'cycle_time' => $data['cycle_time'],
                    ];
                }
            }

            if (!empty($tobeSaved)) {
                DB::table("production_output")->insert($tobeSaved);
            }

            DB::commit();

            return [
                'message' => 'Saved successfully', '$savedRows' => $savedRows,
                '$_TotalCurrentInput' => $_TotalCurrentInput,
                '$ProcessMaster' => $ProcessMaster
            ];
        } catch (Exception $e) {
            $message = $e->getMessage();
            DB::rollBack();
            return response()->json(['message' => $message], 400);
        }
    }

    function saveDowntime(Request $request)
    {
        $data = $request->data;
        $tobeSaved = [];
        $message = '';
        try {

            $productionData = DB::table('production_output')
                ->leftJoin('production_inputs', function ($join) {
                    $join->on('production_output.wo_code', '=', 'production_inputs.wo_code')
                        ->on('production_output.production_date', '=', 'production_inputs.production_date')
                        ->on('production_output.shift_code', '=', 'production_inputs.shift_code')
                        ->on('production_output.line_code', '=', 'production_inputs.line_code')
                        ->on('production_output.process_code', '=', 'production_inputs.process_code');
                })
                ->select('production_output.shift_code', 'production_output.wo_code', DB::raw('MAX(input_qty)*max(cycle_time)/3600 as working_time'),)
                ->where('production_output.line_code', strtoupper($data['line_code']))
                ->where('production_output.production_date', $data['production_date'])
                ->groupBy('production_output.shift_code', 'production_output.wo_code');

            $productionDataFinal = DB::query()->fromSub($productionData, 'v1')
                ->select('shift_code', DB::raw('SUM(working_time) working_time_total'))
                ->groupBy('shift_code')
                ->get();

            $resumeDowntimeHour = [];
            foreach ($data['downtimeMinute'] as $r) {
                $isFound = false;
                foreach ($resumeDowntimeHour as &$s) {
                    if ($s['shift_code'] == $r['shift_code']) {
                        $s['downtime'] += ($r['req_minutes'] / 60);
                        $isFound = true;
                        break;
                    }
                }
                unset($s);

                if (!$isFound) {
                    $resumeDowntimeHour[] = [
                        'shift_code' => $r['shift_code'],
                        'downtime' => ($r['req_minutes'] / 60)
                    ];
                }
            }

            foreach ($productionDataFinal as &$r) {
                foreach ($data['productionTime'] as $i) {
                    if ($i['shift_code'] == $r->shift_code) {
                        $r->working_hour = $i['working_hours'];
                        break;
                    }
                }
                foreach ($resumeDowntimeHour as $k) {
                    if ($k['shift_code'] == $r->shift_code) {
                        $r->working_time_total += $k['downtime'];
                        break;
                    }
                }
                $r->working_time_total = round($r->working_time_total, 2);
            }
            unset($r);

            if (count($productionDataFinal) === 0) {
                return response()->json(['message' => 'There is no output'], 400);
            }


            foreach ($productionDataFinal as $r) {
                $shouldBlock = true;
                foreach ($resumeDowntimeHour as $d) {
                    if ($r->shift_code === $d['shift_code'] && $d['downtime'] == 0) {
                        $shouldBlock = false;
                        break;
                    }
                }
                if ($r->working_hour != $r->working_time_total && $shouldBlock) {
                    return response()->json([
                        'message' => 'Working Hours vs (Actual Working Hour + Downtime) should be balance [' . $r->working_hour . ' != ' . $r->working_time_total . '] '
                    ], 400);
                }
            }


            DB::beginTransaction();

            foreach ($data['downtimeMinute'] as $r) {
                $countRows = DB::table("production_downtime")
                    ->where('production_date', $data['production_date'])
                    ->where('shift_code', $r['shift_code'])
                    ->where('line_code', strtoupper($data['line_code']))
                    ->where('downtime_code', $r['downtime_code'])
                    ->count();

                if ($countRows) {
                    DB::table("production_downtime")
                        ->where('production_date', $data['production_date'])
                        ->where('shift_code', $r['shift_code'])
                        ->where('line_code', strtoupper($data['line_code']))
                        ->where('downtime_code', $r['downtime_code'])
                        ->update([
                            'updated_by' => $data['user_id'],
                            'req_minutes' => $r['req_minutes'],
                        ]);
                } else {
                    $tobeSaved[] = [
                        'created_by' => $data['user_id'],
                        'created_at' => date('Y-m-d H:i:s'),
                        'shift_code' => $r['shift_code'],
                        'production_date' => $data['production_date'],
                        'line_code' => strtoupper($data['line_code']),
                        'downtime_code' => $r['downtime_code'],
                        'req_minutes' => $r['req_minutes'],
                    ];
                }
            }

            if (!empty($tobeSaved)) {
                DB::table("production_downtime")->insert($tobeSaved);
            }

            $tobeSaved = [];

            foreach ($data['productionTime'] as $r) {
                $countRows = DB::table("production_times")
                    ->where('production_date', $data['production_date'])
                    ->where('shift_code', $r['shift_code'])
                    ->where('line_code', strtoupper($data['line_code']))
                    ->count();

                if ($countRows) {
                    DB::table("production_times")
                        ->where('production_date', $data['production_date'])
                        ->where('shift_code', $r['shift_code'])
                        ->where('line_code', strtoupper($data['line_code']))
                        ->update([
                            'updated_by' => $data['user_id'],
                            'working_hours' => $r['working_hours'],
                        ]);
                } else {
                    $tobeSaved[] = [
                        'created_by' => $data['user_id'],
                        'created_at' => date('Y-m-d H:i:s'),
                        'shift_code' => $r['shift_code'],
                        'production_date' => $data['production_date'],
                        'working_hours' => (float)$r['working_hours'],
                        'line_code' => strtoupper($data['line_code']),
                    ];
                }
            }

            if (!empty($tobeSaved)) {
                DB::table("production_times")->insert($tobeSaved);
            }

            DB::commit();

            return [
                'message' => 'Saved successfully', 'data' => $tobeSaved,
                'productionTime' => $productionDataFinal,
                'resumeDowntimeHour' => $resumeDowntimeHour,
                '$productionData' => $productionData->get()
            ];
        } catch (Exception $e) {
            $message = $e->getMessage();
            DB::rollBack();
            return response()->json(['message' => $message], 400);
        }
    }

    function resume(Request $request)
    {
        $output = DB::table('production_output')
            ->select(
                'line_code',
                'process_code',
                DB::raw("SUM(ok_qty) ok_qty"),
                DB::raw("SUM(ng_qty) ng_qty"),
                DB::raw("MAX(production_date) max_production_date")
            )
            ->where('wo_code', $request->wo_code)
            ->whereNull('deleted_at')
            ->groupBy('line_code', 'process_code', 'process_seq')->orderBy('process_seq');
        $process = DB::table('production_output')
            ->select('process_code')
            ->where('wo_code', $request->wo_code)
            ->whereNull('deleted_at')
            ->groupBy('process_code', 'process_seq')->orderBy('process_seq');
        $lines = DB::table('production_output')
            ->select('line_code')
            ->where('wo_code', $request->wo_code)
            ->whereNull('deleted_at')
            ->groupBy('line_code')->orderBy('line_code');

        return [
            'data' => $process->get(),
            'lines' => $lines->get(),
            'output' => $output->get()
        ];
    }

    function getOutput(Request $request)
    {
        $data = DB::table('production_output')
            ->select('running_at', 'ok_qty', 'ng_qty')
            ->where('wo_code', $request->wo_code)
            ->where('process_code', $request->process_code)
            ->where('line_code', $request->line_code)
            ->where('shift_code', $request->shift_code)
            ->where('production_date', $request->production_date)
            ->whereNull('deleted_at')
            ->orderBy('running_at');

        $dataInputPCB = DB::table('production_inputs')
            ->select('input_qty')
            ->where('wo_code', $request->wo_code)
            ->where('process_code', $request->process_code)
            ->where('line_code', $request->line_code)
            ->where('shift_code', $request->shift_code)
            ->where('production_date', $request->production_date)
            ->whereNull('deleted_at')
            ->orderBy('input_qty', 'desc')->first();
        return ['data' => $data->get(), 'inputPCB' => $dataInputPCB->input_qty ?? 0];
    }

    function getDownTime(Request $request)
    {

        $downTime = DB::table('production_downtime')
            ->select('shift_code', 'downtime_code', 'req_minutes')
            ->where('line_code', $request->line_code)
            ->where('production_date', $request->production_date)
            ->whereNull('deleted_at')
            ->orderBy('downtime_code')->get();

        $productionData = DB::table('production_output')
            ->select('production_output.shift_code', 'production_output.wo_code', DB::raw('MAX(input_qty)*max(cycle_time)/3600 as working_time'))
            ->leftJoin('production_inputs', function ($join) {
                $join->on('production_output.wo_code', '=', 'production_inputs.wo_code')
                    ->on('production_output.production_date', '=', 'production_inputs.production_date')
                    ->on('production_output.shift_code', '=', 'production_inputs.shift_code')
                    ->on('production_output.line_code', '=', 'production_inputs.line_code')
                    ->on('production_output.process_code', '=', 'production_inputs.process_code');
            })
            ->where('production_output.line_code',  $request->line_code)
            ->where('production_output.production_date', $request->production_date)
            ->groupBy('production_output.shift_code', 'production_output.wo_code');

        $productionDataFinal = DB::query()->fromSub($productionData, 'v1')
            ->select('shift_code', DB::raw('SUM(working_time) working_time_total'))
            ->groupBy('shift_code')
            ->get();

        return [
            'data' => $downTime, 'workingTime' => $productionDataFinal
        ];
    }

    function getProductionTime(Request $request)
    {
        $data = ProductionTime::select('shift_code', 'working_hours')
            ->where('production_date', $request->production_date)
            ->where('line_code', strtoupper($request->line_code))
            ->get();
        return ['data' => $data];
    }

    function getInput(Request $request)
    {
        $data = DB::table('production_inputs')
            ->where('wo_code', $request->wo_code)
            ->where('process_code', $request->process_code)
            ->whereNull('deleted_at')
            ->orderBy('production_date')
            ->orderBy('shift_code')
            ->get();
        return ['data' => $data];
    }

    function exportDailyOutput(Request $request)
    {
        $productionOutput = DB::table('production_output')
            ->whereNull('deleted_at')
            ->where('production_date', '>=', $request->dateFrom)
            ->where('production_date', '<=', $request->dateTo)
            ->groupBy('line_code', 'production_date', 'shift_code', 'item_code', 'process_seq', 'wo_code')
            ->select(
                'line_code',
                'production_date',
                'shift_code',
                'item_code',
                'process_seq',
                'wo_code',
                DB::raw('MAX(cycle_time) max_cycle_time'),
                DB::raw('SUM(ok_qty) + SUM(ng_qty) sum_output_qty')
            );

        $itemProcesMaster = DB::table('process_masters')->select(
            'assy_code',
            DB::raw("MAX(model_code) model_code"),
            DB::raw("MAX(model_type) model_type"),
        )->groupBy('assy_code');

        $woMaster = DB::table('XWO')->select('PDPP_WONO', DB::raw("MAX(PDPP_WORQT) PDPP_WORQT"))
            ->groupBy('PDPP_WONO');

        $productionInput = DB::table('production_inputs')
            ->where('production_date', '>=', $request->dateFrom)
            ->where('production_date', '<=', $request->dateTo)
            ->select('line_code', 'production_date', 'shift_code', 'item_code', 'process_seq', 'wo_code', DB::raw("sum(input_qty) sum_input_qty"))
            ->groupBy('line_code', 'production_date', 'shift_code', 'item_code', 'process_seq', 'wo_code');

        $productionDowntime = DB::table('production_downtime')
            ->where('production_date', '>=', $request->dateFrom)
            ->where('production_date', '<=', $request->dateTo)
            ->select(
                'line_code',
                'production_date',
                'shift_code',
                DB::raw("SUM(CASE WHEN downtime_code=1 THEN req_minutes END)/60 dt1"),
                DB::raw("SUM(CASE WHEN downtime_code=2 THEN req_minutes END)/60 dt2"),
                DB::raw("SUM(CASE WHEN downtime_code=3 THEN req_minutes END)/60 dt3"),
                DB::raw("SUM(CASE WHEN downtime_code=4 THEN req_minutes END)/60 dt4"),
                DB::raw("SUM(CASE WHEN downtime_code=5 THEN req_minutes END)/60 dt5"),
                DB::raw("SUM(CASE WHEN downtime_code=6 THEN req_minutes END)/60 dt6"),
                DB::raw("SUM(CASE WHEN downtime_code=7 THEN req_minutes END)/60 dt7"),
            )
            ->groupBy('line_code', 'production_date', 'shift_code');

        $data = DB::query()->fromSub($productionOutput, 'prodOutput')
            ->leftJoinSub($itemProcesMaster, 'itemMaster', 'item_code', '=', 'assy_code')
            ->leftJoinSub($woMaster, 'woMaster', 'wo_code', '=', 'PDPP_WONO')
            ->leftJoinSub($productionInput, 'prodInput', function ($join) {
                $join->on('prodOutput.line_code', '=', 'prodInput.line_code')
                    ->on('prodOutput.production_date', '=', 'prodInput.production_date')
                    ->on('prodOutput.shift_code', '=', 'prodInput.shift_code')
                    ->on('prodOutput.item_code', '=', 'prodInput.item_code')
                    ->on('prodOutput.process_seq', '=', 'prodInput.process_seq')
                    ->on('prodOutput.wo_code', '=', 'prodInput.wo_code');
            })
            ->leftJoin('production_times', function ($join) {
                $join->on('prodOutput.line_code', '=', 'production_times.line_code')
                    ->on('prodOutput.production_date', '=', 'production_times.production_date')
                    ->on('prodOutput.shift_code', '=', 'production_times.shift_code');
            })
            ->leftJoinSub($productionDowntime, 'prodDowtime', function ($join) {
                $join->on('prodOutput.line_code', '=', 'prodDowtime.line_code')
                    ->on('prodOutput.production_date', '=', 'prodDowtime.production_date')
                    ->on('prodOutput.shift_code', '=', 'prodDowtime.shift_code');
            })
            ->select(
                'prodOutput.*',
                DB::raw("RTRIM(model_code) model_code"),
                DB::raw("RTRIM(model_type) model_type"),
                'PDPP_WORQT',
                'max_cycle_time',
                'sum_input_qty',
                'working_hours',
                'dt1',
                'dt2',
                'dt3',
                'dt4',
                'dt5',
                'dt6',
                'dt7',
            )
            ->orderBy('line_code')
            ->orderBy('production_date')
            ->orderBy('process_seq')
            ->get();
        
        $spreadSheet = new Spreadsheet();
        $sheet = $spreadSheet->getActiveSheet();
        $sheet->setTitle('daily_output_resume');
        $sheet->setCellValue([1, 1], 'Line');
        $sheet->setCellValue([2, 1], 'Date');
        $sheet->setCellValue([3, 1], 'Shift');
        $sheet->setCellValue([4, 1], 'Model');
        $sheet->setCellValue([5, 1], 'Type');
        $sheet->setCellValue([6, 1], 'Assy Code');
        $sheet->setCellValue([7, 1], 'Proses');
        $sheet->setCellValue([8, 1], 'Job No.');
        $sheet->setCellValue([9, 1], 'Lot Size');
        $sheet->setCellValue([10, 1], 'CT');
        $sheet->setCellValue([11, 1], 'Input');
        $sheet->setCellValue([12, 1], 'Output');
        $sheet->setCellValue([13, 1], 'Jam Kerja');
        $sheet->setCellValue([14, 1], 'Maintenance');
        $sheet->setCellValue([15, 1], 'M/C Trouble');
        $sheet->setCellValue([16, 1], 'Change model');
        $sheet->setCellValue([17, 1], '4M (New model)');
        $sheet->setCellValue([18, 1], 'Not Production 15 min');
        $sheet->setCellValue([19, 1], 'Not Production');
        $sheet->setCellValue([20, 1], 'Not Production No Plan');
        $y = 2;
        foreach ($data as $r) {
            $sheet->setCellValue([1, $y], $r->line_code);
            $sheet->setCellValue([2, $y], $r->production_date);
            $sheet->setCellValue([3, $y], $r->shift_code);
            $sheet->setCellValue([4, $y], $r->model_code);
            $sheet->setCellValue([5, $y], $r->model_type);
            $sheet->setCellValue([6, $y], $r->item_code);
            $sheet->setCellValue([7, $y], $r->process_seq);
            $sheet->setCellValue([8, $y], $r->wo_code);
            $sheet->setCellValue([9, $y], $r->PDPP_WORQT);
            $sheet->setCellValue([10, $y], $r->max_cycle_time);
            $sheet->setCellValue([11, $y], $r->sum_input_qty);
            $sheet->setCellValue([12, $y], $r->sum_output_qty);
            $sheet->setCellValue([13, $y], $r->working_hours);
            $sheet->setCellValue([14, $y], $r->dt1);
            $sheet->setCellValue([15, $y], $r->dt2);
            $sheet->setCellValue([16, $y], $r->dt3);
            $sheet->setCellValue([17, $y], $r->dt4);
            $sheet->setCellValue([18, $y], $r->dt5);
            $sheet->setCellValue([19, $y], $r->dt6);
            $sheet->setCellValue([20, $y], $r->dt7);
            $y++;
        }

        foreach (range('A', 'T') as $v) {
            $sheet->getColumnDimension($v)->setAutoSize(true);
        }

        $sheet->freezePane('A2');

        $stringjudul = "Daily Report from " . $request->dateFrom . " to " . $request->dateTo;
        $writer = IOFactory::createWriter($spreadSheet, 'Xlsx');
        $filename = $stringjudul;
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
    }
}
