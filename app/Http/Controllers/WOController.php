<?php

namespace App\Http\Controllers;

use App\Models\ProductionInput;
use App\Models\ProductionTime;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class WOController extends Controller
{
    private $keikakuColumnIndexStart = 6;
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

        $addionalWhere = [];
        if ($request->bomRev) {
            $addionalWhere[] = ['MBO2_BOMRV', '=', $request->bomRev];
        } else {
            $latestBomRev = DB::table('VCIMS_MBO2_TBL')
                ->select('MBO2_BOMRV')
                ->where('MBO2_MDLCD', $request->item_code)
                ->orderBy('MBO2_BOMRV', 'desc')->first();

            $addionalWhere[] = ['MBO2_BOMRV', '=', $latestBomRev->MBO2_BOMRV];
        }
        $data = DB::table('VCIMS_MBO2_TBL')->select(DB::raw('RTRIM(MBO2_PROCD) MBO2_PROCD'), 'MBO2_SEQNO')
            ->where('MBO2_MDLCD', $request->item_code)
            ->where($addionalWhere)
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
                'message' => 'Saved successfully',
                '$savedRows' => $savedRows,
                '$_TotalCurrentInput' => $_TotalCurrentInput,
                '$ProcessMaster' => $ProcessMaster
            ];
        } catch (Exception $e) {
            $message = $e->getMessage();
            DB::rollBack();
            return response()->json(['message' => $message], 400);
        }
    }

    function checkUserAccess($data)
    {
        $status = false;
        $statusUser = DB::table('keikaku_access_rules')->where('user_id', $data['user_id'])
            ->whereNull('deleted_at')->where('line_code', strtoupper($data['line_code']))
            ->where('sheet_access', 'DTA')
            ->first('sheet_access');
        if ($statusUser) {
            $status = true;
        }

        return $status;
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
                        'working_hours' => (float) $r['working_hours'],
                        'line_code' => strtoupper($data['line_code']),
                    ];
                }
            }

            if (!empty($tobeSaved)) {
                DB::table("production_times")->insert($tobeSaved);
            }

            DB::commit();

            return [
                'message' => 'Saved successfully',
                'data' => $tobeSaved,
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

        $dataKeikakuData = DB::table('keikaku_data')
            ->whereNull('deleted_at')
            ->where('production_date', $request->production_date)
            ->where('line_code', $request->line_code)
            ->orderBy('id')
            ->get(['*', DB::raw("cycle_time/3600*plan_qty as production_worktime"), DB::raw("cycle_time/3600 ct_hour")]);

        $dataCalc = DB::table('keikaku_calcs')->whereNull('deleted_at')->where('production_date', $request->production_date)
            ->orderBy('calculation_at')
            ->get([
                'plan_worktime',
                'efficiency',
                'calculation_at',
                DB::raw("plan_worktime*efficiency as effective_worktime"),
            ]);


        $asProdPlan = $this->plotProdPlan($dataKeikakuData, $dataCalc, [], [], [], [], []);

        return [
            'data' => $data->get(),
            'inputPCB' => $dataInputPCB->input_qty ?? 0,
            'keikakuData' => $dataKeikakuData,
            'asProdplan' => $asProdPlan[0]
        ];
    }

    private function plotProdPlan($dataKeikakuData, $dataCalc, $dataOutputSensor, $dataModelChangesActual, $dataInputHW, $dataOutputHW, $dataInput2HW)
    {
        $tempModel = '';
        $tempType = '';
        $tempSpecs = '';
        $tempAssyCode = '';

        // assy code|should change model| productio total hour |wo | specs side |times...
        $_asMatrixHeader1 = [NULL, NULL, NULL, NULL, NULL, NULL];
        $_asMatrixHeader2 = [NULL, NULL, NULL, NULL, NULL, NULL];
        $_asMatrixHeader3 = [NULL, NULL, NULL, NULL, NULL, NULL];
        $_asMatrixHeader4 = [NULL, NULL, NULL, NULL, NULL, NULL];
        foreach ($dataCalc as $c) {
            $_jam = substr(explode(' ', $c->calculation_at)[1], 0, 2);
            $_asMatrixHeader1[] = $_jam;
            $_asMatrixHeader2[] = $c->effective_worktime;
            $_asMatrixHeader3[] = $c->plan_worktime;
            $_asMatrixHeader4[] = $c->flag_mot;
        }
        $asMatrix = [
            $_asMatrixHeader1,
            $_asMatrixHeader2,
            $_asMatrixHeader3
        ];

        $asMatrixSensor = $asModelChangesActual = $asMatrixInputHW = $asMatrixOutputHW = $asMatrixInput2HW = [];
        foreach ($dataKeikakuData as $d) {
            $_shouldChangeModel = false;
            $_usedTime = 0;
            if (strlen($tempModel) > 0) {
                if (substr($d->type, 0, 4) == substr($tempType, 0, 4) && $tempSpecs == $d->specs_side) {
                    if ($tempAssyCode == $d->item_code) {
                        $_shouldChangeModel = false;
                    } else {
                        $_shouldChangeModel = true;
                        $_usedTime = 0.25;
                    }
                } else {
                    $_usedTime = 0.25;
                    $_shouldChangeModel = true;
                }

                if ($tempSpecs != $d->specs_side) {
                    $tempSpecs = $d->specs_side;
                }

                if (substr($d->type, 0, 4) != substr($tempType, 0, 4)) {
                    $tempType = $d->type;
                }

                if ($tempAssyCode != $d->item_code) {
                    $tempAssyCode = $d->item_code;
                }
            } else {
                if ($tempModel != $d->model_code) {
                    $_shouldChangeModel = false; // first row always set to false
                    $tempModel = $d->model_code;
                    $tempType = $d->type;
                    $tempSpecs = $d->specs_side;
                    $tempAssyCode = $d->item_code;
                }
            }


            $_asMatrix1 = [NULL, $_shouldChangeModel, ($_shouldChangeModel ? $_usedTime : 0), NULL, NULL, NULL];
            $_asMatrix2 = [
                $d->item_code,
                NULL,
                $d->production_worktime,
                $d->wo_full_code,
                $d->ct_hour,
                $d->specs_side . "#" . $d->model_code . "#" . $d->wo_code . "#" . $d->lot_size . "#" . $d->plan_qty . "#" . $d->type . "#" . $d->specs . "#" . $d->seq . "#" . $d->line_code
            ];
            $_asMatrix3 = [
                $d->item_code,
                $d->seq,
                $d->production_worktime,
                $d->wo_full_code,
                $d->specs_side,
                $d->ct_hour
            ];
            $_asMatrixModelChanges = [
                $d->item_code,
                $d->seq,
                $d->production_worktime,
                $d->wo_full_code,
                'process_code_container',
                $d->ct_hour
            ];

            $_asMatrixInputHW = $_asMatrixOutputHW = $_asMatrixInput2HW = [
                $d->item_code,
                $d->seq,
                $d->production_worktime,
                $d->wo_full_code,
                $d->specs_side,
                $d->ct_hour
            ];

            foreach ($dataCalc as $c) {
                $_jam = substr(explode(' ', $c->calculation_at)[1], 0, 2);
                $_asMatrix1[] = NULL;
                $_asMatrix2[] = NULL;

                $_asMatrix3[] = 0;
                $lastActualColumn = count($_asMatrix3) - 1;
                foreach ($dataOutputSensor as &$o) {
                    if ($o->wo_code == $d->wo_full_code && $o->process_code == $d->specs_side && $o->ok_qty > 0 && $o->seq_data == $d->seq) {
                        if (substr($c->calculation_at, 0, 13) == substr($o->running_at, 0, 13)) {
                            $_asMatrix3[$lastActualColumn] += $o->ok_qty;
                            $_asMatrix3[4] = $o->process_code;
                            $o->ok_qty = 0;
                        }
                    }
                }
                unset($o);

                $_asMatrixModelChanges[] = '';
                $lastActualColumn = count($_asMatrixModelChanges) - 1;
                foreach ($dataModelChangesActual as $o) {
                    if ($o->wo_code == $d->wo_full_code && $o->process_code == $d->specs_side  && $o->seq_data == $d->seq) {
                        if (substr($c->calculation_at, 0, 13) == substr($o->running_at, 0, 13)) {
                            $_asMatrixModelChanges[$lastActualColumn] = $o->change_flag;
                            $_asMatrixModelChanges[4] = $o->process_code;
                        }
                    }
                }

                $_asMatrixInputHW[] = 0;
                $lastActualColumn = count($_asMatrixInputHW) - 1;
                foreach ($dataInputHW as &$o) {
                    if ($o->wo_code == $d->wo_full_code && $o->process_code == $d->specs_side && $o->ok_qty > 0 && $o->seq_data == $d->seq) {
                        if (substr($c->calculation_at, 0, 13) == substr($o->running_at, 0, 13)) {
                            $_asMatrixInputHW[$lastActualColumn] += $o->ok_qty;
                            $_asMatrixInputHW[4] = $o->process_code;
                            $o->ok_qty = 0;
                        }
                    }
                }
                unset($o);

                $_asMatrixInput2HW[] = 0;
                $lastActualColumn = count($_asMatrixInput2HW) - 1;
                foreach ($dataInput2HW as &$o) {
                    if ($o->wo_code == $d->wo_full_code && $o->process_code == $d->specs_side && $o->ok_qty > 0 && $o->seq_data == $d->seq) {
                        if (substr($c->calculation_at, 0, 13) == substr($o->running_at, 0, 13)) {
                            $_asMatrixInput2HW[$lastActualColumn] += $o->ok_qty;
                            $_asMatrixInput2HW[4] = $o->process_code;
                            $o->ok_qty = 0;
                        }
                    }
                }
                unset($o);

                $_asMatrixOutputHW[] = 0;
                $lastActualColumn = count($_asMatrixOutputHW) - 1;
                foreach ($dataOutputHW as &$o) {
                    if ($o->wo_code == $d->wo_full_code && $o->process_code == $d->specs_side && $o->ok_qty > 0 && $o->seq_data == $d->seq) {
                        if (substr($c->calculation_at, 0, 13) == substr($o->running_at, 0, 13)) {
                            $_asMatrixOutputHW[$lastActualColumn] += $o->ok_qty;
                            $_asMatrixOutputHW[4] = $o->process_code;
                            $o->ok_qty = 0;
                        }
                    }
                }
                unset($o);
            }
            $asMatrix[] = $_asMatrix1;
            $asMatrix[] = $_asMatrix2;
            $asMatrixSensor[] = $_asMatrix3;
            $asModelChangesActual[] = $_asMatrixModelChanges;
            $asMatrixInputHW[] = $_asMatrixInputHW;
            $asMatrixOutputHW[] = $_asMatrixOutputHW;
            $asMatrixInput2HW[] = $_asMatrixInput2HW;
        }

        // bismillah proses kalkulasi waktu
        $matrixRowsLength = count($asMatrix);
        for ($i = 3; $i < $matrixRowsLength; $i++) {
            for ($col = $this->keikakuColumnIndexStart; $col < (6 + 36); $col++) {
                $_totalProductionHours = $asMatrix[$i][2];
                if ($_totalProductionHours == 0) {
                    $asMatrix[$i][$col] = 0;
                } else {
                    $asMatrix[$i][$col] = $this->_plotTime($asMatrix, $col, $i, round($_totalProductionHours, 5));
                }
            }
        }

        // transform time into qty
        $asProdPlanX = $asMatrix;

        for ($i = 3; $i < $matrixRowsLength; $i++) {
            for ($col = $this->keikakuColumnIndexStart; $col < (6 + 36); $col++) {
                if (!$asProdPlanX[$i][0]) { // change model
                    if ($col === $this->keikakuColumnIndexStart) {
                        if ($asProdPlanX[$i][1]) {
                            if ($asProdPlanX[$i][$col] == 0) {
                                $asProdPlanX[$i][$col] = 0;
                            } else {
                                $asProdPlanX[$i][$col] = round($asProdPlanX[$i][$col] / $asMatrix[$i][2]);
                            }
                        } else {
                            if ($asProdPlanX[$i][$col] == 0) {
                                $asProdPlanX[$i][$col] = 0;
                            } else {
                                $asProdPlanX[$i][$col] = $asProdPlanX[$i][$col] / $asMatrix[$i][4];
                            }
                        }
                    }
                } else {
                    if ($asMatrix[$i][4] == 0) {
                        $asProdPlanX[$i][$col] = 0;
                    } else {
                        if ($col === $this->keikakuColumnIndexStart) {
                            $asProdPlanX[$i][$col] = round($asProdPlanX[$i][$col] / $asMatrix[$i][4]);
                        } else {
                            if ($asProdPlanX[$i][$col] == 0) {
                                $asProdPlanX[$i][$col] = 0;
                            } else {
                                $asProdPlanX[$i][$col] = round($asProdPlanX[$i][$col] / $asMatrix[$i][4]);
                            }
                        }
                    }
                }
            }
        }

        return [$asMatrix, $asProdPlanX, $asMatrixSensor,  $_asMatrixHeader4, $asModelChangesActual, $asMatrixInputHW, $asMatrixOutputHW, $asMatrixInput2HW];
    }

    private function _plotTime($data, $parX, $parY, $parProductionHours)
    {
        $_plotedTime = 0;
        $restEffectiveWorkTime = $data[1][$parX] - $this->_sumVertical($data, $parX, $parY);

        if ($parX === $this->keikakuColumnIndexStart) {
            if ($parProductionHours < $restEffectiveWorkTime) {
                $_plotedTime = $parProductionHours;
            } else {
                $_plotedTime = $restEffectiveWorkTime;
            }
        } else {
            if ($parProductionHours < $restEffectiveWorkTime) {
                $_plotedTime = $parProductionHours - $this->_sumHorizontal($data, $parX, $parY);
            } elseif (($parProductionHours - $this->_sumHorizontal($data, $parX, $parY)) < $restEffectiveWorkTime) {
                $_plotedTime = $parProductionHours - $this->_sumHorizontal($data, $parX, $parY);
            } else {
                $_plotedTime = $restEffectiveWorkTime;
            }
        }

        return $_plotedTime;
    }

    private function _sumVertical($data, $parX, $parY)
    {
        $_summarizedVertical = 0;
        for ($__r = $parY; $__r > 2; $__r--) {
            $_summarizedVertical += $data[$__r][$parX];
        }

        return round($_summarizedVertical, 5);
    }

    private function _sumHorizontal($data, $parX, $parY)
    {
        $_summarizedHorizontal = 0;
        for ($__c = $parX; $__c >= $this->keikakuColumnIndexStart; $__c--) {
            $_summarizedHorizontal += $data[$parY][$__c];
        }
        return $_summarizedHorizontal;
    }

    function getProdPlanSimulation(Request $request)
    {
        $ReleaseStatus = DB::table('keikaku_releases')->where('line_code', $request->line_code)->whereNull('deleted_at')
            ->where('production_date', $request->production_date)->first();

        $isReleaser = DB::table('keikaku_access_rules')
            ->where('user_id', $request->user_id)
            ->where('sheet_access', 'RLS')
            ->whereNull('deleted_at')
            ->count() >= 1 ? true : false;
        if ($ReleaseStatus || $isReleaser) {
            $dataKeikakuData = DB::table('keikaku_data')
                ->whereNull('deleted_at')
                ->where('production_date', $request->production_date)
                ->where('line_code', $request->line_code)
                ->orderBy('id')
                ->get(['*', DB::raw("cycle_time/3600*plan_qty as production_worktime"), DB::raw("cycle_time/3600 ct_hour")]);
        } else {
            $dataKeikakuData = [];
        }


        $dataCalc = DB::table('keikaku_calcs')->whereNull('deleted_at')->where('production_date', $request->production_date)
            ->where('line_code', $request->line_code)
            ->orderBy('calculation_at')
            ->get([
                'plan_worktime',
                'efficiency',
                'calculation_at',
                'flag_mot',
                DB::raw("plan_worktime*efficiency as effective_worktime"),
            ]);

        if ($dataCalc->isEmpty()) {
            return response()->json(['message' => 'Calculation sheet data is required'], 400);
        }

        $dataSensor = DB::table('keikaku_outputs')->whereNull('deleted_at')
            ->where('production_date', $request->production_date)
            ->where('line_code', $request->line_code)
            ->groupBy('wo_code', 'running_at', 'process_code', 'seq_data')
            ->get([DB::raw('sum(ok_qty) ok_qty'), 'wo_code', 'running_at', 'process_code', 'seq_data']);



        $dataModelChanges = DB::table('keikaku_model_changes')->whereNull('deleted_at')
            ->where('production_date', $request->production_date)
            ->where('line_code', $request->line_code)
            ->groupBy('wo_code', 'running_at', 'process_code', 'seq_data', 'change_flag')
            ->get([DB::raw('change_flag'), 'wo_code', 'running_at', 'process_code', 'seq_data']);

        $inputHW = $this->isHWContext(['line' => $request->line_code]) ? DB::table('keikaku_input2s')->whereNull('deleted_at')
            ->where('production_date', $request->production_date)
            ->where('line_code', $request->line_code)
            ->groupBy('wo_code', 'running_at', 'process_code', 'seq_data')
            ->get([DB::raw('sum(ok_qty) ok_qty'), 'wo_code', 'running_at', 'process_code', 'seq_data']) : [];

        $outputHW = $this->isHWContext(['line' => $request->line_code]) ? DB::table('keikaku_output2s')->whereNull('deleted_at')
            ->where('production_date', $request->production_date)
            ->where('line_code', $request->line_code)
            ->groupBy('wo_code', 'running_at', 'process_code', 'seq_data')
            ->get([DB::raw('sum(ok_qty) ok_qty'), 'wo_code', 'running_at', 'process_code', 'seq_data']) : [];

        $input2HW = $this->isHWContext(['line' => $request->line_code]) ? DB::table('keikaku_input3s')->whereNull('deleted_at')
            ->where('production_date', $request->production_date)
            ->where('line_code', $request->line_code)
            ->groupBy('wo_code', 'running_at', 'process_code', 'seq_data')
            ->get([DB::raw('sum(ok_qty) ok_qty'), 'wo_code', 'running_at', 'process_code', 'seq_data']) : [];

        $asProdPlan = $this->plotProdPlan($dataKeikakuData, $dataCalc, $dataSensor, $dataModelChanges, $inputHW, $outputHW, $input2HW);

        $morningEfficiency = 0;
        $nightEfficiency = 0;
        $this->_updateDataSimulation($asProdPlan[1], $request->production_date);

        foreach ($dataCalc as $r) {
            if (substr($r->calculation_at, 11, 2) == '07' && $morningEfficiency == 0) {
                $morningEfficiency = $r->efficiency;
            }
            if (substr($r->calculation_at, 11, 2) == '20' && $nightEfficiency == 0) {
                $nightEfficiency = $r->efficiency;
                break;
            }
        }

        $theParam = [
            'dateFrom' => $request->production_date,
            'dateTo' => $request->production_date,
            'lineCode' => $request->line_code
        ];
        $data_ = $this->_getBaseKeikakuDataReport($theParam);

        $dataComments = DB::table('keikaku_comment_prodplans')->whereNull('deleted_at')
            ->where('production_date', $request->production_date)
            ->where('line_code', $request->line_code)
            ->get(['cell_code', 'comment']);

        return [
            'asProdplan' => $asProdPlan[1],
            'asMatrix' => $asProdPlan[0],
            'dataSensor' => $asProdPlan[2],
            'dataCalculation' => $asProdPlan[3],
            'dataChangesModel' => $asProdPlan[4],
            'morningEfficiency' => $morningEfficiency,
            'nightEfficiency' => $nightEfficiency,
            'dataMount' => $data_[0],
            'dataInputHW' => $asProdPlan[5],
            'dataOutputHW' => $asProdPlan[6],
            'dataInput2HW' => $asProdPlan[7],
            'dataComment' => $dataComments,
        ];
    }

    function _updateDataSimulation($data, $productionDate)
    {

        $dataToUpdate = [];
        foreach ($data as $r) {
            if ($r[0]) {
                $_prop = explode('#', $r[5]);

                //calculate moring & night
                $_morning = 0;
                $_night = 0;
                for ($i = 6; $i <= 29; $i++) {
                    if ($i > 17) {
                        $_night += $r[$i];
                    } else {
                        $_morning += $r[$i];
                    }
                }

                $dataToUpdate[] = [
                    'wo' => $r[3],
                    'seq' => $_prop[7],
                    'MORNING' => $_morning,
                    'NIGHT' => $_night,
                ];
            }
        }
        foreach ($dataToUpdate as $r) {
            DB::table('keikaku_data')->whereNull('deleted_at')
                ->where('wo_full_code', $r['wo'])
                ->where('production_date', $productionDate)
                ->where('seq', $r['seq'])
                ->update([
                    'plan_morning_qty' => $r['MORNING'],
                    'plan_night_qty' => $r['NIGHT'],
                ]);
        }
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
            ->where('production_output.line_code', $request->line_code)
            ->where('production_output.production_date', $request->production_date)
            ->groupBy('production_output.shift_code', 'production_output.wo_code');

        $productionDataFinal = DB::query()->fromSub($productionData, 'v1')
            ->select('shift_code', DB::raw('SUM(working_time) working_time_total'))
            ->groupBy('shift_code')
            ->get();

        return [
            'data' => $downTime,
            'workingTime' => $productionDataFinal
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

    function exportCost(Request $request)
    {
        # filter 1 : mendapatkan daftar Job
        $productionOutput = DB::table('production_output')
            ->whereNull('deleted_at')
            ->where('production_date', '<=', $request->dateFrom)
            ->groupBy('item_code', 'process_seq', 'wo_code')
            ->select(
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



        $productionInput = DB::table('production_inputs')
            ->where('production_date', '<=', $request->dateFrom)
            ->select('item_code', 'process_seq', 'wo_code', DB::raw("sum(input_qty) sum_input_qty"))
            ->groupBy('item_code', 'process_seq', 'wo_code');

        $data = DB::query()->fromSub($productionOutput, 'prodOutput')
            ->leftJoinSub($itemProcesMaster, 'itemMaster', 'item_code', '=', 'assy_code')
            ->leftJoinSub($productionInput, 'prodInput', function ($join) {
                $join->on('prodOutput.item_code', '=', 'prodInput.item_code')
                    ->on('prodOutput.process_seq', '=', 'prodInput.process_seq')
                    ->on('prodOutput.wo_code', '=', 'prodInput.wo_code');
            })
            ->select(
                'prodOutput.*',
                DB::raw("RTRIM(model_code) model_code"),
                DB::raw("RTRIM(model_type) model_type"),
                'max_cycle_time',
                'sum_input_qty',
            )
            ->whereRaw("sum_input_qty>isnull(sum_output_qty,0)")
            ->orderBy('wo_code')
            ->orderBy('process_seq')
            ->get();
        $UniqueJobList = [];

        foreach ($data as $r) {
            if (!in_array($r->wo_code, $UniqueJobList)) {
                $UniqueJobList[] = $r->wo_code;
            }
        }

        # filter 2 : dari daftar job tersebut tampilkan detailnya
        $productionOutput2 = DB::table('production_output')
            ->whereNull('deleted_at')
            ->whereIn('wo_code', $UniqueJobList)
            ->groupBy('item_code', 'process_seq', 'wo_code')
            ->select(
                'item_code',
                'process_seq',
                'wo_code',
                DB::raw('MAX(cycle_time) max_cycle_time'),
                DB::raw('SUM(ok_qty) + SUM(ng_qty) sum_output_qty')
            );
        $productionInput2 = DB::table('production_inputs')
            ->whereIn('wo_code', $UniqueJobList)
            ->select('item_code', 'process_seq', 'wo_code', DB::raw("sum(input_qty) sum_input_qty"))
            ->groupBy('item_code', 'process_seq', 'wo_code');

        $data2 = DB::query()->fromSub($productionOutput2, 'prodOutput')
            ->leftJoinSub($itemProcesMaster, 'itemMaster', 'item_code', '=', 'assy_code')
            ->leftJoinSub($productionInput2, 'prodInput', function ($join) {
                $join->on('prodOutput.item_code', '=', 'prodInput.item_code')
                    ->on('prodOutput.process_seq', '=', 'prodInput.process_seq')
                    ->on('prodOutput.wo_code', '=', 'prodInput.wo_code');
            })
            ->select(
                'prodOutput.*',
                DB::raw("RTRIM(model_code) model_code"),
                DB::raw("RTRIM(model_type) model_type"),
                'max_cycle_time',
                'sum_input_qty',
            )
            ->orderBy('wo_code')
            ->orderBy('process_seq')
            ->get();

        $spreadSheet = new Spreadsheet();
        $sheet = $spreadSheet->getActiveSheet();
        $sheet->setTitle('wip');
        $sheet->setCellValue([1, 1], 'Assy Code');
        $sheet->setCellValue([2, 1], 'Job No');
        $sheet->setCellValue([3, 1], 'Process');
        $sheet->setCellValue([4, 1], 'CT / Process');
        $sheet->setCellValue([5, 1], 'CT Total');
        $sheet->setCellValue([6, 1], 'Cost / second');
        $sheet->setCellValue([7, 1], 'Input Qty');
        $sheet->setCellValue([8, 1], 'Output Qty');
        $sheet->setCellValue([9, 1], 'WIP Qty');
        $sheet->setCellValue([10, 1], 'Inventory Cost');
        $sheet->setCellValue([11, 1], 'Actual Qty');

        $y = 2;
        $CTWOStage = 0;
        $tempFlag = '';
        foreach ($data2 as $r) {
            if ($tempFlag != $r->wo_code) {
                $CTWOStage = $r->max_cycle_time;
                $tempFlag = $r->wo_code;
            } else {
                $CTWOStage += $r->max_cycle_time;
            }
            $sheet->setCellValue([1, $y], $r->item_code);
            $sheet->setCellValue([2, $y], $r->wo_code);
            $sheet->setCellValue([3, $y], $r->process_seq);
            $sheet->setCellValue([4, $y], $r->max_cycle_time);
            $sheet->setCellValue([5, $y], $CTWOStage);
            $sheet->setCellValue([7, $y], $r->sum_input_qty);
            $sheet->setCellValue([8, $y], $r->sum_output_qty);
            $sheet->setCellValue([9, $y], ($r->sum_input_qty - $r->sum_output_qty));
            $y++;
        }

        foreach (range('A', 'K') as $v) {
            $sheet->getColumnDimension($v)->setAutoSize(true);
        }

        $sheet->getColumnDimension('D')->setVisible(false);
        $sheet->getColumnDimension('G')->setVisible(false);
        $sheet->getColumnDimension('H')->setVisible(false);

        $sheet->freezePane('A2');

        $stringjudul = "WIP Cost Report from " . $request->dateFrom . " to " . $request->dateTo;
        $writer = IOFactory::createWriter($spreadSheet, 'Xlsx');
        $filename = $stringjudul;
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
    }

    function saveKeikaku(Request $request)
    {
        $validator = Validator::make(
            $request->json()->all(),
            [
                'line_code' => 'required',
                'production_date' => 'required|date',
                'detail' => 'required|array',
                'detail.*.wo_code' => 'required',
            ],
            [
                'line_code.required' => ':attribute is required',
                'production_date.required' => ':attribute is required',
                'production_date.date' => ':attribute should be date',
                'detail.required' => ':attribute is required',
                'detail.array' => ':attribute should be array',
                'detail.*.wo_code.required' => ':attribute is required',
            ]
        );

        if ($validator->fails()) {
            return response()->json($validator->errors()->all(), 406);
        }

        $data = $request->json()->all();

        if (!$this->checkUserAccess(['user_id' => $data['user_id'], 'line_code' => $data['line_code']])) {
            return response()->json(['message' => 'You have read-only access'], 403);
        }

        $isReleaser = DB::table('keikaku_access_rules')
            ->where('user_id', $data['user_id'])
            ->where('sheet_access', 'RLS')
            ->whereNull('deleted_at')
            ->count() >= 1 ? true : false;
        $ReleaseStatus = DB::table('keikaku_releases')->where('line_code', $data['line_code'])->whereNull('deleted_at')
            ->where('production_date', $data['production_date'])->first();

        if (!$this->checkUserAccess(['user_id' => $data['user_id'], 'line_code' => $data['line_code']])) {
            return response()->json(['message' => 'You have read-only access'], 403);
        }

        if ($ReleaseStatus) {
        } else {
            if (!$isReleaser) {
                return response()->json(['message' => 'You are not a releaser'], 403);
            }
        }


        $tobeSaved = [];
        $message = '';

        try {
            DB::beginTransaction();
            $dbStyle = DB::table('keikaku_styles')->whereNull('deleted_at')
                ->where('production_date', $data['production_date'])
                ->where('line_code', $data['line_code']);
            if ($dbStyle->count() > 0) {
                $dbStyle->update(['deleted_at' => date('Y-m-d H:i:s')]);
            }
            DB::table('keikaku_styles')->insert([[
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $data['user_id'],
                'line_code' => $data['line_code'],
                'production_date' => $data['production_date'],
                'styles' => json_encode($data['style']),
                'sheet_code' => 'd',
            ]]);

            $tobeSaved = [];
            // validate WO
            $UniqueWO = [];
            $UniqueWO1 = [];
            $UniqueWO2 = [];
            $InputWO = [];
            $InputWO1 = [];
            $InputWO2 = [];
            $WOOnly = [];
            $AssyCodeOnly = [];
            $WOOnly1 = [];
            $AssyCodeOnly1 = [];
            $seq = 1;
            foreach ($data['detail'] as $r) {
                $_wo = date('y') . '-' . $r['wo_code'] . '-' . trim($r['item_code']);
                $_wo_only = date('y') . '-' . $r['wo_code'];
                if (!in_array($_wo, $UniqueWO)) {
                    $UniqueWO[] = $_wo;
                    $WOOnly[] = substr($_wo_only, 0, 7);
                    $AssyCodeOnly[] = trim($r['item_code']);
                }
                $InputWO[] = ['WO' => $_wo, 'FLAG' => 0, 'BWO' => $_wo, 'WO_ONLY' => $_wo_only, 'ASSY_CODE' => trim($r['item_code'])];

                $tobeSaved[] = [
                    'created_at' => date('Y-m-d H:i:s'),
                    'created_by' => $data['user_id'],
                    'line_code' => $data['line_code'],
                    'production_date' => $data['production_date'],
                    'seq' => $seq,
                    'model_code' => $r['model_code'],
                    'wo_code' => $r['wo_code'],
                    'wo_full_code' => $_wo,
                    'item_code' => $r['item_code'],
                    'lot_size' => $r['lot_size'],
                    'plan_qty' => $r['plan_qty'],
                    'type' => $r['type'],
                    'specs' => $r['specs'],
                    'specs_side' => $r['specs_side'],
                    'packaging' => $r['packaging'],
                    'cycle_time' => (float) $r['cycle_time'],
                    'bom_rev' => null
                ];
                $seq++;
            }

            # check UniqueWO on database
            $DBWO0 = DB::table('XWO')->select('PDPP_WONO', 'PDPP_MDLCD', 'PDPP_BOMRV')
                ->whereIn('PDPP_MDLCD', $AssyCodeOnly)
                ->whereIn(DB::raw('SUBSTRING(PDPP_WONO,1,7)'), $WOOnly)
                ->get();
            foreach ($DBWO0 as $d) {
                foreach ($InputWO as &$i) {
                    if (str_contains($d->PDPP_WONO, $i['WO_ONLY']) && $d->PDPP_MDLCD == $i['ASSY_CODE'] && $i['FLAG'] == 0) {
                        $i['FLAG'] = 1;
                        foreach ($tobeSaved as &$s) {
                            if ($s['wo_full_code'] == $d->PDPP_WONO) {
                                $s['bom_rev'] = $d->PDPP_BOMRV;
                            }
                        }
                        unset($s);
                    }
                }
                unset($i);
            }

            $prefixPreviousYear = (int) date('y') - 1;
            $prefixNextYear = (int) date('y') + 1;
            $additionalFilter1Applied = false;
            $additionalFilter2Applied = false;

            foreach ($InputWO as &$i) {
                if ($i['FLAG'] === 0) {
                    $additionalFilter1Applied = true;
                    $_bWO = $i['WO'];
                    $i['WO'] = $prefixNextYear . substr($i['WO'], 2, strlen($i['WO']));
                    $UniqueWO1[] = $i['WO'];
                    $InputWO1[] = ['WO' => $i['WO'], 'FLAG' => 0, 'BWO' => $_bWO, 'ASSY_CODE' => $i['ASSY_CODE']];

                    $WOOnly1[] = $prefixNextYear  . substr($i['WO_ONLY'], 2, strlen($i['WO_ONLY']));
                    $AssyCodeOnly1[] = $i['ASSY_CODE'];
                }
            }
            unset($i);

            if ($additionalFilter1Applied) {
                $DBWO1 = DB::table('XWO')->select('PDPP_WONO', 'PDPP_MDLCD')
                    ->whereIn('PDPP_MDLCD', $AssyCodeOnly1)
                    ->whereIn(DB::raw('SUBSTRING(PDPP_WONO,1,7)'), $WOOnly1)
                    ->get();
                foreach ($DBWO1 as $d) {
                    foreach ($InputWO1 as &$i) {
                        if (str_contains($d->PDPP_WONO, $i['WO']) && $d->PDPP_MDLCD == $i['ASSY_CODE'] && $i['FLAG'] == 0) {
                            foreach ($tobeSaved as &$r) {
                                $_tempWO = substr($i['WO'], 2, strlen($i['WO'])) . '-' . $i['ASSY_CODE'];
                                if (str_contains($r['wo_full_code'], $_tempWO)) {
                                    $r['wo_full_code'] = '??' . $_tempWO;
                                }
                            }
                            unset($r);

                            $i['FLAG'] = 1;
                            break;
                        }
                    }
                    unset($i);
                }

                foreach ($InputWO1 as &$i) {
                    if ($i['FLAG'] === 0) {
                        $additionalFilter2Applied = true;
                        $_bWO = $i['WO'];
                        $i['WO'] = $prefixPreviousYear . substr($i['WO'], 2, strlen($i['WO']));
                        $UniqueWO2[] = $i['WO'];
                        $InputWO2[] = ['WO' => $i['WO'], 'FLAG' => 0, 'BWO' => $_bWO];
                    }
                }
                unset($i);

                if ($additionalFilter2Applied) {
                    $DBWO = DB::table('XWO')->select('PDPP_WONO')->whereIn('PDPP_WONO', $UniqueWO2)->get();
                    foreach ($DBWO as $d) {
                        foreach ($InputWO2 as &$i) {
                            if ($d->PDPP_WONO === $i['WO']) {
                                $i['FLAG'] = 1;
                            }
                        }
                        unset($i);
                    }

                    foreach ($InputWO2 as &$i) {
                        if ($i['FLAG'] === 0) {
                            foreach ($tobeSaved as &$r) {
                                $_tempWO = substr($i['WO'], 2, strlen($i['WO']));
                                if (str_contains($r['wo_full_code'], $_tempWO)) {
                                    $r['wo_full_code'] = '??' . $_tempWO;
                                }
                            }
                            unset($r);
                        }
                    }
                    unset($i);
                }
            }

            // when bom rev still null then get from CIMS
            foreach ($tobeSaved as &$s) {
                if (!$s['bom_rev']) {
                    $s['bom_rev'] = DB::table('VCIMS_MBLA_TBL')
                        ->where('MBLA_MDLCD', $s['item_code'])
                        ->select(DB::raw('MAX(MBLA_BOMRV) MBLA_BOMRV'))->value('MBLA_BOMRV');
                }
            }
            unset($s);

            if (
                DB::table('keikaku_data')
                ->where('line_code', $data['line_code'])
                ->whereNull('deleted_at')
                ->where('production_date', $data['production_date'])->count() > 0
            ) {
                DB::table('keikaku_data')
                    ->where('line_code', $data['line_code'])
                    ->whereNull('deleted_at')
                    ->where('production_date', $data['production_date'])->update(
                        ['deleted_at' => date('Y-m-d H:i:s'), 'deleted_by' => $data['user_id']]
                    );
            }

            DB::table('keikaku_data')->insert($tobeSaved);


            // save calculation if not exist
            if (
                DB::table('keikaku_calc_resumes')
                ->where('line_code', $data['line_code'])
                ->where('production_date', $data['production_date'])->count() == 0
                && DB::table('keikaku_calcs')
                ->where('line_code', $data['line_code'])
                ->where('production_date', $data['production_date'])->count()  == 0
            ) {
                $tobeSaved = [];
                foreach ($data['detail_calc'] as $r) {
                    $tobeSaved[] = [
                        'shift_code' => $r['shift_code'],
                        'production_date' => $data['production_date'],
                        'calculation_at' => $r['calculation_at'],
                        'line_code' => $data['line_code'],
                        'worktype1' => (float) $r['worktype1'],
                        'worktype2' => (float) $r['worktype2'],
                        'worktype3' => (float) $r['worktype3'],
                        'worktype4' => (float) $r['worktype4'],
                        'worktype5' => (float) $r['worktype5'],
                        'worktype6' => (float) $r['worktype6'],
                        'plan_worktime' => (float) $r['plan_worktime'],
                        'flag_mot' => $r['flag_mot'],
                        'efficiency' => (float) $r['efficiency'],
                        'created_at' => date('Y-m-d H:i:s'),
                        'created_by' => $data['user_id'],
                    ];
                }

                DB::table('keikaku_calcs')->insert($tobeSaved);
                DB::table('keikaku_calc_resumes')->insert([
                    'line_code' => $data['line_code'],
                    'production_date' => $data['production_date'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'created_by' => $data['user_id'],
                    'total_plan_worktime_morning' => $data['totalWorkingTimeMorning'],
                    'total_plan_worktime_night' => $data['totalWorkingTimeNight'],
                ]);
            }

            DB::commit();
            return ['message' => 'Saved successfully', $data];
        } catch (Exception $e) {
            $message = $e->getMessage();
            DB::rollBack();
            return response()->json(['message' => $message], 400);
        }
    }

    function saveKeikakuCalculation(Request $request)
    {
        $validator = Validator::make(
            $request->json()->all(),
            [
                'line_code' => 'required',
                'production_date' => 'required|date',
                'user_id' => 'required',
                'detail' => 'required|array',
                'detail.*.shift_code' => 'required',
            ],
            [
                'line_code.required' => ':attribute is required',
                'production_date.required' => ':attribute is required',
                'production_date.date' => ':attribute should be date',
                'user_id.required' => ':attribute is required',
                'detail.required' => ':attribute is required',
                'detail.array' => ':attribute should be array',
                'detail.*.shift_code.required' => ':attribute is required',
            ]
        );

        if ($validator->fails()) {
            return response()->json($validator->errors()->all(), 406);
        }
        $data = $request->json()->all();

        if (!$this->checkUserAccess(['user_id' => $data['user_id'], 'line_code' => $data['line_code']])) {
            return response()->json(['message' => 'You have read-only access'], 403);
        }

        $tobeSaved = [];

        try {

            DB::beginTransaction();
            foreach ($data['detail'] as $r) {
                $tobeSaved[] = [
                    'shift_code' => $r['shift_code'],
                    'production_date' => $data['production_date'],
                    'calculation_at' => $r['calculation_at'],
                    'line_code' => $data['line_code'],
                    'worktype1' => (float) $r['worktype1'],
                    'worktype2' => (float) $r['worktype2'],
                    'worktype3' => (float) $r['worktype3'],
                    'worktype4' => (float) $r['worktype4'],
                    'worktype5' => (float) $r['worktype5'],
                    'worktype6' => (float) $r['worktype6'],
                    'plan_worktime' => (float) $r['plan_worktime'],
                    'flag_mot' => $r['flag_mot'],
                    'efficiency' => (float) $r['efficiency'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'created_by' => $data['user_id'],
                ];
            }

            if (
                DB::table('keikaku_calcs')
                ->where('line_code', $data['line_code'])
                ->whereNull('deleted_at')
                ->where('production_date', $data['production_date'])->count() > 0
            ) {
                DB::table('keikaku_calcs')
                    ->where('line_code', $data['line_code'])
                    ->whereNull('deleted_at')
                    ->where('production_date', $data['production_date'])->update(
                        ['deleted_at' => date('Y-m-d H:i:s'), 'deleted_by' => $data['user_id']]
                    );
            }
            if (
                DB::table('keikaku_calc_resumes')
                ->where('line_code', $data['line_code'])
                ->whereNull('deleted_at')
                ->where('production_date', $data['production_date'])->count() > 0
            ) {
                DB::table('keikaku_calc_resumes')
                    ->where('line_code', $data['line_code'])
                    ->whereNull('deleted_at')
                    ->where('production_date', $data['production_date'])->update(
                        ['deleted_at' => date('Y-m-d H:i:s'), 'deleted_by' => $data['user_id']]
                    );
            }

            DB::table('keikaku_calcs')->insert($tobeSaved);
            DB::table('keikaku_calc_resumes')->insert([
                'line_code' => $data['line_code'],
                'production_date' => $data['production_date'],
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $data['user_id'],
                'total_plan_worktime_morning' => $data['totalWorkingTimeMorning'],
                'total_plan_worktime_night' => $data['totalWorkingTimeNight'],
            ]);

            DB::commit();
            return ['message' => 'Saved successfully', 'data' => $tobeSaved];
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    function getKeikakuCalculation(Request $request)
    {
        $data = DB::table('keikaku_calcs')
            ->whereNull('deleted_at')
            ->where('production_date', $request->production_date)
            ->where('line_code', $request->line_code)
            ->orderBy('id')
            ->get(['worktype1', 'worktype2', 'worktype3', 'worktype4', 'worktype5', 'worktype6', 'flag_mot', 'efficiency']);

        $dateO = date_create($request->production_date);
        $category = date_format($dateO, 'w') == '5' ? 'f' : 'nf'; // friday or non friday
        $dataDefault = DB::table('keikaku_calc_templates')
            ->whereNull('deleted_at')
            ->where('status', '1')
            ->where('category', $category)
            ->orderBy('id')
            ->get();

        return ['data' => $data, 'dataDefault' => $dataDefault];
    }

    function getKeikakuData(Request $request)
    {
        $currentActiveUser = NULL;
        try {
            $currentActiveUser = Redis::command('EXISTS', ['keikaku_' . base64_encode($request->line_code . '#' . $request->production_date)]);

            if ($currentActiveUser) {
                $currentActiveUser = Redis::command('GET', ['keikaku_' . base64_encode($request->line_code . '#' . $request->production_date)]);
            } else {
                Redis::command('SET', ['keikaku_' . base64_encode($request->line_code . '#' . $request->production_date), $request->user_id]);
                Redis::command('EXPIRE', ['keikaku_' . base64_encode($request->line_code . '#' . $request->production_date), 1800]);
                $currentActiveUser = Redis::command('GET', ['keikaku_' . base64_encode($request->line_code . '#' . $request->production_date)]);
            }
        } catch (Exception $e) {
            // ketika redis tidak aktif
            $currentActiveUser = $request->user_id;
        }

        $nextDate = date_create($request->production_date);
        date_add($nextDate, date_interval_create_from_date_string('1 days'));
        $maxCalculationDate = date_format($nextDate, 'Y-m-d') . ' 07:00:00';

        $data = [];
        $dataOutput = NULL;
        $isReleaser = DB::table('keikaku_access_rules')
            ->where('user_id', $request->user_id)
            ->where('sheet_access', 'RLS')
            ->whereNull('deleted_at')
            ->count() >= 1 ? true : false;

        $ReleaseStatus = DB::table('keikaku_releases')->where('line_code', $request->line_code)->whereNull('deleted_at')
            ->where('production_date', $request->production_date)->first();

        $originLineCategory = DB::table('process_masters')
            ->whereNull('deleted_at')
            ->where('line_code', $request->line_code)->first();

        $relatedLines = !$originLineCategory ? [] : DB::table('wms_v_get_line_category')
            ->where('line_category', $originLineCategory->line_category)->get()
            ->unique('line_code')->pluck('line_code')->toArray();

        if ($ReleaseStatus || $isReleaser) {

            // only show data when someone is a releaser OR the data is released

            if ($this->isHWContext(['line' => $request->line_code])) {
                $dataOutput = $request->line_code == '-' ? [] : DB::table('keikaku_input3s')->where('production_date', $request->production_date)
                    ->where('line_code', $request->line_code)
                    ->where('running_at', '<', $maxCalculationDate)
                    ->whereNull('deleted_at')
                    ->groupBy('wo_code', 'process_code', 'seq_data')
                    ->select('wo_code', 'process_code', 'seq_data', DB::raw('sum(ok_qty) ok_qty'));
            } else {
                $dataOutput = $request->line_code == '-' ? [] : DB::table('keikaku_outputs')->where('production_date', $request->production_date)
                    ->where('line_code', $request->line_code)
                    ->where('running_at', '<', $maxCalculationDate)
                    ->whereNull('deleted_at')
                    ->groupBy('wo_code', 'process_code', 'seq_data')
                    ->select('wo_code', 'process_code', 'seq_data', DB::raw('sum(ok_qty) ok_qty'));
            }

            $data = $request->line_code == '-' ? [] : DB::table('keikaku_data')
                ->leftJoinSub($dataOutput, 'output', function ($join) {
                    $join->on('keikaku_data.wo_full_code', '=', 'output.wo_code')
                        ->on('keikaku_data.specs_side', '=', 'output.process_code')
                        ->on('keikaku_data.seq', '=', 'output.seq_data');
                })
                ->whereNull('deleted_at')
                ->where('production_date', $request->production_date)
                ->where('line_code', $request->line_code)
                ->orderBy('id')
                ->get(['keikaku_data.*', DB::raw('ISNULL(ok_qty,0) ok_qty')]);

            $previousData = [];
            $_uniqueItem = $data->unique('item_code')->pluck('item_code')->toArray();
            $processMaster = DB::table('process_masters')
                ->whereNull('deleted_at')
                ->whereIn('assy_code', $_uniqueItem)
                ->whereRaw("isnull(process_seq,0)>0")
                ->where('line_code', $request->line_code)
                ->get(['assy_code', 'process_seq', 'process_code']);

            $_processFocus = [];

            foreach ($data as &$d) {
                $d->process_seq = -1;
                foreach ($processMaster as $p) {
                    if (in_array($originLineCategory->line_category, ['HW', 'MI'])) {
                        if ($d->item_code == $p->assy_code) {
                            $d->process_seq = $p->process_seq;
                            if (!in_array($p->process_seq, $_processFocus)) {
                                $_processFocus[] = $p->process_seq;
                            }
                            break;
                        }
                    } else {
                        if ($d->item_code == $p->assy_code && str_contains($p->process_code, $d->specs_side)) {
                            $d->process_seq = $p->process_seq;
                            if (!in_array($p->process_seq, $_processFocus)) {
                                $_processFocus[] = $p->process_seq;
                            }
                            break;
                        }
                    }
                }
            }
        }

        $keikakuDataStyle = $request->line_code == '-' ? [] : DB::table('keikaku_styles')->whereNull('deleted_at')
            ->where('production_date', $request->production_date)
            ->where('line_code', $request->line_code)
            ->first();
        $keikakuDataStyleO = $keikakuDataStyle ? json_decode($keikakuDataStyle->styles) : [];

        $woCompleted = $woProcessed = [];
        foreach ($data as &$d) {
            $d->previousRun = 0;
            $_wo_and_process = $d->wo_full_code . $d->specs_side;
            if (!in_array($_wo_and_process, $woProcessed)) {
                $woProcessed[] = $_wo_and_process;

                $_data = DB::table('keikaku_data')->whereNull('deleted_at')
                    ->where('production_date', '<', $request->production_date)
                    ->whereIn('line_code', $relatedLines)
                    ->where('wo_full_code', $d->wo_full_code)
                    ->where('specs_side', $d->specs_side)
                    ->select('production_date', 'line_code', 'seq', 'wo_full_code');

                $_procesMaster0 = DB::table('process_masters')
                    ->whereNull('deleted_at')
                    ->where('assy_code', $d->item_code)
                    ->whereIn('line_code', $relatedLines)
                    ->groupBy('assy_code', 'process_code', 'line_code')
                    ->select(
                        'assy_code',
                        DB::raw('MAX(process_seq) process_seq'),
                        DB::raw("case 
                    when process_code = 'SMT-A' THEN 'A'                    
                    when process_code = 'SMT-B' THEN 'B' 
                    ELSE 'A'
                    END process_code"),
                        'line_code'
                    );

                $_procesMaster = DB::query()->fromSub($_procesMaster0, 'vp1')
                    ->groupBy('assy_code', 'process_code', 'line_code')
                    ->select(
                        'assy_code',
                        DB::raw('MAX(process_seq) process_seq'),
                        'process_code',
                        'line_code'
                    );

                if ($this->isHWContext(['line' => $request->line_code])) {
                    $_output = DB::table('keikaku_input3s')->whereNull('deleted_at')
                        ->where('production_date', '<', $request->production_date)
                        ->whereIn('line_code', $relatedLines)
                        ->where('wo_code', $d->wo_full_code)
                        ->select(
                            'production_date',
                            'line_code',
                            'seq_data',
                            DB::raw("sum(case
                                    when production_date = convert(date, running_at) then ok_qty
                                    else case
                                    when convert(char(5), running_at, 108) < '07:00' then ok_qty
                                    end
                                end) previous_ok_qty"),
                            'wo_code',
                            DB::raw("MAX(process_code) process_code")
                        )
                        ->groupBy('production_date', 'line_code', 'seq_data', 'wo_code');
                } else {
                    $_output = DB::table('keikaku_outputs')->whereNull('deleted_at')
                        ->where('production_date', '<', $request->production_date)
                        ->whereIn('line_code', $relatedLines)
                        ->where('wo_code', $d->wo_full_code)
                        ->select(
                            'production_date',
                            'line_code',
                            'seq_data',
                            DB::raw("sum(case
                                    when production_date = convert(date, running_at) then ok_qty
                                    else case
                                    when convert(char(5), running_at, 108) < '07:00' then ok_qty
                                    end
                                end) previous_ok_qty"),
                            'wo_code',
                            DB::raw("MAX(process_code) process_code")
                        )
                        ->groupBy('production_date', 'line_code', 'seq_data', 'wo_code');
                }


                $previousData = $request->line_code == '-' ? [] : DB::query()->fromSub($_data, 'V1')
                    ->leftJoinSub($_output, 'V2', function ($join) {
                        $join->on('V1.production_date', '=', 'V2.production_date')
                            ->on('V1.line_code', '=', 'V2.line_code')
                            ->on('V1.wo_full_code', '=', 'V2.wo_code')
                            ->on('V1.seq', '=', 'V2.seq_data')
                        ;
                    })
                    ->leftJoinSub($_procesMaster, 'V3', function ($join) {
                        $join->on('V2.line_code', '=', 'V3.line_code')
                            ->on('V2.process_code', '=', 'V3.process_code')
                        ;
                    })
                    ->groupBy('wo_code', 'V2.process_code', 'process_seq')
                    ->select(
                        'wo_code',
                        'V2.process_code',
                        DB::raw("sum(previous_ok_qty) previous_ok_qty"),
                        DB::raw('process_seq')
                    )->get();

                foreach ($previousData as &$p) {
                    if (isset($p->process_seq)) {
                        if (
                            $d->wo_full_code == $p->wo_code && $d->specs_side == $p->process_code
                            && $d->process_seq == $p->process_seq
                            && $p->previous_ok_qty > 0
                        ) {
                            $woCompleted[] = $p->wo_code;
                            $d->previousRun += $p->previous_ok_qty;
                            $p->previous_ok_qty = 0;
                            break;
                        }
                    } else {

                        if (
                            $d->wo_full_code == $p->wo_code && $d->specs_side == $p->process_code
                            && $p->previous_ok_qty > 0
                        ) {
                            $woCompleted[] = $p->wo_code;
                            $d->previousRun += $p->previous_ok_qty;
                            $p->previous_ok_qty = 0;
                            break;
                        }
                    }
                }
                unset($p);
            }
        }
        unset($d);

        $dataLength = empty($data) ? 0 : $data->count();

        for ($r = 1; $r < $dataLength; $r++) {
            if ($data[$r]->previousRun == 0) {
                for ($r0 = $r - 1; $r0 >= 0; $r0--) {
                    if ($data[$r]->wo_full_code == $data[$r0]->wo_full_code && $data[$r]->specs_side == $data[$r0]->specs_side) {
                        $data[$r]->previousRun = $data[$r0]->previousRun + $data[$r0]->plan_qty;
                        break;
                    }
                }
            }
        }

        return [
            'data' => $data,
            'currentActiveUser' => DB::table('MSTEMP_TBL')->where('MSTEMP_ID', $currentActiveUser)
                ->first(['MSTEMP_ID', 'MSTEMP_FNM', 'MSTEMP_LNM']),
            'dataStyle' => $keikakuDataStyleO,
            'release' => $ReleaseStatus,
        ];
    }

    function saveKeikakuFromPreviousBalance(Request $request)
    {
        $validator = Validator::make(
            $request->json()->all(),
            [
                'line_code' => 'required',
                'production_date' => 'required|date',
                'user_id' => 'required',
            ],
            [
                'line_code.required' => ':attribute is required',
                'production_date.required' => ':attribute is required',
                'production_date.date' => ':attribute should be date',
                'user_id.required' => ':attribute is required',
            ]
        );

        if ($validator->fails()) {
            return response()->json($validator->errors()->all(), 406);
        }

        $data = $request->json()->all();

        $lastProductionDate = DB::table('keikaku_data')
            ->select('production_date')
            ->where('line_code', $data['line_code'])
            ->where('production_date', '<=', $data['production_date'])
            ->orderBy('production_date', 'desc')->first();

        $balanceData = [];
        $message = '';
        if ($lastProductionDate) {
            $balanceData = DB::table('keikaku_data')
                ->select(
                    'id',
                    'model_code',
                    'wo_code',
                    'lot_size',
                    'plan_qty',
                    'item_code',
                    'type',
                    'specs',
                    'specs_side',
                    'packaging',
                    DB::raw("plan_qty-isnull(actual_qty,0) bal_qty")
                )
                ->where('line_code', $data['line_code'])
                ->where('production_date', $lastProductionDate->production_date)
                ->whereRaw('plan_qty > isnull(actual_qty,0)')
                ->orderBy('id', 'asc')->get();
        } else {
            $message = 'there is no previous balance on a Line ' . $data['line_code'];
        }

        return ['data' => $balanceData, 'message' => $message];
    }

    function importProdPlan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'template_file' => 'required|mimes:xlsx,xls|max:10240',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 406);
        }

        $message = '';
        $data = [];

        if ($request->file('template_file')) {
            $file = $request->file('template_file');

            $fileName = time() . '_' . $file->getClientOriginalName();

            $location = 'assets';

            $file->move(public_path($location), $fileName);
            $reader = IOFactory::createReader(ucfirst($file->getClientOriginalExtension()));
            $spreadsheet = $reader->load(public_path('assets/' . $fileName));
            $sheetCount = $spreadsheet->getSheetCount();
            $revision = '';
            $scheduleYear = '';
            $scheduleMonth = '';
            $startProductionDate = '';
            for ($i = 0; $i < $sheetCount; $i++) { // sheet scope
                $sheet = $spreadsheet->getSheet($i);
                $emptyRowsCount = 0;
                $_columnABefore = '';
                $rowAt = 12;
                if ($sheet->getCell('N2')->getCalculatedValue()) { // to avoid revision is not filled in specific sheet
                    $revision = $sheet->getCell('N2')->getCalculatedValue();
                    $_StartProductionDate = $sheet->getCell('AT4')->getCalculatedValue();
                    $_StartProductionDateO = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($_StartProductionDate);
                    $startProductionDate = $_StartProductionDateO->format('Y-m-d');
                }

                if ($sheet->getCell('A4')->getCalculatedValue()) { // to avoid Month is not filled in specific sheet
                    switch (strtoupper($sheet->getCell('A4')->getCalculatedValue())) {
                        case 'JANUARY':
                            $scheduleMonth = '01';
                            break;
                        case 'FEBRUARY':
                            $scheduleMonth = '02';
                            break;
                        case 'MARCH':
                            $scheduleMonth = '03';
                            break;
                        case 'APRIL':
                            $scheduleMonth = '04';
                            break;
                        case 'MAY':
                            $scheduleMonth = '05';
                            break;
                        case 'JUNE':
                            $scheduleMonth = '06';
                            break;
                        case 'JULY':
                            $scheduleMonth = '07';
                            break;
                        case 'AUGUST':
                            $scheduleMonth = '08';
                            break;
                        case 'SEPTEMBER':
                            $scheduleMonth = '09';
                            break;
                        case 'OCTOBER':
                            $scheduleMonth = '10';
                            break;
                        case 'NOVEMBER':
                            $scheduleMonth = '11';
                            break;
                        case 'DECEMBER':
                            $scheduleMonth = '12';
                            break;
                        default:
                            $scheduleMonth = strtoupper($sheet->getCell('A4')->getCalculatedValue());
                    }

                    $scheduleYear = $sheet->getCell('E4')->getCalculatedValue();
                }

                while ($emptyRowsCount < 2) { // row scope

                    $_columnA = $sheet->getCell([1, $rowAt])->getCalculatedValue(); // Seq
                    $_columnC = $sheet->getCell([3, $rowAt])->getCalculatedValue(); // Model
                    $_columnCNext = trim($sheet->getCell([3, $rowAt + 1])->getCalculatedValue()); // type
                    $_columnD = $sheet->getCell([4, $rowAt])->getCalculatedValue(); // Job
                    $_columnDNext = trim($sheet->getCell([4, $rowAt + 1])->getCalculatedValue()); // Spec
                    $_columnE = $sheet->getCell([5, $rowAt])->getCalculatedValue(); // lot size
                    $_columnENext = $sheet->getCell([5, $rowAt + 1])->getCalculatedValue(); // assy code

                    if ($_columnA == '') {
                        $emptyRowsCount++;
                    }

                    if ($_columnA != '' && $_columnA != $_columnABefore && is_numeric($_columnA)) {

                        $_columnABefore = $_columnA;
                        $emptyRowsCount--;

                        for ($c = 13; $c < 53; $c++) { // column scope

                            $_qty = $sheet->getCell([$c, $rowAt])->getCalculatedValue();
                            $_date = $sheet->getCell([$c, 6])->getCalculatedValue();
                            $_dateO = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($_date);
                            $_shift = strtoupper($sheet->getCell([$c, 11])->getCalculatedValue());

                            if ($_qty > 0) {

                                if (!$_date) {
                                    if ($_shift == 'N') {
                                        $_date = $sheet->getCell([$c - 1, 6])->getCalculatedValue();
                                        $_dateO = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($_date);
                                    }
                                }

                                $data[] = [
                                    'revision' => $revision,
                                    'line_code' => $sheet->getCell('J4')->getCalculatedValue(),
                                    'seq' => $_columnA,
                                    'model_code' => $_columnC,
                                    'wo_code' => $_columnD,
                                    'type' => $_columnCNext,
                                    'specs' => $_columnDNext,
                                    'lot_size' => $_columnE,
                                    'item_code' => $_columnENext,
                                    'plan_qty' => $_qty,
                                    'production_date' => $_dateO->format('Y-m-d'),
                                    'created_by' => $request->user_id,
                                    'created_at' => date('Y-m-d H:i:s'),
                                    'start_production_date' => $startProductionDate,
                                    'shift' => trim($_shift),
                                    'file_year' => $scheduleYear,
                                    'file_month' => $scheduleMonth,
                                ];
                            }
                        }
                    }

                    $rowAt++;
                }
            }

            if (count($data) > 0) {
                $TOTAL_COLUMN = 17;
                try {
                    DB::beginTransaction();
                    $ttlSaved = DB::table('keikaku_draft_data')
                        ->where('file_year', $scheduleYear)
                        ->where('file_month', $scheduleMonth)
                        ->where('revision', $revision)
                        ->whereNull('deleted_at')
                        ->count();

                    if ($ttlSaved > 0) {
                        DB::table('keikaku_draft_data')
                            ->where('file_year', $scheduleYear)
                            ->where('file_month', $scheduleMonth)
                            ->where('revision', $revision)
                            ->whereNull('deleted_at')
                            ->update(['deleted_at' => date('Y-m-d H:i:s'), 'deleted_by' => $request->user_id]);
                    }

                    $insert_data = collect($data);
                    $chunks = $insert_data->chunk(2000 / $TOTAL_COLUMN);
                    foreach ($chunks as $chunk) {
                        DB::table('keikaku_draft_data')->insert($chunk->toArray());
                    }
                    DB::commit();
                } catch (Exception $e) {
                    unlink(public_path('assets/' . $fileName));
                    DB::rollBack();
                    return response()->json(['message' => $e->getMessage()], 400);
                }
            }

            unlink(public_path('assets/' . $fileName));

            $message = 'Uploaded successfully';
        }

        return ['message' => $message, 'data' => $data];
    }

    function getProdPlanRevisions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_year' => 'required',
            'file_month' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 406);
        }

        $data = DB::table('keikaku_draft_data')
            ->where('file_year', $request->file_year)
            ->where('file_month', $request->file_month)
            ->whereNull('deleted_at')
            ->groupBy('revision')
            ->orderBy('revision');

        return ['data' => $data->get('revision'), $request->file_year, $request->file_month];
    }

    function getProdPlan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_year' => 'required',
            'file_month' => 'required',
            'line_code' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 406);
        }

        $data = DB::table('keikaku_draft_data')
            ->where('file_year', $request->file_year)
            ->where('file_month', $request->file_month)
            ->where('line_code', $request->line_code)
            ->where('revision', base64_decode($request->revision))
            ->whereNull('deleted_at')
            ->orderBy('seq')
            ->orderBy('production_date')
            ->get();

        return ['data' => $data];
    }

    function saveKeikakuOutput(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'line' => 'required',
            'job' => 'required',
            'side' => 'required',
            'quantity' => 'required',
        ], [
            'line.required' => ':attribute is required',
            'job.required' => ':attribute is required',
            'side.required' => ':attribute is required',
            'quantity.required' => ':attribute is required',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 406);
        }

        $running_at = $request->productionDate . ' ' . $request->runningAtTime . ':00';
        $nextDate = date_create($request->productionDate);
        date_add($nextDate, date_interval_create_from_date_string('1 days'));

        if ($request->XCoordinate >= 26) {
            $_date = date_create($request->productionDate);
            date_add($_date, date_interval_create_from_date_string('1 days'));
            $running_at = date_format($_date, 'Y-m-d') . ' ' . $request->runningAtTime . ':00';
        }

        $productionPlan = DB::table('keikaku_data')
            ->where('production_date', $request->productionDate)
            ->where('line_code', $request->line)
            ->where('wo_full_code', $request->job)
            ->where('specs_side', $request->side)
            ->where('seq', $request->seq_data)
            ->whereNull('deleted_at')
            ->first();

        $currentOutput = DB::table('keikaku_outputs')
            ->whereDate('production_date', $request->productionDate)
            ->where("running_at", "!=", $running_at)
            ->where('line_code', $request->line)
            ->where('wo_code', $request->job)
            ->where('process_code', $request->side)
            ->where('seq_data', $request->seq_data)
            ->whereNull('deleted_at')
            ->select(DB::raw('isnull(sum(ok_qty),0) ok_qty'))
            ->first();

        // periksa proses konteks
        $procesMaster = DB::table('process_masters')
            ->whereNull('deleted_at')
            ->where('assy_code', $request->assy_code)
            ->groupBy('assy_code', 'process_code', 'line_code')
            ->select(
                'assy_code',
                DB::raw('MAX(process_seq) process_seq'),
                DB::raw("case 
                    when process_code = 'SMT-A' OR process_code = 'A' THEN 'A'                    
                    when process_code = 'SMT-B' OR process_code = 'B' THEN 'B' 
                    ELSE 'A'
                    END process_code"),
                'line_code'
            );

        $procesMasterO = DB::query()->fromSub($procesMaster, 'v1')
            ->where('process_code', $request->side)
            ->where('line_code', $request->line)
            ->first();

        $historyDataJoin = [];

        if (!empty($procesMasterO)) {
            if ($procesMasterO->process_seq > 1 && $request->quantity != 0) { // hanya untuk seq > 1
                $totalOutputCurrentSeq = $request->quantity;
                $historyData = $this->getWOHistoryData([
                    'doc' => $request->job,
                    'cutoff_date' => $request->production_date,
                    'keikaku_outputs' => [
                        'column_name' => 'running_at',
                        'column_value' => [$running_at],
                        'operator' => 'not_in'
                    ]
                ])->where('ok_qty', '>', 0)->orWhere('ok_qty_hw', '>', 0);

                $historyDataJoinSQL = DB::query()->fromSub($historyData, 'V2')
                    ->leftJoinSub($procesMaster, 'V3', function ($join) {
                        $join->on('V2.line_code', '=', 'V3.line_code')
                            ->on('V2.specs_side', '=', 'V3.process_code')
                            ->on('V2.item_code', '=', 'V3.assy_code')
                        ;
                    })
                    ->groupBy('wo_full_code', 'process_seq')
                    ->whereRaw("ISNULL(process_seq,0)>=" . ($procesMasterO->process_seq - 1))
                    ->select(
                        'wo_full_code',
                        'process_seq',
                        DB::raw("SUM(ok_qty)+SUM(ok_qty_hw) ok_qty")
                    );

                $historyDataJoin = $historyDataJoinSQL->get();
                $_totalPrevSeq = $historyDataJoin->where('process_seq', ($procesMasterO->process_seq - 1))->first();
                $_totalCurrentSeq = $historyDataJoin->where('process_seq', $procesMasterO->process_seq)->first();

                $_totalPrevSeqV = 0;

                if (!empty($_totalPrevSeq)) {
                    $_totalPrevSeqV = $_totalPrevSeq->ok_qty ?? 0;
                }

                if (!empty($_totalCurrentSeq)) {
                    $totalOutputCurrentSeq += $_totalCurrentSeq->ok_qty ?? 0;
                }

                if (!empty($historyDataJoin)) {
                    if ($totalOutputCurrentSeq > $_totalPrevSeqV) {
                        return response()->json(
                            [
                                'message' => 'Previous Process=' . (int)$_totalPrevSeqV . ', output=' .
                                    $totalOutputCurrentSeq,

                            ],
                            406
                        );
                    } else {
                        $totalOutputCurrentSeq = $currentOutput->ok_qty ?? 0 + $request->quantity;
                    }
                }
            } else {
                $totalOutputCurrentSeq = $currentOutput->ok_qty ?? 0 + $request->quantity;
            }
        }

        if ($totalOutputCurrentSeq > $productionPlan->plan_qty) {
            return response()->json(
                [
                    'message' => 'Prodplan=' . $productionPlan->plan_qty . ', output=' .
                        $totalOutputCurrentSeq,
                    'data' => $historyDataJoin
                ],
                406
            );
        }

        DB::table('keikaku_outputs')
            ->where("running_at",  $running_at)
            ->where('line_code', $request->line)
            ->where('wo_code', $request->job)
            ->where('process_code', $request->side)
            ->where('seq_data', $request->seq_data)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => date('Y-m-d H:i:s')]);

        $affectedRows = DB::table('keikaku_outputs')->insert([
            'created_at' => date('Y-m-d H:i:s'),
            'production_date' => $request->productionDate,
            'running_at' => $running_at,
            'wo_code' => $request->job,
            'line_code' =>  $request->line,
            'process_code' => $request->side,
            'ok_qty' => $request->quantity,
            'seq_data' => $request->seq_data,
            'created_by' => $request->user_id,
        ]);

        return $affectedRows ? ['message' => 'Recorded successfully'] :
            ['message' => 'Failed, please try again', 'data' => $historyDataJoin];
    }

    public function saveKeikakuDownTime(Request $request)
    {
        $validator = Validator::make($request->json()->all(), [
            'lineCode' => 'required',
            'productionDate' => 'required|date',
            'shift' => 'required',
        ], [
            'lineCode.required' => ':attribute is required',
            'productionDate.required' => ':attribute is required',
            'productionDate.date' => ':attribute should be date',
            'shift.required' => ':attribute is required',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 406);
        }

        $data = $request->json()->all();

        try {
            DB::beginTransaction();
            $headQuery = DB::table('production_downtime')
                ->whereNull('deleted_at')
                ->where('line_code', $data['lineCode'])
                ->where('production_date', $data['productionDate'])
                ->where('shift_code', $data['shift']);
            if (
                $headQuery->count() >= 1
            ) {
                $headQuery->update(['deleted_at' => date('Y-m-d H:i:s')]);
            }

            $tobeSaved = [];

            foreach ($data['detail'] as $r) {
                $tobeSaved[] = [
                    'created_at' => date('Y-m-d H:i:s'),
                    'line_code' => $data['lineCode'],
                    'production_date' => $data['productionDate'],
                    'created_by' => $data['user_id'],
                    'shift_code' => $data['shift'],
                    'downtime_code' => $r['downTimeCode'],
                    'remark' => $r['remark'],
                    'req_minutes' => $r['reqMinutes'],
                    'running_at' => $data['productionDate'] . ' ' . $r['runningAt'] . ':00',
                ];
            }

            if (!empty($tobeSaved)) {
                DB::table('production_downtime')->insert($tobeSaved);
            }

            DB::commit();

            return ['message' => 'Saved successfully'];
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function getKeikakuDownTime(Request $request)
    {
        $data = DB::table('production_downtime')
            ->whereNull('deleted_at')
            ->where('line_code', $request->lineCode)
            ->where('production_date', $request->productionDate)
            ->where('shift_code', $request->shift)
            ->get();

        return ['data' => $data];
    }

    function _getBaseKeikakuDataReport($params)
    {
        $dataOutput = DB::table('keikaku_outputs')
            ->where('production_date', '>=', $params['dateFrom'])
            ->where('production_date', '<=', $params['dateTo'])
            ->whereNull('deleted_at')
            ->groupBy('wo_code', 'process_code', 'production_date', 'line_code', 'running_at', 'seq_data')
            ->get(['wo_code', 'process_code', 'production_date', 'line_code', 'running_at', DB::raw('sum(ok_qty) ok_qty'), 'seq_data']);

        $dataInput1HW = DB::table('keikaku_input2s')
            ->where('production_date', '>=', $params['dateFrom'])
            ->where('production_date', '<=', $params['dateTo'])
            ->whereNull('deleted_at')
            ->groupBy('wo_code', 'process_code', 'production_date', 'line_code', 'seq_data')
            ->select([
                'wo_code',
                'process_code',
                'production_date',
                'line_code',
                DB::raw("sum(case
                        when production_date = convert(date, running_at) AND convert(char(5), running_at, 108) < '19:00' then ok_qty     
                    END) output_hw_in1_m_qty"),
                DB::raw("ISNULL(sum(case
                        when production_date = convert(date, running_at) AND convert(char(5), running_at, 108) >= '19:00' then ok_qty     
                    end),0) 
                    +
                    ISNULL(SUM(CASE 
                    WHEN production_date != convert(date, running_at) AND convert(char(5), running_at, 108) < '07:00' THEN ok_qty
                    END),0) output_hw_in1_n_qty"),
                'seq_data'
            ]);
        $dataInput2HW = DB::table('keikaku_input3s')
            ->where('production_date', '>=', $params['dateFrom'])
            ->where('production_date', '<=', $params['dateTo'])
            ->whereNull('deleted_at')
            ->groupBy('wo_code', 'process_code', 'production_date', 'line_code', 'seq_data')
            ->select([
                'wo_code',
                'process_code',
                'production_date',
                'line_code',
                DB::raw("sum(case
                        when production_date = convert(date, running_at) AND convert(char(5), running_at, 108) < '19:00' then ok_qty     
                    END) output_hw_in2_m_qty"),
                DB::raw("ISNULL(sum(case
                        when production_date = convert(date, running_at) AND convert(char(5), running_at, 108) >= '19:00' then ok_qty     
                    end),0) 
                    +
                    ISNULL(SUM(CASE 
                    WHEN production_date != convert(date, running_at) AND convert(char(5), running_at, 108) < '07:00' THEN ok_qty
                    END),0) output_hw_in2_n_qty"),
                'seq_data'
            ]);

        $dataOutputHW = DB::table('keikaku_output2s')
            ->where('production_date', '>=', $params['dateFrom'])
            ->where('production_date', '<=', $params['dateTo'])
            ->whereNull('deleted_at')
            ->groupBy('wo_code', 'process_code', 'production_date', 'line_code', 'seq_data')
            ->select([
                'wo_code',
                'process_code',
                'production_date',
                'line_code',
                DB::raw("sum(case
                        when production_date = convert(date, running_at) AND convert(char(5), running_at, 108) < '19:00' then ok_qty     
                    END) output_hw_m_qty"),
                DB::raw("ISNULL(sum(case
                        when production_date = convert(date, running_at) AND convert(char(5), running_at, 108) >= '19:00' then ok_qty     
                    end),0) 
                    +
                    ISNULL(SUM(CASE 
                    WHEN production_date != convert(date, running_at) AND convert(char(5), running_at, 108) < '07:00' THEN ok_qty
                    END),0) output_hw_n_qty"),
                'seq_data'
            ]);

        $subCalculationSummary = DB::table('keikaku_calc_resumes')
            ->whereNull('deleted_at')
            ->where('production_date', '>=', $params['dateFrom'])
            ->where('production_date', '<=', $params['dateTo'])
            ->select('production_date', 'line_code', 'total_plan_worktime_morning', 'total_plan_worktime_night');

        $whereAdditional = [];
        if (isset($params['lineCode'])) {
            $whereAdditional[] = ['keikaku_data.line_code', $params['lineCode']];
        }
        $data = DB::table('keikaku_data')
            ->leftJoinSub($subCalculationSummary, 'calculation_summary', function ($join) {
                $join->on('keikaku_data.production_date', '=', 'calculation_summary.production_date')
                    ->on('keikaku_data.line_code', '=', 'calculation_summary.line_code');
            })
            ->leftJoinSub($dataOutputHW, 'output_hw', function ($join) {
                $join->on('keikaku_data.wo_full_code', '=', 'output_hw.wo_code')
                    ->on('keikaku_data.specs_side', '=', 'output_hw.process_code')
                    ->on('keikaku_data.production_date', '=', 'output_hw.production_date')
                    ->on('keikaku_data.line_code', '=', 'output_hw.line_code')
                    ->on('keikaku_data.seq', '=', 'output_hw.seq_data');
            })
            ->leftJoinSub($dataInput1HW, 'input1_hw', function ($join) {
                $join->on('keikaku_data.wo_full_code', '=', 'input1_hw.wo_code')
                    ->on('keikaku_data.specs_side', '=', 'input1_hw.process_code')
                    ->on('keikaku_data.production_date', '=', 'input1_hw.production_date')
                    ->on('keikaku_data.line_code', '=', 'input1_hw.line_code')
                    ->on('keikaku_data.seq', '=', 'input1_hw.seq_data');
            })
            ->leftJoinSub($dataInput2HW, 'input2_hw', function ($join) {
                $join->on('keikaku_data.wo_full_code', '=', 'input2_hw.wo_code')
                    ->on('keikaku_data.specs_side', '=', 'input2_hw.process_code')
                    ->on('keikaku_data.production_date', '=', 'input2_hw.production_date')
                    ->on('keikaku_data.line_code', '=', 'input2_hw.line_code')
                    ->on('keikaku_data.seq', '=', 'input2_hw.seq_data');
            })
            ->whereNull('deleted_at')
            ->where('keikaku_data.production_date', '>=', $params['dateFrom'])
            ->where('keikaku_data.production_date', '<=', $params['dateTo'])
            ->where($whereAdditional)
            ->orderBy('keikaku_data.production_date')
            ->orderBy('id')
            ->get([
                'keikaku_data.*',
                DB::raw('0 ok_qty'),
                'total_plan_worktime_morning',
                'total_plan_worktime_night',
                DB::raw('ISNULL(output_hw_in1_m_qty,0) output_hw_in1_m_qty'),
                DB::raw('ISNULL(output_hw_in1_n_qty,0) output_hw_in1_n_qty'),
                DB::raw('ISNULL(output_hw_in2_m_qty,0) output_hw_in2_m_qty'),
                DB::raw('ISNULL(output_hw_in2_n_qty,0) output_hw_in2_n_qty'),
                DB::raw('ISNULL(output_hw_m_qty,0) output_hw_m_qty'),
                DB::raw('ISNULL(output_hw_n_qty,0) output_hw_n_qty'),
            ]);

        $dataAssyVer = [];
        foreach ($data as &$r) {
            $_morningOutput = 0;
            $_nightOutput = 0;
            foreach ($dataOutput as $o) {
                if (
                    $r->production_date == $o->production_date
                    && $r->wo_full_code == $o->wo_code
                    && $r->line_code == $o->line_code
                    && $r->seq == $o->seq_data
                ) {
                    $_running_at = explode(' ', $o->running_at);
                    if ($r->production_date == $_running_at[0]) {
                        if ($_running_at[1] >= '19:00:00') {
                            $_nightOutput += $o->ok_qty;
                        } else {
                            $_morningOutput += $o->ok_qty;
                        }
                    } else {
                        if ($_running_at[1] < '07:00:00') {
                            $_nightOutput += $o->ok_qty;
                        }
                    }
                }
            }
            $r->morningOutput = $_morningOutput;
            $r->nightOutput = $_nightOutput;
            $r->baseMount = 0;

            // resume for next loop to get base mountings
            $isFound = false;
            foreach ($dataAssyVer as $n) {
                if ($n['ASSY_CODE'] == $r->item_code && $n['BOM_REV'] == $r->bom_rev) {
                    $isFound = true;
                    break;
                }
            }

            if (!$isFound) {
                $dataAssyVer[] = [
                    'ASSY_CODE' => $r->item_code,
                    'BOM_REV' => $r->bom_rev
                ];
            }
        }
        unset($r);


        $uniqueWO = [];
        $uniqueLine = [];
        foreach ($data as $r) {
            if ($r->morningOutput > 0 || $r->nightOutput > 0) {
                if (!in_array($r->wo_full_code, $uniqueWO)) {
                    $uniqueWO[] = $r->wo_full_code;
                }
            }

            if (!in_array($r->line_code, $uniqueLine)) {
                $uniqueLine[] = $r->line_code;
            }
        }

        $dataMountArray = [];

        foreach ($dataAssyVer as $r) {
            try {
                $currentKeyActive = Redis::command('EXISTS', ['mount_' . $r['ASSY_CODE'] . '#' . $r['BOM_REV']]);
                if ($currentKeyActive) {
                    $currentKeyActive = json_decode(Redis::command('GET', ['mount_' . $r['ASSY_CODE'] . '#' . $r['BOM_REV']]));
                    foreach ($currentKeyActive as $m) {
                        $_isAdded = false;
                        foreach ($dataMountArray as $n) {
                            if (
                                $n['ASSY_CODE'] == $m->MBLA_MDLCD && $n['BOM_REV'] == $m->MBLA_BOMRV && $n['LINENO'] == $m->MBLA_LINENO
                                && $n['PROCD'] == $m->MBLA_PROCD
                            ) {
                                $_isAdded = true;
                                break;
                            }
                        }
                        if (!$_isAdded) {
                            $dataMountArray[] = [
                                'ASSY_CODE' => $m->MBLA_MDLCD,
                                'BOM_REV' => $m->MBLA_BOMRV,
                                'PROCD' => $m->MBLA_PROCD,
                                'COUNTLOCATION' => $m->COUNTLOCATION,
                                'LINENO' => $m->MBLA_LINENO,
                                'SEQNO' => $m->MBO2_SEQNO
                            ];
                        }
                    }
                } else {
                    $_subQuery = DB::table('VCIMS_MBLA_TBL')
                        ->where('MBLA_MDLCD', $r['ASSY_CODE'])
                        ->where('MBLA_BOMRV', $r['BOM_REV'])
                        ->groupBy('MBLA_MDLCD', 'MBLA_BOMRV', 'MBLA_PROCD', 'MBLA_LINENO')
                        ->select(
                            DB::raw('RTRIM(MBLA_MDLCD) MBLA_MDLCD'),
                            'MBLA_BOMRV',
                            DB::raw('RTRIM(MBLA_PROCD) MBLA_PROCD'),
                            DB::raw('COUNT(*) COUNTLOCATION'),
                            DB::raw('RTRIM(MBLA_LINENO) MBLA_LINENO')
                        );
                    $dataMount = DB::query()->fromSub($_subQuery, 'V1')
                        ->leftJoin('VCIMS_MBO2_TBL', function ($join) {
                            $join->on('MBLA_MDLCD', '=', 'MBO2_MDLCD')
                                ->on('MBLA_BOMRV', '=', 'MBO2_BOMRV')
                                ->on('MBLA_PROCD', '=', 'MBO2_PROCD');
                        })
                        ->orderBy('MBO2_SEQNO')
                        ->select('V1.*', 'MBO2_SEQNO')->get();

                    foreach ($dataMount as $m) {
                        $_isAdded = false;
                        foreach ($dataMountArray as $n) {
                            if (
                                $n['ASSY_CODE'] == $m->MBLA_MDLCD && $n['BOM_REV'] == $m->MBLA_BOMRV && $n['LINENO'] == $m->MBLA_LINENO
                                && $n['PROCD'] == $m->MBLA_PROCD
                            ) {
                                $_isAdded = true;
                                break;
                            }
                        }
                        if (!$_isAdded) {
                            $dataMountArray[] = [
                                'ASSY_CODE' => $m->MBLA_MDLCD,
                                'BOM_REV' => $m->MBLA_BOMRV,
                                'PROCD' => $m->MBLA_PROCD,
                                'COUNTLOCATION' => $m->COUNTLOCATION,
                                'LINENO' => $m->MBLA_LINENO,
                                'SEQNO' => $m->MBO2_SEQNO
                            ];
                        }
                    }
                    Redis::command('SET', ['mount_' . $r['ASSY_CODE'] . '#' . $r['BOM_REV'], json_encode($dataMount)]);
                    Redis::command('EXPIRE', ['mount_' . $r['ASSY_CODE'] . '#' . $r['BOM_REV'], 2505600]); // 29 days
                }
            } catch (Exception $e) {
                $isFound = false;
                foreach ($dataMountArray as $n) {
                    if ($n['ASSY_CODE'] == $r['ASSY_CODE'] && $n['BOM_REV'] == $r['BOM_REV']) {
                        $isFound = true;
                        break;
                    }
                }
                if (!$isFound) {
                    $_subQuery = DB::table('VCIMS_MBLA_TBL')
                        ->where('MBLA_MDLCD', $r['ASSY_CODE'])
                        ->where('MBLA_BOMRV', $r['BOM_REV'])
                        ->groupBy('MBLA_MDLCD', 'MBLA_BOMRV', 'MBLA_PROCD', 'MBLA_LINENO')
                        ->select(
                            DB::raw('RTRIM(MBLA_MDLCD) MBLA_MDLCD'),
                            'MBLA_BOMRV',
                            DB::raw('RTRIM(MBLA_PROCD) MBLA_PROCD'),
                            DB::raw('COUNT(*) COUNTLOCATION'),
                            'MBLA_LINENO'
                        );
                    $dataMount = DB::query()->fromSub($_subQuery, 'V1')
                        ->leftJoin('VCIMS_MBO2_TBL', function ($join) {
                            $join->on('MBLA_MDLCD', '=', 'MBO2_MDLCD')
                                ->on('MBLA_BOMRV', '=', 'MBO2_BOMRV')
                                ->on('MBLA_PROCD', '=', 'MBO2_PROCD');
                        })
                        ->orderBy('MBO2_SEQNO')
                        ->select('V1.*', 'MBO2_SEQNO')->get();
                    foreach ($dataMount as $m) {
                        $_isAdded = false;
                        foreach ($dataMountArray as $n) {
                            if (
                                $n['ASSY_CODE'] == $m->MBLA_MDLCD && $n['BOM_REV'] == $m->MBLA_BOMRV && $n['LINENO'] == $m->MBLA_LINENO
                                && $n['PROCD'] == $m->MBLA_PROCD
                            ) {
                                $_isAdded = true;
                                break;
                            }
                        }
                        if (!$_isAdded) {
                            $dataMountArray[] = [
                                'ASSY_CODE' => $m->MBLA_MDLCD,
                                'BOM_REV' => $m->MBLA_BOMRV,
                                'PROCD' => $m->MBLA_PROCD,
                                'COUNTLOCATION' => $m->COUNTLOCATION,
                                'LINENO' => $m->MBLA_LINENO,
                                'SEQNO' => $m->MBO2_SEQNO
                            ];
                        }
                    }
                }
            }
        }

        // plotting 0
        foreach ($data as &$d) {
            foreach ($dataMountArray as $r) {
                if ($r['ASSY_CODE'] == $d->item_code) {
                    if ($d->specs_side == 'A') {
                        if ($r['SEQNO'] == 1) {
                            $d->baseMount = $r['COUNTLOCATION'] - 1;
                            break;
                        }
                    } else {
                        if ($r['SEQNO'] == 2) {
                            $d->baseMount = $r['COUNTLOCATION'];
                        }
                    }
                }
            }
        }
        unset($d);

        // plotting 1
        foreach ($data as &$d) {
            if ($d->baseMount == 0) {
                foreach ($dataMountArray as $r) {
                    if ($r['ASSY_CODE'] == $d->item_code) {
                        $substractPCB = $r['SEQNO'] == 1 ? 1 : 0;
                        if (str_contains($r['LINENO'], $d->line_code) && str_contains($r['PROCD'], $d->specs_side)) {
                            $d->baseMount = $r['COUNTLOCATION'] - $substractPCB;
                            break;
                        }
                    }
                }
            }
        }
        unset($d);

        // plotting 2
        foreach ($data as &$d) {
            if ($d->baseMount == 0) {
                foreach ($dataMountArray as $r) {
                    if ($r['ASSY_CODE'] == $d->item_code) {
                        $substractPCB = $r['SEQNO'] == 1 ? 1 : 0;
                        if (str_contains($r['LINENO'], $d->line_code) && str_contains($r['PROCD'], $d->specs_side)) {
                            $d->baseMount = $r['COUNTLOCATION'] - $substractPCB;
                            break;
                        } else {
                            if (str_contains($r['LINENO'], substr($d->line_code, -1)) && str_contains($r['PROCD'], $d->specs_side)) {
                                $d->baseMount = $r['COUNTLOCATION'] - $substractPCB;
                                break;
                            } elseif (str_contains($r['PROCD'], $d->specs_side)) {
                                $d->baseMount = $r['COUNTLOCATION'] - $substractPCB;
                                break;
                            } elseif (str_contains($r['LINENO'], $d->line_code)) {
                                $d->baseMount = $r['COUNTLOCATION'] - $substractPCB;
                                break;
                            }
                        }
                    }
                }
            }
        }
        unset($d);

        sort($uniqueLine);

        return [$data, $dataMountArray, $uniqueLine];
    }

    public function getKeikakuReport(Request $request)
    {
        $theParam = [
            'dateFrom' => $request->dateFrom,
            'dateTo' => $request->dateTo,
        ];
        $data = $this->_getBaseKeikakuDataReport($theParam)[0];
        $dataRelease = DB::table('keikaku_releases')->whereNull('deleted_at')
            ->where('release_flag', 'Y')
            ->get(['production_date', 'line_code']);

        $spreadSheet = new Spreadsheet();
        $sheet = $spreadSheet->getActiveSheet();
        $sheet->setTitle('keikaku_mc');
        $sheet->setCellValue([1, 1], 'Production Date');
        $sheet->setCellValue([2, 1], 'Line Code');
        $sheet->setCellValue([3, 1], 'Model');
        $sheet->setCellValue([4, 1], 'Spec');
        $sheet->setCellValue([5, 1], 'Assy Code');
        $sheet->setCellValue([6, 1], 'Job No');
        $sheet->setCellValue([7, 1], 'Specs');
        $sheet->setCellValue([7, 2], 'Side');
        $sheet->setCellValue([8, 1], 'CT / Process');
        $sheet->setCellValue([9, 1], 'Qty');
        $sheet->setCellValue([9, 2], 'Lot Size');
        $sheet->setCellValue([10, 1], 'Plan');
        $sheet->setCellValue([10, 2], 'Total');
        $sheet->setCellValue([11, 2], 'M');
        $sheet->setCellValue([12, 2], 'N');
        $sheet->mergeCells('J1:L1', $sheet::MERGE_CELL_CONTENT_HIDE);

        $sheet->setCellValue([13, 1], 'Input');
        $sheet->setCellValue([13, 2], 'Total');
        $sheet->setCellValue([14, 2], 'M');
        $sheet->setCellValue([15, 2], 'N');
        $sheet->mergeCells('M1:O1', $sheet::MERGE_CELL_CONTENT_HIDE);

        $sheet->setCellValue([16, 1], 'Output');
        $sheet->setCellValue([16, 2], 'Total');
        $sheet->setCellValue([17, 2], 'M');
        $sheet->setCellValue([18, 2], 'N');
        $sheet->setCellValue([19, 2], 'STATUS');
        $sheet->mergeCells('P1:R1', $sheet::MERGE_CELL_CONTENT_HIDE);

        $simulationPlan = collect([]);
        if ($request->dateFrom == $request->dateTo) {
            $hour = 7;

            for ($i = 1; $i <= 36; $i++) {
                if ($hour == 24) {
                    $hour = 0;
                }
                $sheet->setCellValue([19 + $i, 2], $hour . ' ~ ' . ($hour + 1));
                $hour++;
            }

            $uniqueLine = [];
            foreach ($data as $r) {
                if (!in_array($r->line_code, $uniqueLine)) {
                    $uniqueLine[] = $r->line_code;
                }
            }

            foreach ($uniqueLine as $r) {
                $dataCalc = DB::table('keikaku_calcs')->whereNull('deleted_at')->where('production_date', $request->dateFrom)
                    ->where('line_code', $r)
                    ->orderBy('calculation_at')
                    ->get([
                        'plan_worktime',
                        'efficiency',
                        'calculation_at',
                        'flag_mot',
                        DB::raw("plan_worktime*efficiency as effective_worktime"),
                    ]);

                if (!$dataCalc->isEmpty()) {
                    $dataKeikakuData = DB::table('keikaku_data')
                        ->whereNull('deleted_at')
                        ->where('production_date', $request->dateFrom)
                        ->where('line_code', $r)
                        ->orderBy('id')
                        ->get(['*', DB::raw("cycle_time/3600*plan_qty as production_worktime"), DB::raw("cycle_time/3600 ct_hour")]);

                    $dataSensor = DB::table('keikaku_outputs')->whereNull('deleted_at')
                        ->where('production_date', $request->dateFrom)
                        ->where('line_code', $r)
                        ->groupBy('wo_code', 'running_at', 'process_code', 'seq_data')
                        ->get([DB::raw('sum(ok_qty) ok_qty'), 'wo_code', 'running_at', 'process_code', 'seq_data']);

                    $dataModelChanges = DB::table('keikaku_model_changes')->whereNull('deleted_at')
                        ->where('production_date', $request->dateFrom)
                        ->where('line_code', $r)
                        ->groupBy('wo_code', 'running_at', 'process_code', 'seq_data', 'change_flag')
                        ->get([DB::raw('change_flag'), 'wo_code', 'running_at', 'process_code', 'seq_data']);

                    $asProdPlan = $this->plotProdPlan($dataKeikakuData, $dataCalc, $dataSensor, $dataModelChanges, [], [], []);

                    $simulationPlan = $simulationPlan->merge($asProdPlan[1]);
                }
            }
        }

        $countSimulationPlan = count($simulationPlan);

        $rowAt = 3;
        $previousSpec = '';
        $previousAssyCode = '';
        $previousSide = '';
        $previousLine = '';
        $lineMaster = DB::table('wms_v_get_line_category')->get();
        foreach ($data as $r) {
            $isReleased = $dataRelease->where('line_code', $r->line_code)
                ->where('production_date', $r->production_date)->count();
            if (!$this->isHWContext(['line' => $r->line_code]) && $lineMaster->where('line_code', $r->line_code)->whereIn('line_category', ['MC', 'RG', 'AX', 'TES', 'TS'])->count() > 0) {
                $_label = '';
                if (($r->morningOutput > 0 || $r->nightOutput > 0) && $r->line_code == $previousLine) {
                    if (substr($previousSpec, 0, 4) == substr($r->specs, 0, 4) && $previousSide == $r->specs_side) {
                        $_label = $r->item_code == $previousAssyCode ? '' : 'CHANGE TYPE';
                    } else {
                        $_label = "CHANGE MODEL";
                    }
                }
                $sheet->setCellValue([1, $rowAt], $r->production_date);
                $sheet->setCellValue([2, $rowAt], $r->line_code);
                $sheet->setCellValue([3, $rowAt], $r->model_code);
                $sheet->setCellValue([4, $rowAt], $r->specs);
                $sheet->setCellValue([5, $rowAt], $r->item_code);
                $sheet->getStyle([5, $rowAt])->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                $sheet->setCellValue([6, $rowAt], $r->wo_full_code);
                $sheet->setCellValue([7, $rowAt], $r->specs_side);
                $sheet->setCellValue([8, $rowAt], $r->cycle_time);
                $sheet->setCellValue([9, $rowAt], $r->lot_size);
                $sheet->setCellValue([10, $rowAt], $r->plan_morning_qty + $r->plan_night_qty);
                $sheet->setCellValue([11, $rowAt], $r->plan_morning_qty);
                $sheet->setCellValue([12, $rowAt], $r->plan_night_qty);
                $sheet->setCellValue([13, $rowAt], $r->plan_morning_qty + $r->plan_night_qty);
                $sheet->setCellValue([14, $rowAt], $r->plan_morning_qty);
                $sheet->setCellValue([15, $rowAt], $r->plan_night_qty);
                $sheet->setCellValue([16, $rowAt], $r->morningOutput + $r->nightOutput);
                $sheet->setCellValue([17, $rowAt], $r->morningOutput);
                $sheet->setCellValue([18, $rowAt], $r->nightOutput);
                $sheet->setCellValue([19, $rowAt], $_label);
                if ($request->dateFrom == $request->dateTo) {
                    for ($rs = 4; $rs < $countSimulationPlan; $rs++) {
                        if ($simulationPlan[$rs][5]) {
                            $rawInfo = explode('#', $simulationPlan[$rs][5]);
                            $line = $rawInfo[8];
                            $wo = $simulationPlan[$rs][3];
                            $seq = $rawInfo[7];
                            if ($line == $r->line_code) {
                                if ($wo == $r->wo_full_code && $seq == $r->seq) {
                                    for ($c = 6; $c < 41; $c++) {
                                        if ($simulationPlan[$rs][$c] > 0) {
                                            $sheet->setCellValue([19 + ($c - 5), $rowAt], $simulationPlan[$rs][$c]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                if ($isReleased == 0) {
                    $sheet->getStyle('A' . $rowAt . ':I' . $rowAt)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('f59a5d');
                }
                $rowAt++;
                $previousSpec = $r->specs;
                $previousAssyCode = $r->item_code;
                $previousSide = $r->specs_side;
                $previousLine = $r->line_code;
            }
        }

        $sheet->getStyle('I1:R' . $rowAt)->getNumberFormat()->setFormatCode('#,##0');

        foreach (range('A', 'Z') as $v) {
            $sheet->getColumnDimension($v)->setAutoSize(true);
        }

        $sheet->freezePane('A3');


        $sheet = $spreadSheet->createSheet();
        $sheet->setTitle('keikaku_hw');
        $sheet->setCellValue([1, 1], 'Production Date');
        $sheet->setCellValue([2, 1], 'Line Code');
        $sheet->setCellValue([3, 1], 'Model');
        $sheet->setCellValue([4, 1], 'Spec');
        $sheet->setCellValue([5, 1], 'Assy Code');
        $sheet->setCellValue([6, 1], 'Job No');
        $sheet->setCellValue([7, 1], 'Specs');
        $sheet->setCellValue([7, 2], 'Side');
        $sheet->setCellValue([8, 1], 'CT / Process');
        $sheet->setCellValue([9, 1], 'Qty');
        $sheet->setCellValue([9, 2], 'Lot Size');
        $sheet->setCellValue([10, 1], 'Plan');
        $sheet->setCellValue([10, 2], 'Total');
        $sheet->setCellValue([11, 2], 'M');
        $sheet->setCellValue([12, 2], 'N');
        $sheet->mergeCells('J1:L1', $sheet::MERGE_CELL_CONTENT_HIDE);

        $sheet->setCellValue([13, 1], 'Input 1');
        $sheet->setCellValue([13, 2], 'Total');
        $sheet->setCellValue([14, 2], 'M');
        $sheet->setCellValue([15, 2], 'N');
        $sheet->mergeCells('M1:O1', $sheet::MERGE_CELL_CONTENT_HIDE);

        $sheet->setCellValue([16, 1], 'Input 2');
        $sheet->setCellValue([16, 2], 'Total');
        $sheet->setCellValue([17, 2], 'M');
        $sheet->setCellValue([18, 2], 'N');
        $sheet->mergeCells('P1:R1', $sheet::MERGE_CELL_CONTENT_HIDE);

        $sheet->setCellValue([19, 1], 'Output');
        $sheet->setCellValue([19, 2], 'Total');
        $sheet->setCellValue([20, 2], 'M');
        $sheet->setCellValue([21, 2], 'N');
        $sheet->mergeCells('S1:U1', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->setCellValue([22, 2], 'Status');

        $sheet->freezePane('A3');


        if ($request->dateFrom == $request->dateTo) {
            $hour = 7;
            for ($i = 1; $i <= 36; $i++) {
                if ($hour == 24) {
                    $hour = 0;
                }
                $sheet->setCellValue([22 + $i, 2], $hour . ' ~ ' . ($hour + 1));
                $hour++;
            }
        }

        $rowAt = 3;
        $previousSpec = '';
        $previousAssyCode = '';
        $previousSide = '';
        $previousLine = '';
        foreach ($data as $r) {
            $isReleased = $dataRelease->where('line_code', $r->line_code)
                ->where('production_date', $r->production_date)->count();
            if ($this->isHWContext(['line' => $r->line_code]) || $lineMaster->where('line_code', $r->line_code)->whereIn('line_category', ['MC', 'RG', 'AX', 'TES', 'TS'])->count() == 0) {
                $_label = '';
                if (($r->output_hw_in2_m_qty > 0 || $r->output_hw_in2_n_qty > 0
                    || $r->morningOutput > 0 || $r->nightOutput > 0) && $r->line_code == $previousLine) {
                    if (substr($previousSpec, 0, 4) == substr($r->specs, 0, 4) && $previousSide == $r->specs_side) {
                        $_label = $r->item_code == $previousAssyCode ? '' : 'CHANGE TYPE';
                    } else {
                        $_label = "CHANGE MODEL";
                    }
                }
                $sheet->setCellValue([1, $rowAt], $r->production_date);
                $sheet->setCellValue([2, $rowAt], $r->line_code);
                $sheet->setCellValue([3, $rowAt], $r->model_code);
                $sheet->setCellValue([4, $rowAt], $r->specs);
                $sheet->setCellValue([5, $rowAt], $r->item_code);
                $sheet->getStyle([5, $rowAt])->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                $sheet->setCellValue([6, $rowAt], $r->wo_full_code);
                $sheet->setCellValue([7, $rowAt], $r->specs_side);
                $sheet->setCellValue([8, $rowAt], $r->cycle_time);
                $sheet->setCellValue([9, $rowAt], $r->lot_size);
                $sheet->setCellValue([10, $rowAt], $r->plan_morning_qty + $r->plan_night_qty);
                $sheet->setCellValue([11, $rowAt], $r->plan_morning_qty);
                $sheet->setCellValue([12, $rowAt], $r->plan_night_qty);
                $sheet->setCellValue([13, $rowAt], $r->output_hw_in1_m_qty + $r->output_hw_in1_n_qty);
                $sheet->setCellValue([14, $rowAt], $r->output_hw_in1_m_qty);
                $sheet->setCellValue([15, $rowAt], $r->output_hw_in1_n_qty);
                $sheet->setCellValue([16, $rowAt], $r->output_hw_in2_m_qty + $r->output_hw_in2_n_qty);
                $sheet->setCellValue([17, $rowAt], $r->output_hw_in2_m_qty);
                $sheet->setCellValue([18, $rowAt], $r->output_hw_in2_n_qty);
                if (!$this->isHWContext(['line' => $r->line_code])) {
                    $sheet->setCellValue([19, $rowAt], $r->morningOutput + $r->nightOutput);
                    $sheet->setCellValue([20, $rowAt], $r->morningOutput);
                    $sheet->setCellValue([21, $rowAt], $r->nightOutput);
                } else {
                    $sheet->setCellValue([19, $rowAt], $r->output_hw_m_qty + $r->output_hw_n_qty);
                    $sheet->setCellValue([20, $rowAt], $r->output_hw_m_qty);
                    $sheet->setCellValue([21, $rowAt], $r->output_hw_n_qty);
                }
                $sheet->setCellValue([22, $rowAt], $_label);

                if ($request->dateFrom == $request->dateTo) {
                    for ($rs = 4; $rs < $countSimulationPlan; $rs++) {
                        if ($simulationPlan[$rs][5]) {
                            $rawInfo = explode('#', $simulationPlan[$rs][5]);
                            $line = $rawInfo[8];
                            $wo = $simulationPlan[$rs][3];
                            $seq = $rawInfo[7];
                            if ($line == $r->line_code) {
                                if ($wo == $r->wo_full_code && $seq == $r->seq) {
                                    for ($c = 6; $c < 41; $c++) {
                                        if ($simulationPlan[$rs][$c] > 0) {
                                            $sheet->setCellValue([22 + ($c - 5), $rowAt], $simulationPlan[$rs][$c]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                if ($isReleased == 0) {
                    $sheet->getStyle('A' . $rowAt . ':I' . $rowAt)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('f59a5d');
                }
                $rowAt++;
                $previousSpec = $r->specs;
                $previousAssyCode = $r->item_code;
                $previousSide = $r->specs_side;
                $previousLine = $r->line_code;
            }
        }

        $sheet->getStyle('I1:R' . $rowAt)->getNumberFormat()->setFormatCode('#,##0');

        foreach (range('A', 'Z') as $v) {
            $sheet->getColumnDimension($v)->setAutoSize(true);
        }

        $sheet = $spreadSheet->createSheet();
        $sheet->setTitle('data');
        $sheet->freezePane('A2');
        $sheet->setCellValue([1, 1], 'Production Date');
        $sheet->setCellValue([2, 1], 'Line Code');
        $sheet->setCellValue([3, 1], 'Seq');
        $sheet->setCellValue([4, 1], 'Model');
        $sheet->setCellValue([5, 1], 'Job');
        $sheet->setCellValue([6, 1], 'Lot');
        $sheet->setCellValue([7, 1], 'Production');
        $sheet->setCellValue([8, 1], 'Type');
        $sheet->setCellValue([9, 1], 'Spec');
        $sheet->setCellValue([10, 1], 'Assy Code');
        $sheet->setCellValue([11, 1], 'Remark');
        $sheet->setCellValue([12, 1], 'Specs Side');
        $sheet->setCellValue([13, 1], 'CT');
        $sheet->setCellValue([14, 1], 'Production Result');
        $sheet->setCellValue([15, 1], 'Difference');

        $rowAt = 2;
        foreach ($data as $r) {
            $sheet->setCellValue([1, $rowAt], $r->production_date);
            $sheet->setCellValue([2, $rowAt], $r->line_code);
            $sheet->setCellValue([3, $rowAt], $r->seq);
            $sheet->setCellValue([4, $rowAt], $r->model_code);
            $sheet->setCellValueExplicit([5, $rowAt], $r->wo_code, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue([6, $rowAt], $r->lot_size);
            $sheet->setCellValue([7, $rowAt], $r->plan_qty);
            $sheet->setCellValue([8, $rowAt], $r->type);
            $sheet->setCellValue([9, $rowAt], $r->specs);
            $sheet->setCellValue([10, $rowAt], $r->item_code);
            $sheet->setCellValue([11, $rowAt], $r->packaging);
            $sheet->setCellValue([12, $rowAt], $r->specs_side);
            $sheet->setCellValue([13, $rowAt], $r->cycle_time);
            $sheet->setCellValue([14, $rowAt], $r->morningOutput + $r->nightOutput);
            $sheet->setCellValue([15, $rowAt], "=IF(N$rowAt =0,0, N$rowAt-G$rowAt)");
            $rowAt++;
        }

        $stringjudul = "Keikaku from " . $request->dateFrom . " to " . $request->dateTo;
        $writer = IOFactory::createWriter($spreadSheet, 'Xlsx');
        $filename = $stringjudul;
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
    }

    function saveKeikakuModelChanges(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'line' => 'required',
            'job' => 'required',
            'side' => 'required',
        ], [
            'line.required' => ':attribute is required',
            'job.required' => ':attribute is required',
            'side.required' => ':attribute is required',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 406);
        }

        $running_at = $request->productionDate . ' ' . $request->runningAtTime . ':00';
        $nextDate = date_create($request->productionDate);
        date_add($nextDate, date_interval_create_from_date_string('1 days'));

        if ($request->XCoordinate >= 26) {
            $_date = date_create($request->productionDate);
            date_add($_date, date_interval_create_from_date_string('1 days'));
            $running_at = date_format($_date, 'Y-m-d') . ' ' . $request->runningAtTime . ':00';
        }

        DB::table('keikaku_model_changes')
            ->where("running_at",  $running_at)
            ->where('line_code', $request->line)
            ->where('wo_code', $request->job)
            ->where('process_code', $request->side)
            ->where('seq_data', $request->seq_data)
            ->update(['deleted_at' => date('Y-m-d H:i:s')]);

        $affectedRows = DB::table('keikaku_model_changes')->insert([
            'created_at' => date('Y-m-d H:i:s'),
            'production_date' => $request->productionDate,
            'running_at' => $running_at,
            'wo_code' => $request->job,
            'line_code' =>  $request->line,
            'process_code' => $request->side,
            'change_flag' => $request->change_flag,
            'seq_data' => $request->seq_data,
            'created_by' => $request->user_id,
        ]);

        return $affectedRows ? ['message' => 'Recorded successfully.'] : ['message' => 'Failed, please try again.'];
    }

    function getProductionOutputReport(Request $request)
    {
        $date1 = date_create($request->dateFrom);
        $date2 = date_create($request->dateTo);
        $dateDiff = date_diff($date1, $date2);
        $dateDiffValue = $dateDiff->format('%a');
        $theParam = [
            'dateFrom' => $request->dateFrom,
            'dateTo' => $request->dateTo,
        ];
        $data_ = $this->_getBaseKeikakuDataReport($theParam);
        $data = $data_[0];
        $data1 = $data_[1];
        $uniqueLine = $data_[2];
        $productionDownTime = DB::table('production_downtime')
            ->where('production_date', '>=', $request->dateFrom)
            ->where('production_date', '<=', $request->dateTo)
            ->whereNull('deleted_at')
            ->get(['production_date', 'line_code', 'req_minutes', 'shift_code']);

        $spreadSheet = new Spreadsheet();
        $sheet = $spreadSheet->getActiveSheet();
        $sheet->freezePane('A3');
        $sheet->setCellValue([1, 1], 'Line');
        $sheet->mergeCells('A1:A2', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->setCellValue([2, 1], 'Date');
        $sheet->mergeCells('B1:B2', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->setCellValue([3, 1], 'Mounting');
        $sheet->mergeCells('C1:E1', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->setCellValue([3, 2], 'M');
        $sheet->setCellValue([4, 2], 'N');
        $sheet->setCellValue([5, 2], 'Total');

        $sheet->setCellValue([6, 1], 'Jam Kerja Biasa');
        $sheet->mergeCells('F1:H1', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->setCellValue([6, 2], 'M');
        $sheet->setCellValue([7, 2], 'N');
        $sheet->setCellValue([8, 2], 'Total');

        $sheet->setCellValue([9, 1], 'Jam Kerja Aktual');
        $sheet->mergeCells('I1:K1', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->setCellValue([9, 2], 'M');
        $sheet->setCellValue([10, 2], 'N');
        $sheet->setCellValue([11, 2], 'Total');

        $sheet->setCellValue([12, 1], 'Jam Kerja Kalkulasi');
        $sheet->mergeCells('L1:N1', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->setCellValue([12, 2], 'M');
        $sheet->setCellValue([13, 2], 'N');
        $sheet->setCellValue([14, 2], 'Total');

        $sheet->setCellValue([15, 1], 'Eff');
        $sheet->setCellValue([15, 2], 'Biasa');

        $sheet->setCellValue([16, 1], 'Eff');
        $sheet->setCellValue([16, 2], 'Aktual');

        $sheet->setCellValue([17, 1], 'Input');
        $sheet->mergeCells('Q1:S1', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->setCellValue([17, 2], 'M');
        $sheet->setCellValue([18, 2], 'N');
        $sheet->setCellValue([19, 2], 'Total');

        $sheet->setCellValue([20, 1], 'Output');
        $sheet->mergeCells('T1:V1', $sheet::MERGE_CELL_CONTENT_HIDE);
        $sheet->setCellValue([20, 2], 'M');
        $sheet->setCellValue([21, 2], 'N');
        $sheet->setCellValue([22, 2], 'Total');
        $sheet->getStyle('A1:V2')->getFont()->setBold(true);
        $rowAt = 3;
        foreach ($uniqueLine as $l) {
            $nextDate = $request->dateFrom;
            for ($d = 0; $d <= $dateDiffValue; $d++) {
                $sheet->setCellValue([1, $rowAt], $l);
                $sheet->setCellValue([2, $rowAt], $nextDate);
                $ttlOutputMorning = 0;
                $ttlOutputNight = 0;
                $theMorningHour = 0;
                $theNightHour = 0;
                $ttlDowntimeMorningHour = 0;
                $ttlDowntimeNightHour = 0;
                $ttlMorningHourCalc = 0;
                $ttlNightHourCalc = 0;
                $ttlQtyPlanMorning = 0;
                $ttlQtyPlanNight = 0;
                $ttlQtyActualMorning = 0;
                $ttlQtyActualNight = 0;
                foreach ($data as $r) {
                    if ($r->production_date == $nextDate && ($r->morningOutput > 0 || $r->nightOutput > 0) && $r->line_code == $l) {
                        $ttlOutputMorning += ($r->morningOutput * $r->baseMount);
                        $ttlOutputNight += ($r->nightOutput * $r->baseMount);
                        $theMorningHour = $r->total_plan_worktime_morning;
                        $theNightHour = $r->total_plan_worktime_night;
                        $ttlMorningHourCalc += $r->plan_morning_qty * $r->cycle_time / 3600;
                        $ttlNightHourCalc += $r->plan_night_qty * $r->cycle_time / 3600;
                        $ttlQtyPlanMorning += $r->plan_morning_qty;
                        $ttlQtyPlanNight += $r->plan_night_qty;
                        $ttlQtyActualMorning += $r->morningOutput;
                        $ttlQtyActualNight += $r->nightOutput;
                    }
                }
                foreach ($productionDownTime as $dt) {
                    if ($dt->production_date == $nextDate && $dt->line_code == $l && $dt->shift_code == 'M') {
                        $ttlDowntimeMorningHour += $dt->req_minutes / 60;
                    }
                    if ($dt->production_date == $nextDate && $dt->line_code == $l && $dt->shift_code == 'N') {
                        $ttlDowntimeNightHour += $dt->req_minutes / 60;
                    }
                }

                $sheet->setCellValue([3, $rowAt], $ttlOutputMorning);
                $sheet->setCellValue([4, $rowAt], $ttlOutputNight);
                $sheet->setCellValue([5, $rowAt], "=SUM(C$rowAt:D$rowAt)");
                $sheet->setCellValue([6, $rowAt], $theMorningHour);
                $sheet->setCellValue([7, $rowAt], $theNightHour);
                $sheet->setCellValue([8, $rowAt], "=SUM(F$rowAt:G$rowAt)");
                $sheet->setCellValue([9, $rowAt], round($theMorningHour - $ttlDowntimeMorningHour, 1));
                $sheet->setCellValue([10, $rowAt], round($theNightHour - $ttlDowntimeNightHour, 1));
                $sheet->setCellValue([11, $rowAt], "=SUM(I$rowAt:J$rowAt)");
                $sheet->setCellValue([12, $rowAt], round($ttlMorningHourCalc, 1));
                $sheet->setCellValue([13, $rowAt], round($ttlNightHourCalc, 1));
                $sheet->setCellValue([14, $rowAt], "=SUM(L$rowAt:M$rowAt)");
                $sheet->setCellValue([15, $rowAt], "=IFERROR(N$rowAt/H$rowAt,0)");
                $sheet->setCellValue([16, $rowAt], "=IFERROR(N$rowAt/K$rowAt,0)");
                $sheet->setCellValue([17, $rowAt], $ttlQtyPlanMorning);
                $sheet->setCellValue([18, $rowAt], $ttlQtyPlanNight);
                $sheet->setCellValue([19, $rowAt], "=SUM(Q$rowAt:R$rowAt)");
                $sheet->setCellValue([20, $rowAt], $ttlQtyActualMorning);
                $sheet->setCellValue([21, $rowAt], $ttlQtyActualNight);
                $sheet->setCellValue([22, $rowAt], "=SUM(T$rowAt:U$rowAt)");

                $nextDate = date_create($nextDate);
                $day = date_format($nextDate, 'N');
                if (in_array($day, [7, 6])) {
                    $sheet->getStyle('A' . $rowAt . ':Z' . $rowAt)->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_RED);
                }

                date_add($nextDate, date_interval_create_from_date_string('1 days'));
                $nextDate = date_format($nextDate, 'Y-m-d');

                $rowAt++;
            }
        }
        $sheet->getStyle('C3:E' . $rowAt)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('O3:P' . $rowAt)->getNumberFormat()->setFormatCode('0.00%');
        $sheet->getStyle('Q3:V' . $rowAt)->getNumberFormat()->setFormatCode('#,##0');

        // downtime sheet
        $sheet = $spreadSheet->createSheet();
        $sheet->setTitle('down_time');
        $sheet->freezePane('A2');

        $dataDownTime = DB::table('production_downtime')
            ->leftJoin('downtime_category', 'downtime_code', '=', 'downtime_category.id')
            ->whereNull('production_downtime.deleted_at')
            ->whereDate('running_at', '>=', $request->dateFrom)
            ->whereDate('running_at', '<=', $request->dateTo)
            ->whereNotNull('req_minutes')
            ->orderBy('line_code')
            ->orderBy('running_at')
            ->get(['line_code', 'running_at', 'req_minutes', 'description', 'remark']);

        $sheet->setCellValue([1, 1], 'Line');
        $sheet->setCellValue([2, 1], 'Downtime At');
        $sheet->setCellValue([3, 1], 'Downtime Minutes');
        $sheet->setCellValue([4, 1], 'Category');
        $sheet->setCellValue([5, 1], 'Remark');
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);

        $rowAt = 2;
        foreach ($dataDownTime as $r) {
            $sheet->setCellValue([1, $rowAt], $r->line_code);
            $sheet->setCellValue([2, $rowAt], $r->running_at);
            $sheet->setCellValue([3, $rowAt], $r->req_minutes);
            $sheet->setCellValue([4, $rowAt], $r->description);
            $sheet->setCellValue([5, $rowAt], $r->remark);
            $rowAt++;
        }
        foreach (range('A', 'Z') as $v) {
            $sheet->getColumnDimension($v)->setAutoSize(true);
        }

        // raw data sheet
        $data = json_decode(json_encode($data), true);
        $sheet = $spreadSheet->createSheet();
        $sheet->setTitle('raw_data');
        $sheet->setCellValue([1, 1], 'id');
        $sheet->setCellValue([2, 1], 'created_at');
        $sheet->setCellValue([3, 1], 'line_code');
        $sheet->setCellValue([4, 1], 'production_date');
        $sheet->setCellValue([5, 1], 'seq');
        $sheet->setCellValue([6, 1], 'model_code');
        $sheet->setCellValue([7, 1], 'wo_code');
        $sheet->setCellValue([8, 1], 'wo_full_code');
        $sheet->setCellValue([9, 1], 'item_code');
        $sheet->setCellValue([10, 1], 'lot_size');
        $sheet->setCellValue([11, 1], 'plan_qty');
        $sheet->setCellValue([12, 1], 'actual_qty');
        $sheet->setCellValue([13, 1], 'type');
        $sheet->setCellValue([14, 1], 'specs');
        $sheet->setCellValue([15, 1], 'specs_side');
        $sheet->setCellValue([16, 1], 'cycle_time');
        $sheet->setCellValue([17, 1], 'packaging');
        $sheet->setCellValue([18, 1], 'created_by');
        $sheet->setCellValue([19, 1], 'plan_morning_qty');
        $sheet->setCellValue([20, 1], 'plan_night_qty');
        $sheet->setCellValue([21, 1], 'bom_rev');
        $sheet->setCellValue([22, 1], 'ok_qty');
        $sheet->setCellValue([23, 1], 'total_plan_worktime_morning');
        $sheet->setCellValue([24, 1], 'total_plan_worktime_night');
        $sheet->setCellValue([25, 1], 'morningOutput');
        $sheet->setCellValue([26, 1], 'nightOutput');
        $sheet->setCellValue([27, 1], 'baseMount');
        $rowAt = 2;
        foreach ($data as $r) {
            $sheet->setCellValue([1, $rowAt], $r['id']);
            $sheet->setCellValue([2, $rowAt], $r['created_at']);
            $sheet->setCellValue([3, $rowAt], $r['line_code']);
            $sheet->setCellValue([4, $rowAt], $r['production_date']);
            $sheet->setCellValue([5, $rowAt], $r['seq']);
            $sheet->setCellValue([6, $rowAt], $r['model_code']);
            $sheet->setCellValueExplicit([7, $rowAt], $r['wo_code'],  \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue([8, $rowAt], $r['wo_full_code']);
            $sheet->setCellValue([9, $rowAt], $r['item_code']);
            $sheet->setCellValue([10, $rowAt], $r['lot_size']);
            $sheet->setCellValue([11, $rowAt], $r['plan_qty']);
            $sheet->setCellValue([12, $rowAt], $r['actual_qty']);
            $sheet->setCellValue([13, $rowAt], $r['type']);
            $sheet->setCellValue([14, $rowAt], $r['specs']);
            $sheet->setCellValue([15, $rowAt], $r['specs_side']);
            $sheet->setCellValue([16, $rowAt], $r['cycle_time']);
            $sheet->setCellValue([17, $rowAt], $r['packaging']);
            $sheet->setCellValue([18, $rowAt], $r['created_by']);
            $sheet->setCellValue([19, $rowAt], $r['plan_morning_qty']);
            $sheet->setCellValue([20, $rowAt], $r['plan_night_qty']);
            $sheet->setCellValue([21, $rowAt], $r['bom_rev']);
            $sheet->setCellValue([22, $rowAt], $r['ok_qty']);
            $sheet->setCellValue([23, $rowAt], $r['total_plan_worktime_morning']);
            $sheet->setCellValue([24, $rowAt], $r['total_plan_worktime_night']);
            $sheet->setCellValue([25, $rowAt], $r['morningOutput']);
            $sheet->setCellValue([26, $rowAt], $r['nightOutput']);
            $sheet->setCellValue([27, $rowAt], $r['baseMount']);

            $rowAt++;
        }

        $sheet->freezePane('A2');
        foreach (range('A', 'Z') as $v) {
            $sheet->getColumnDimension($v)->setAutoSize(true);
        }

        // raw data mount sheet
        $sheet = $spreadSheet->createSheet();
        $sheet->setTitle('raw_data_mount');
        $sheet->fromArray(array_keys($data1[0]), null, 'A1');
        $sheet->fromArray($data1, null, 'A2');
        foreach (range('A', 'Z') as $v) {
            $sheet->getColumnDimension($v)->setAutoSize(true);
        }
        $sheet->freezePane('A2');

        $stringjudul = "Daily Output from " . $request->dateFrom . " to " . $request->dateTo;
        $writer = IOFactory::createWriter($spreadSheet, 'Xlsx');
        $filename = $stringjudul;
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
    }

    function getWOHistoryData($params): Builder
    {
        if (isset($params['multiple'])) {
            $dataOutput = DB::table('keikaku_outputs')
                ->whereIn('wo_code', $params['doc'])
                ->whereNull('deleted_at')
                ->where('created_by', '!=', 'sensor')
                ->groupBy('wo_code', 'process_code', 'production_date', 'line_code', 'seq_data')
                ->select('wo_code', 'process_code', 'production_date', 'line_code', DB::raw("sum(case
                        when production_date = convert(date, running_at) then ok_qty
                        else case
                        when convert(char(5), running_at, 108) < '07:00' then ok_qty
                        end
                    end) ok_qty"), 'seq_data'); // TO output

            $dataInput2HW = DB::table('keikaku_input3s')
                ->whereIn('wo_code', $params['doc'])
                ->whereNull('deleted_at')
                ->where('created_by', '!=', 'sensor')
                ->groupBy('wo_code', 'process_code', 'production_date', 'line_code', 'seq_data')
                ->select('wo_code', 'process_code', 'production_date', 'line_code', DB::raw("sum(case
                        when production_date = convert(date, running_at) then ok_qty
                        else case
                        when convert(char(5), running_at, 108) < '07:00' then ok_qty
                        end
                    end) ok_qty_hw"), 'seq_data');

            $dataOutputHW = DB::table('keikaku_output2s')
                ->whereIn('wo_code', $params['doc'])
                ->whereNull('deleted_at')
                ->where('created_by', '!=', 'sensor')
                ->groupBy('wo_code', 'process_code', 'production_date', 'line_code', 'seq_data')
                ->select('wo_code', 'process_code', 'production_date', 'line_code', DB::raw("sum(case
                        when production_date = convert(date, running_at) then ok_qty
                        else case
                        when convert(char(5), running_at, 108) < '07:00' then ok_qty
                        end
                    end) ok_output_qty_hw"), 'seq_data');

            $WIPoutput = DB::table('w_i_p_outputs')
                ->whereNull('deleted_at')
                ->whereIn('wo_full_code', $params['doc'])
                ->groupBy('wo_full_code', 'production_date', 'line_code', 'item_code')
                ->select([
                    'production_date',
                    'line_code',
                    'wo_full_code',
                    DB::raw("0 plan_qty"),
                    DB::raw("SUM(ok_qty) as ok_qty"),
                    DB::raw("SUM(ok_qty) as ok_qty_hw"),
                    DB::raw("'A' specs_side"),
                    DB::raw("'' lot_size"),
                    'item_code'
                ]);

            $dataBasic = DB::table('keikaku_data')
                ->whereNull('deleted_at')
                ->whereIn('wo_full_code', $params['doc'])
                ->where('production_date', '<=', $params['cutoff_date'])
                ->groupBy('production_date', 'line_code', 'wo_full_code', 'plan_qty', 'specs_side', 'seq')
                ->select(
                    'production_date',
                    'line_code',
                    'wo_full_code',
                    'plan_qty',
                    'specs_side',
                    'seq',
                    DB::raw("MAX(lot_size) lot_size"),
                    DB::raw("MAX(item_code) item_code"),
                );
        } else {
            $dataOutput = DB::table('keikaku_outputs')
                ->where('wo_code', 'like', '%' . $params['doc'] . '%')
                ->whereNull('deleted_at')
                ->where('created_by', '!=', 'sensor')
                ->groupBy('wo_code', 'process_code', 'production_date', 'line_code', 'seq_data')
                ->select('wo_code', 'process_code', 'production_date', 'line_code', DB::raw("sum(case
                        when production_date = convert(date, running_at) then ok_qty
                        else case
                        when convert(char(5), running_at, 108) < '07:00' then ok_qty
                        end
                    end) ok_qty"), 'seq_data'); // TO output
            if (isset($params['keikaku_outputs'])) {
                $_condition = $params['keikaku_outputs'];
                if (isset($_condition['operator'])) {
                    if ($_condition['operator'] == 'not_in') {
                        $dataOutput->whereNotIn($_condition['column_name'], $_condition['column_value']);
                    }
                }
            }

            $dataInput2HW = DB::table('keikaku_input3s')
                ->where('wo_code', 'like', '%' . $params['doc'] . '%')
                ->whereNull('deleted_at')
                ->where('created_by', '!=', 'sensor')
                ->groupBy('wo_code', 'process_code', 'production_date', 'line_code', 'seq_data')
                ->select('wo_code', 'process_code', 'production_date', 'line_code', DB::raw("sum(case
                        when production_date = convert(date, running_at) then ok_qty
                        else case
                        when convert(char(5), running_at, 108) < '07:00' then ok_qty
                        end
                    end) ok_qty_hw"), 'seq_data');
            if (isset($params['keikaku_input3s'])) {
                $_condition = $params['keikaku_input3s'];
                if (isset($_condition['operator'])) {
                    if ($_condition['operator'] == 'not_in') {
                        $dataInput2HW->whereNotIn($_condition['column_name'], $_condition['column_value']);
                    }
                }
            }

            $dataOutputHW = DB::table('keikaku_output2s')
                ->where('wo_code', 'like', '%' . $params['doc'] . '%')
                ->whereNull('deleted_at')
                ->where('created_by', '!=', 'sensor')
                ->groupBy('wo_code', 'process_code', 'production_date', 'line_code', 'seq_data')
                ->select('wo_code', 'process_code', 'production_date', 'line_code', DB::raw("sum(case
                        when production_date = convert(date, running_at) then ok_qty
                        else case
                        when convert(char(5), running_at, 108) < '07:00' then ok_qty
                        end
                    end) ok_output_qty_hw"), 'seq_data');

            $WIPoutput = DB::table('w_i_p_outputs')
                ->whereNull('deleted_at')
                ->where('wo_full_code', 'like', '%' . $params['doc'] . '%')
                ->groupBy('wo_full_code', 'production_date', 'line_code', 'item_code')
                ->select([
                    'production_date',
                    'line_code',
                    'wo_full_code',
                    DB::raw("0 plan_qty"),
                    DB::raw("SUM(ok_qty) as ok_qty"),
                    DB::raw("SUM(ok_qty) as ok_qty_hw"),
                    DB::raw("'A' specs_side"),
                    DB::raw("'' lot_size"),
                    'item_code'
                ]);

            $dataBasic = DB::table('keikaku_data')
                ->whereNull('deleted_at')
                ->where('wo_full_code', 'like', '%' . $params['doc'] . '%')
                ->groupBy('production_date', 'line_code', 'wo_full_code', 'plan_qty', 'specs_side', 'seq')
                ->select(
                    'production_date',
                    'line_code',
                    'wo_full_code',
                    'plan_qty',
                    'specs_side',
                    'seq',
                    DB::raw("MAX(lot_size) lot_size"),
                    DB::raw("MAX(item_code) item_code"),
                );
        }

        $data = DB::query()->fromSub($dataBasic, 'V1')
            ->leftJoinSub($dataOutput, 'V2', function ($join) {
                $join->on('V1.production_date', '=', 'V2.production_date')
                    ->on('V1.line_code', '=', 'V2.line_code')
                    ->on('V1.wo_full_code', '=', 'V2.wo_code')
                    ->on('V1.specs_side', '=', 'V2.process_code')
                    ->on('V1.seq', '=', 'V2.seq_data')
                ;
            })
            ->leftJoinSub($dataInput2HW, 'V3', function ($join) {
                $join->on('V1.production_date', '=', 'V3.production_date')
                    ->on('V1.line_code', '=', 'V3.line_code')
                    ->on('V1.wo_full_code', '=', 'V3.wo_code')
                    ->on('V1.specs_side', '=', 'V3.process_code')
                    ->on('V1.seq', '=', 'V3.seq_data')
                ;
            })
            ->leftJoinSub($dataOutputHW, 'V4', function ($join) {
                $join->on('V1.production_date', '=', 'V4.production_date')
                    ->on('V1.line_code', '=', 'V4.line_code')
                    ->on('V1.wo_full_code', '=', 'V4.wo_code')
                    ->on('V1.specs_side', '=', 'V4.process_code')
                    ->on('V1.seq', '=', 'V4.seq_data')
                ;
            })
            ->groupBy('V1.production_date', 'V1.line_code', 'wo_full_code', 'plan_qty', 'specs_side', 'lot_size', 'item_code')
            ->select([
                'V1.production_date',
                'V1.line_code',
                'wo_full_code',
                DB::raw("SUM(plan_qty) plan_qty"),
                DB::raw("ISNULL(SUM(ok_qty),0)+ISNULL(SUM(ok_output_qty_hw),0) ok_qty"),
                DB::raw("ISNULL(SUM(ok_qty_hw),0) ok_qty_hw"),
                'specs_side',
                'lot_size',
                'item_code',
            ]);

        $dataFinal = DB::query()->fromSub($data, 'vy')->union($WIPoutput);
        return $dataFinal;
    }

    function getWOHistory(Request $request)
    {
        $datax = $this->getWOHistoryData(['doc' => $request->doc]);

        $dataWrap = $request->include_plan_only == 'Y' ?
            $datax->orderBy('production_date')
            ->orderBy('line_code')->get() :
            $datax->where('ok_qty', '>', 0)->orWhere('ok_qty_hw', '>', 0)
            ->orderBy('production_date')
            ->orderBy('line_code')->get();

        return ['data' => $dataWrap];
    }

    private function isHWContext($data)
    {
        $hwLine = [
            'A3',
            'B3',
            'C3',
            'D3',
            'E3',
            'F3',
            'H3-1',
            'H3-2',
            'H3-3',
            'J3-1',
            'J3-2',
            'K3',
            'L3',
            'M3',
            'OFFLINE 3',
            'OFFLINE 4',
            'OFFLINE PS',
            'PS2',
            'PS3',
            'ATH-1',
            'ATH-2',
            'ATH-3',
            'ATH-4',
            'M3.1',
            'M3.2'
        ];
        return in_array($data['line'], $hwLine) ? true : false;
    }

    function saveKeikakuInputHW(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'line' => 'required',
            'job' => 'required',
            'side' => 'required',
            'quantity' => 'required',
        ], [
            'line.required' => ':attribute is required',
            'job.required' => ':attribute is required',
            'side.required' => ':attribute is required',
            'quantity.required' => ':attribute is required',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 406);
        }

        $running_at = $request->productionDate . ' ' . $request->runningAtTime . ':00';
        $nextDate = date_create($request->productionDate);
        date_add($nextDate, date_interval_create_from_date_string('1 days'));

        if ($request->XCoordinate >= 26) {
            $_date = date_create($request->productionDate);
            date_add($_date, date_interval_create_from_date_string('1 days'));
            $running_at = date_format($_date, 'Y-m-d') . ' ' . $request->runningAtTime . ':00';
        }

        $productionPlan = DB::table('keikaku_data')
            ->where('production_date', $request->productionDate)
            ->where('line_code', $request->line)
            ->where('wo_full_code', $request->job)
            ->where('specs_side', $request->side)
            ->where('seq', $request->seq_data)
            ->whereNull('deleted_at')
            ->first();

        $currentOutput = DB::table('keikaku_input2s')
            ->whereDate('production_date', $request->productionDate)
            ->where("running_at", "!=", $running_at)
            ->where('line_code', $request->line)
            ->where('wo_code', $request->job)
            ->where('process_code', $request->side)
            ->where('seq_data', $request->seq_data)
            ->whereNull('deleted_at')
            ->select(DB::raw('isnull(sum(ok_qty),0) ok_qty'))
            ->first();

        $totalOutputCurrentSeq = $currentOutput->ok_qty + $request->quantity;

        if ($totalOutputCurrentSeq > $productionPlan->plan_qty) {
            return response()->json(
                ['message' => 'Prodplan=' . $productionPlan->plan_qty . ', output=' .
                    $currentOutput->ok_qty . '+' . $request->quantity],
                406
            );
        }

        DB::table('keikaku_input2s')
            ->where("running_at",  $running_at)
            ->where('line_code', $request->line)
            ->where('wo_code', $request->job)
            ->where('process_code', $request->side)
            ->where('seq_data', $request->seq_data)
            ->update(['deleted_at' => date('Y-m-d H:i:s')]);

        $affectedRows = DB::table('keikaku_input2s')->insert([
            'created_at' => date('Y-m-d H:i:s'),
            'production_date' => $request->productionDate,
            'running_at' => $running_at,
            'wo_code' => $request->job,
            'line_code' =>  $request->line,
            'process_code' => $request->side,
            'ok_qty' => $request->quantity,
            'seq_data' => $request->seq_data,
            'created_by' => $request->user_id,
        ]);

        return $affectedRows ? ['message' => 'Recorded successfully'] : ['message' => 'Failed, please try again'];
    }

    function saveKeikakuOutputHW(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'line' => 'required',
            'job' => 'required',
            'side' => 'required',
            'quantity' => 'required',
        ], [
            'line.required' => ':attribute is required',
            'job.required' => ':attribute is required',
            'side.required' => ':attribute is required',
            'quantity.required' => ':attribute is required',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 406);
        }

        $running_at = $request->productionDate . ' ' . $request->runningAtTime . ':00';
        $nextDate = date_create($request->productionDate);
        date_add($nextDate, date_interval_create_from_date_string('1 days'));

        if ($request->XCoordinate >= 26) {
            $_date = date_create($request->productionDate);
            date_add($_date, date_interval_create_from_date_string('1 days'));
            $running_at = date_format($_date, 'Y-m-d') . ' ' . $request->runningAtTime . ':00';
        }

        $productionPlan = DB::table('keikaku_data')
            ->where('production_date', $request->productionDate)
            ->where('line_code', $request->line)
            ->where('wo_full_code', $request->job)
            ->where('specs_side', $request->side)
            ->where('seq', $request->seq_data)
            ->whereNull('deleted_at')
            ->first();

        $currentOutput = DB::table('keikaku_output2s')
            ->whereDate('production_date', $request->productionDate)
            ->where("running_at", "!=", $running_at)
            ->where('line_code', $request->line)
            ->where('wo_code', $request->job)
            ->where('process_code', $request->side)
            ->where('seq_data', $request->seq_data)
            ->whereNull('deleted_at')
            ->select(DB::raw('isnull(sum(ok_qty),0) ok_qty'))
            ->first();

        if ($currentOutput->ok_qty + $request->quantity > $productionPlan->plan_qty) {
            return response()->json(
                ['message' => 'Prodplan=' . $productionPlan->plan_qty . ', output=' .
                    $currentOutput->ok_qty . '+' . $request->quantity],
                406
            );
        }

        DB::table('keikaku_output2s')
            ->where("running_at",  $running_at)
            ->where('line_code', $request->line)
            ->where('wo_code', $request->job)
            ->where('process_code', $request->side)
            ->where('seq_data', $request->seq_data)
            ->update(['deleted_at' => date('Y-m-d H:i:s')]);

        $affectedRows = DB::table('keikaku_output2s')->insert([
            'created_at' => date('Y-m-d H:i:s'),
            'production_date' => $request->productionDate,
            'running_at' => $running_at,
            'wo_code' => $request->job,
            'line_code' =>  $request->line,
            'process_code' => $request->side,
            'ok_qty' => $request->quantity,
            'seq_data' => $request->seq_data,
            'created_by' => $request->user_id,
        ]);

        return $affectedRows ? ['message' => 'Recorded successfully'] : ['message' => 'Failed, please try again'];
    }

    function saveKeikakuInput2HW(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'line' => 'required',
            'job' => 'required',
            'side' => 'required',
            'quantity' => 'required',
        ], [
            'line.required' => ':attribute is required',
            'job.required' => ':attribute is required',
            'side.required' => ':attribute is required',
            'quantity.required' => ':attribute is required',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 406);
        }

        $running_at = $request->productionDate . ' ' . $request->runningAtTime . ':00';
        $nextDate = date_create($request->productionDate);
        date_add($nextDate, date_interval_create_from_date_string('1 days'));

        if ($request->XCoordinate >= 26) {
            $_date = date_create($request->productionDate);
            date_add($_date, date_interval_create_from_date_string('1 days'));
            $running_at = date_format($_date, 'Y-m-d') . ' ' . $request->runningAtTime . ':00';
        }

        $productionPlan = DB::table('keikaku_data')
            ->where('production_date', $request->productionDate)
            ->where('line_code', $request->line)
            ->where('wo_full_code', $request->job)
            ->where('specs_side', $request->side)
            ->where('seq', $request->seq_data)
            ->whereNull('deleted_at')
            ->first();

        $currentOutput = DB::table('keikaku_input3s')
            ->whereDate('production_date', $request->productionDate)
            ->where("running_at", "!=", $running_at)
            ->where('line_code', $request->line)
            ->where('wo_code', $request->job)
            ->where('process_code', $request->side)
            ->where('seq_data', $request->seq_data)
            ->whereNull('deleted_at')
            ->select(DB::raw('isnull(sum(ok_qty),0) ok_qty'))
            ->first();

        // periksa proses konteks
        $procesMaster = DB::table('process_masters')
            ->whereNull('deleted_at')
            ->where('assy_code', $request->assy_code)
            ->groupBy('assy_code', 'process_code', 'line_code')
            ->select(
                'assy_code',
                DB::raw('MAX(process_seq) process_seq'),
                DB::raw("case 
                    when process_code = 'SMT-A' OR process_code = 'A' OR process_code = 'SMT-HW' THEN 'A'                    
                    when process_code = 'SMT-B' OR process_code = 'B' THEN 'B' 
                    ELSE 'A'
                    END process_code"),
                'line_code'
            );

        $procesMasterO = DB::query()->fromSub($procesMaster, 'v1')
            ->where('process_code', $request->side)
            ->where('line_code', $request->line)
            ->first();

        $historyDataJoin = [];

        if (!empty($procesMasterO)) {
            if ($procesMasterO->process_seq > 1 && $request->quantity != 0) { // hanya untuk seq > 1
                $totalOutputCurrentSeq = $request->quantity;
                $historyData = $this->getWOHistoryData([
                    'doc' => $request->job,
                    'cutoff_date' => $request->production_date,
                    'keikaku_input3s' => [
                        'column_name' => 'running_at',
                        'column_value' => [$running_at],
                        'operator' => 'not_in'
                    ]
                ])->where('ok_qty', '>', 0)->orWhere('ok_qty_hw', '>', 0);

                $historyDataJoinSQL = DB::query()->fromSub($historyData, 'V2')
                    ->leftJoinSub($procesMaster, 'V3', function ($join) {
                        $join->on('V2.line_code', '=', 'V3.line_code')
                            ->on('V2.specs_side', '=', 'V3.process_code')
                            ->on('V2.item_code', '=', 'V3.assy_code')
                        ;
                    })
                    ->groupBy('wo_full_code', 'process_seq')
                    ->whereRaw("ISNULL(process_seq,'')>=" . ($procesMasterO->process_seq - 1))
                    ->select(
                        'wo_full_code',
                        'process_seq',
                        DB::raw("SUM(ok_qty)+SUM(ok_qty_hw) ok_qty"),
                        DB::raw("isnull(SUM(ok_qty_hw),0) ok_qty_hw"),
                    );
                $historyDataJoin = $historyDataJoinSQL->get();

                $_totalPrevSeq = $historyDataJoin->where('process_seq', ($procesMasterO->process_seq - 1))->first();
                $_totalCurrentSeq = $historyDataJoin->where('process_seq', $procesMasterO->process_seq)->first();

                $_totalPrevSeqV = 0;

                if (!empty($_totalPrevSeq)) {
                    $_totalPrevSeqV = $_totalPrevSeq->ok_qty ?? 0;
                }

                if (!empty($_totalCurrentSeq)) {
                    $totalOutputCurrentSeq += $_totalCurrentSeq->ok_qty_hw ?? 0;
                }

                if (!empty($historyDataJoin)) {
                    if ($totalOutputCurrentSeq > $_totalPrevSeqV) {
                        return response()->json(
                            [
                                'message' => 'Previous Process[' . $_totalPrevSeq->process_seq . ']=' . (int)$_totalPrevSeqV . ', Input2=' .
                                    $totalOutputCurrentSeq,
                            ],
                            406
                        );
                    } else {
                        $totalOutputCurrentSeq = $currentOutput->ok_qty ?? 0 + $request->quantity;
                    }
                }
            }
        }

        if ($currentOutput->ok_qty + $request->quantity > $productionPlan->plan_qty) {
            return response()->json(
                ['message' => 'Prodplan=' . $productionPlan->plan_qty . ', output=' .
                    $currentOutput->ok_qty . '+' . $request->quantity],
                406
            );
        }

        DB::table('keikaku_input3s')
            ->where("running_at",  $running_at)
            ->where('line_code', $request->line)
            ->where('wo_code', $request->job)
            ->where('process_code', $request->side)
            ->where('seq_data', $request->seq_data)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => date('Y-m-d H:i:s')]);

        $affectedRows = DB::table('keikaku_input3s')->insert([
            'created_at' => date('Y-m-d H:i:s'),
            'production_date' => $request->productionDate,
            'running_at' => $running_at,
            'wo_code' => $request->job,
            'line_code' =>  $request->line,
            'process_code' => $request->side,
            'ok_qty' => $request->quantity,
            'seq_data' => $request->seq_data,
            'created_by' => $request->user_id,
        ]);

        return $affectedRows ? [
            'message' => 'Recorded successfully',
        ] :
            ['message' => 'Failed, please try again'];
    }

    function keikakuSaveComment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'line' => 'required',
            'cell_code' => 'required',
            'productionDate' => 'required|date',
        ], [
            'line.required' => ':attribute is required',
            'cell_code.required' => ':attribute is required',
            'productionDate.required' => ':attribute is required',
            'productionDate.date' => ':attribute should be date',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 406);
        }

        try {
            DB::beginTransaction();
            DB::table('keikaku_comment_prodplans')
                ->where('line_code', $request->line)
                ->where('cell_code', $request->cell_code)
                ->where('production_date', $request->productionDate)
                ->update(['deleted_at' => date('Y-m-d H:i:s')]);

            $affectedRows = DB::table('keikaku_comment_prodplans')->insert([
                'created_at' => date('Y-m-d H:i:s'),
                'production_date' => $request->productionDate,
                'cell_code' => $request->cell_code,
                'line_code' =>  $request->line,
                'comment' =>  $request->comment ?? '',
                'created_by' => $request->user_id,
            ]);

            DB::commit();
            return $affectedRows ? ['message' => 'Recorded successfully'] : ['message' => 'Failed, please try again'];
        } catch (Exception $e) {
            $message = $e->getMessage();
            DB::rollBack();
            return response()->json(['message' => $message], 400);
        }
    }

    function getWO(Request $request)
    {

        $data = DB::table('XPPSN1')->where('PPSN1_PSNNO', $request->doc)
            ->leftJoin('XWO', 'PPSN1_WONO', '=', 'PDPP_WONO')
            ->groupBy('PPSN1_WONO', 'PDPP_WORQT', 'PPSN1_PROCD')
            ->get([
                DB::raw("RTRIM(UPPER(PPSN1_WONO)) WONO"),
                DB::raw("PDPP_WORQT"),
                DB::raw("0 CLS_QTY"),
                DB::raw("RTRIM(UPPER(PPSN1_PROCD)) PROCD"),
            ]);

        $WOUnique = $data->pluck('WONO')->toArray();
        $ProcdUnique = $data->pluck('PROCD')->toArray();
        $dataCLS = DB::table('WMS_CLS_JOB')->whereIn('CLS_JOBNO', $WOUnique)
            ->whereIn('CLS_PROCD', $ProcdUnique)
            ->groupBy('CLS_JOBNO', 'CLS_PROCD')
            ->get([DB::raw('UPPER(RTRIM(CLS_JOBNO)) CLS_JOBNO'), DB::raw("SUM(CLS_QTY) CLS_QTY"), 'CLS_PROCD']);

        foreach ($data as $d) {
            foreach ($dataCLS as $n) {
                if ($d->WONO == $n->CLS_JOBNO && $d->PROCD == $n->CLS_PROCD) {
                    $d->CLS_QTY = $n->CLS_QTY;
                    break;
                }
            }
        }
        unset($d);

        return ['data' => $data];
    }

    function getLineAccessByUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
        ], [
            'user_id.required' => ':attribute is required',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 406);
        }

        $data = DB::table('keikaku_access_rules')
            ->where('user_id', $request->user_id)
            ->whereNull('deleted_at')
            ->get(['line_code', 'sheet_access']);

        return ['data' => $data];
    }

    function savePermission(Request $request)
    {
        try {
            DB::beginTransaction();
            $data = $request->json()->all();

            // reset permission
            DB::table('keikaku_access_rules')
                ->where('user_id', $data['permittedUserId'])
                ->where('sheet_access', 'DTA')
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => date('Y-m-d H:i:s'),
                    'deleted_by' => $data['user_id']
                ]);

            $tobeSaved = [];
            foreach ($data['detail'] as $r) {
                $tobeSaved[] = [
                    'created_at' => date('Y-m-d H:i:s'),
                    'sheet_access' => 'DTA',
                    'line_code' => $r['line_code'],
                    'user_id' => $data['permittedUserId'],
                    'created_by' => $data['user_id'],
                ];
            }

            if ($tobeSaved) {
                // save new permission
                DB::table('keikaku_access_rules')->insert($tobeSaved);
            }

            // reset releaser rule

            DB::table('keikaku_access_rules')
                ->where('user_id', $data['permittedUserId'])
                ->where('sheet_access', 'RLS')
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => date('Y-m-d H:i:s'),
                    'deleted_by' => $data['user_id']
                ]);

            if ($data['as_releaser'] == '1') {
                DB::table('keikaku_access_rules')->insert([
                    'created_at' => date('Y-m-d H:i:s'),
                    'sheet_access' => 'RLS',
                    'user_id' => $data['permittedUserId'],
                    'created_by' => $data['user_id'],
                ]);
            }

            DB::commit();
            return ['message' => 'Saved successfully'];
        } catch (Exception $e) {
            $message = $e->getMessage();
            DB::rollBack();
            return response()->json(['message' => $message], 400);
        }
    }

    function setRelease(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'line_code' => 'required',
                'production_date' => 'required|date',
            ],
            [
                'line_code.required' => ':attribute is required',
                'production_date.required' => ':attribute is required',
                'production_date.date' => ':attribute should be date',
            ]
        );

        if ($validator->fails()) {
            return response()->json($validator->errors()->all(), 406);
        }


        $message = 'Successfully changing from Released to Unreleased';
        try {
            DB::beginTransaction();

            // reset permission
            DB::table('keikaku_releases')
                ->where('line_code', $request->line_code)
                ->where('production_date', $request->production_date)
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => date('Y-m-d H:i:s'),
                    'deleted_by' => $request->user_id
                ]);

            if ($request->release_flag == '1') {
                $message = "Released successfully 🚀";

                DB::table('keikaku_releases')->insert([
                    'created_at' => date('Y-m-d H:i:s'),
                    'production_date' => $request->production_date,
                    'line_code' => $request->line_code,
                    'release_flag' => 'Y',
                    'created_by' => $request->user_id
                ]);
            }
            DB::commit();

            return ['message' => $message];
        } catch (Exception $e) {
            $message = $e->getMessage();
            DB::rollBack();
            return response()->json(['message' => $message], 400);
        }
    }

    function saveWIP(Request $request)
    {
        $validator = Validator::make(
            $request->json()->all(),
            [
                'production_date' => 'required|date',
                'line_code' => 'required|string',
                'detail' => 'required|array',
                'detail.*.wo_code' => 'required',
            ],
            [
                'production_date.required' => ':attribute is required',
                'production_date.date' => ':attribute should be date',
                'detail.required' => ':attribute is required',
                'detail.array' => ':attribute should be array',
                'detail.*.wo_code.required' => ':attribute is required',
            ]
        );

        if ($validator->fails()) {
            return response()->json($validator->errors()->all(), 406);
        }

        $data = $request->json()->all();

        $tobeSaved = [];
        $message = '';

        try {
            // get code of model,type and specs
            $uniqueAssyCode = [];
            foreach ($data['detail'] as $r) {
                if (!in_array($r['item_code'], $uniqueAssyCode)) {
                    $uniqueAssyCode[] = $r['item_code'];
                }
            }

            $assyCodeO = DB::table('keikaku_data')->whereIn('item_code', $uniqueAssyCode)
                ->groupBy('item_code', 'type', 'specs', 'model_code')
                ->get(['item_code', 'type', 'specs', 'model_code']);

            foreach ($data['detail'] as $r) {
                $_wo = date('y') . '-' . $r['wo_code'] . '-' . trim($r['item_code']);

                $_type = $_spec = $_model_code = '';
                foreach ($assyCodeO as $d) {
                    if ($r['item_code'] == $d->item_code) {
                        $_type = $d->type;
                        $_spec = $d->specs;
                        $_model_code = $d->model_code;
                        break;
                    }
                }
                $tobeSaved[] = [
                    'created_at' => date('Y-m-d H:i:s'),
                    'created_by' => $data['user_id'],
                    'line_code' => $data['line_code'],
                    'production_date' => $data['production_date'],
                    'wo_code' => $r['wo_code'],
                    'wo_full_code' => $_wo,
                    'item_code' => $r['item_code'],
                    'type' => $_type,
                    'specs' => $_spec,
                    'model_code' => $_model_code,
                    'shift_code' => $data['shift'],
                    'running_at' => $data['shift'] == 'M' ? $data['production_date'] . ' 07:01:00' : $data['production_date'] . ' 21:01:00',
                    'ok_qty' => $r['output']
                ];
            }

            DB::beginTransaction();
            DB::table('w_i_p_outputs')
                ->where('production_date', $data['production_date'])
                ->where('shift_code', $data['shift'])
                ->where('line_code', $data['line_code'])
                ->update(
                    ['deleted_at' => date('Y-m-d H:i:s'), 'deleted_by' => $data['user_id']]
                );
            DB::table('w_i_p_outputs')->insert($tobeSaved);

            DB::commit();
            return ['message' => 'OK', 'data' => $tobeSaved];
        } catch (Exception $e) {
            $message = $e->getMessage();
            DB::rollBack();
            return response()->json(['message' => $message], 400);
        }

        return ['message' => 'debugging', 'data' => $data];
    }

    function getWIP(Request $request)
    {
        $data = DB::table('w_i_p_outputs')
            ->where('production_date', $request->production_date)
            ->where('shift_code', $request->shift)
            ->whereNull('deleted_at')
            ->get();

        $woUnique = $data->unique('wo_full_code')->pluck('wo_full_code')->toArray();
        $itemUnique = $data->unique('item_code')->pluck('item_code')->toArray();

        $historyData = $this->getWOHistoryData([
            'multiple' => true,
            'doc' => $woUnique,
            'cutoff_date' => $request->production_date
        ])->where('ok_qty', '>', 0)->orWhere('ok_qty_hw', '>', 0);

        $procesMaster = DB::table('process_masters')
            ->whereNull('deleted_at')
            ->whereIn('assy_code', $itemUnique)
            ->groupBy('assy_code', 'process_code', 'line_code')
            ->select(
                'assy_code',
                DB::raw('MAX(process_seq) process_seq'),
                DB::raw("case 
                    when process_code = 'SMT-A' THEN 'A'                    
                    when process_code = 'SMT-B' THEN 'B' 
                    ELSE 'A'
                    END process_code"),
                'line_code'
            );

        $historyDataJoin = DB::query()->fromSub($historyData, 'V2')
            ->leftJoinSub($procesMaster, 'V3', function ($join) {
                $join->on('V2.line_code', '=', 'V3.line_code')
                    ->on('V2.specs_side', '=', 'V3.process_code')
                    ->on('V2.item_code', '=', 'V3.assy_code')
                ;
            })
            ->get([
                'V2.*',
                'process_seq'
            ]);

        foreach ($data as &$r) {
            $_input1v = 0;
            $_outputv = 0;
            $_outputWIP = 0;
            $_processSeq = NULL;

            // determine current process seq per job
            foreach ($historyDataJoin as $h) {
                if ($r->wo_full_code == $h->wo_full_code && $r->line_code == $h->line_code) {
                    $_processSeq = $h->process_seq;
                    break;
                }
            }

            // get total current output
            foreach ($historyDataJoin as $h) {
                if ($r->wo_full_code == $h->wo_full_code && $h->process_seq == $_processSeq) {
                    if ($r->production_date == $h->production_date) {
                    } else {
                        $_outputv += $h->ok_qty;
                    }
                }
            }

            // get total input on previous seq
            foreach ($historyDataJoin as $h) {
                if ($r->wo_full_code == $h->wo_full_code && $h->process_seq == ($_processSeq - 1)) {
                    $_input1v += $h->ok_qty;
                }
            }


            $r->ostLotSize = ($_outputv + $_outputWIP) - $_input1v;
            $r->ost_qty_raw = "raw_" . $_outputv . '-' . $_input1v;
        }
        unset($r);

        return ['data' => $data];
    }

    function getItemDescription(Request $request)
    {
        $data = $request->json()->all();
        // get code of model,type and specs
        $uniqueAssyCode = [];
        foreach ($data['detail'] as $r) {
            if (!in_array($r['item_code'], $uniqueAssyCode)) {
                $uniqueAssyCode[] = $r['item_code'];
            }
        }

        $assyCodeO = DB::table('keikaku_data')->whereIn('item_code', $uniqueAssyCode)
            ->groupBy('item_code', 'type', 'specs', 'model_code')
            ->get(['item_code', 'type', 'specs', 'model_code']);

        return ['data' => $assyCodeO];
    }

    function getOutstandingLotsize(Request $request)
    {
        $data = $request->json()->all();

        $woUnique = $itemUnique = [];

        foreach ($data['detail'] as &$r) {
            $r['ost_qty'] = 0;
            $r['wo_full_code'] = date('y') . '-' . $r['wo_code'] . '-' . $r['item_code'];
            $_inputUnique = date('y') . '-' . $r['wo_code'] . '-' . $r['item_code'];

            if (!in_array($_inputUnique, $woUnique)) {
                $woUnique[] = $_inputUnique;
            }

            if (!in_array($r['item_code'], $itemUnique)) {
                $itemUnique[] = $r['item_code'];
            }
        }
        unset($r);

        $procesMaster = DB::table('process_masters')
            ->whereNull('deleted_at')
            ->whereIn('assy_code', $itemUnique)
            ->groupBy('assy_code', 'process_code', 'line_code')
            ->select(
                'assy_code',
                DB::raw('MAX(process_seq) process_seq'),
                DB::raw("case 
                    when process_code = 'SMT-A' THEN 'A'                    
                    when process_code = 'SMT-B' THEN 'B' 
                    ELSE 'A'
                    END process_code"),
                'line_code'
            );

        $historyData = $this->getWOHistoryData([
            'multiple' => true,
            'doc' => $woUnique,
            'cutoff_date' => $data['production_date']
        ])
            ->where('ok_qty', '>', 0)->orWhere('ok_qty_hw', '>', 0);

        $historyDataJoin = DB::query()->fromSub($historyData, 'V2')
            ->leftJoinSub($procesMaster, 'V3', function ($join) {
                $join->on('V2.line_code', '=', 'V3.line_code')
                    ->on('V2.specs_side', '=', 'V3.process_code')
                    ->on('V2.item_code', '=', 'V3.assy_code')
                ;
            })
            ->get([
                'V2.*',
                'process_seq'
            ]);

        foreach ($data['detail'] as &$r) {
            $_input1v = 0;
            $_outputv = 0;
            $_outputWIP = 0;
            $_processSeq = NULL;

            // determine current process seq per job
            foreach ($historyDataJoin as $h) {
                if ($r['wo_full_code'] == $h->wo_full_code && $data['line_code'] == $h->line_code) {
                    $_processSeq = $h->process_seq;
                    break;
                }
            }

            // get total current output
            foreach ($historyDataJoin as $h) {
                if ($r['wo_full_code'] == $h->wo_full_code && $h->process_seq == $_processSeq) {
                    if ($data['production_date'] == $h->production_date) {
                    } else {
                        $_outputv += $h->ok_qty;
                    }
                }
            }

            // get total input on previous seq
            foreach ($historyDataJoin as $h) {
                if ($r['wo_full_code'] == $h->wo_full_code && $h->process_seq == ($_processSeq - 1)) {
                    $_input1v += $h->ok_qty;
                }
            }

            $r['ost_qty'] = ($_outputv + $_outputWIP) - $_input1v;
            $r['ost_qty_raw'] = "raw_" . $_outputv . '-' . $_input1v;
        }

        return [
            'data' => $data['detail'],
            'tHistory' => $historyDataJoin
        ];
    }
}
