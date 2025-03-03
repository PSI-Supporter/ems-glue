<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WorkingTimeController extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
    }

    function saveCalculation(Request $request)
    {
        $validator = Validator::make(
            $request->json()->all(),
            [
                'line_code' => 'required',
                'user_id' => 'required',
                'detail' => 'required|array',
            ],
            [
                'line_code.required' => ':attribute is required',
                'user_id.required' => ':attribute is required',
                'detail.required' => ':attribute is required',
                'detail.array' => ':attribute should be array',
            ]
        );

        if ($validator->fails()) {
            return response()->json($validator->errors()->all(), 406);
        }
        $data = $request->json()->all();

        $tobeSaved = [];

        try {

            DB::beginTransaction();
            foreach ($data['detail'] as $r) {
                $tobeSaved[] = [
                    'calculation_at' => $r['calculation_at'],
                    'line_code' => $data['line_code'],
                    'worktype1' => (float) $r['worktype1'],
                    'worktype2' => (float) $r['worktype2'],
                    'worktype3' => (float) $r['worktype3'],
                    'worktype4' => (float) $r['worktype4'],
                    'worktype5' => (float) $r['worktype5'],
                    'worktype6' => (float) $r['worktype6'],
                    'name' => $data['name'],
                    'category' => $r['category'],
                    'status' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'created_by' => $data['user_id'],
                ];
            }

            if (
                DB::table('keikaku_calc_templates')
                ->where('line_code', $data['line_code'])
                ->where('name', $data['name'])
                ->count() > 0
            ) {
                DB::table('keikaku_calc_templates')
                    ->where('line_code', $data['line_code'])
                    ->where('name', $data['name'])
                    ->update(
                        ['deleted_at' => date('Y-m-d H:i:s'), 'deleted_by' => $data['user_id']]
                    );
            }

            DB::table('keikaku_calc_templates')->insert($tobeSaved);

            DB::commit();
            return ['message' => 'Saved successfully', 'data' => $tobeSaved];
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    function getName()
    {
        $data = DB::table('keikaku_calc_templates')
            ->select('name', DB::raw("MAX(status) status"))
            ->groupBy('name')
            ->get();
        return ['data' => $data];
    }

    function getTemplate(Request $request)
    {
        $data = DB::table('keikaku_calc_templates')
            ->where('name', $request->name)
            ->whereNull('deleted_at')
            ->get();
        return ['data' => $data];
    }
}
