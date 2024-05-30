<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

            // .total ouput current input
            $_TotalCurrentInput = 0;
            foreach ($data['output'] as $r) {
                $_TotalCurrentInput += ($r['outputOK'] + $r['outputNG']);
            }

            foreach ($ProcessMaster as $r) {
                if ($r->MBO2_SEQNO == 1 && $_TotalCurrentInput > $data['wo_size']) {
                    return response()->json(['message' => 'output of production > lot size'], 400);
                }

                if ($r->MBO2_PROCD === $data['process_code']) { // iterasi dari atas ke bawah
                    $_seq = $r->MBO2_SEQNO;
                    if ($_seq != 1) {
                        foreach ($ProcessMaster as $_s) { // iterasi dari atas ke bawah (lagi) dengan batasan
                            if ($_s->MBO2_SEQNO < $_seq && $_s->total_qty < $_TotalCurrentInput) {
                                return response()->json(['message' => 'output of process #' . $_seq . ' > #' . $_s->MBO2_SEQNO], 400);
                            }
                        }
                    }
                    break;
                }
            }
            // end of validation           

            DB::beginTransaction();
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
                            'input_qty' => $data['input_qty'],
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
                        'input_qty' => $data['input_qty'],
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
                    ->count();

                if ($countRows) {
                    DB::table("production_times")
                        ->where('production_date', $data['production_date'])
                        ->where('shift_code', $r['shift_code'])
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
                        'working_hours' => $r['working_hours'],
                    ];
                }
            }

            if (!empty($tobeSaved)) {
                DB::table("production_times")->insert($tobeSaved);
            }

            DB::commit();

            return [
                'message' => 'Saved successfully', 'data' => $tobeSaved
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
            ->select('line_code', 'process_code', DB::raw("SUM(ok_qty) ok_qty"), DB::raw("SUM(ng_qty) ng_qty"))
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

        $dataInputPCB = DB::table('production_output')
            ->select('input_qty')
            ->where('wo_code', $request->wo_code)
            ->where('process_code', $request->process_code)
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
            ->orderBy('downtime_code');
        return [
            'data' => $downTime->get()
        ];
    }
}
