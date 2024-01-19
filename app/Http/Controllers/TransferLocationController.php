<?php

namespace App\Http\Controllers;

use App\Models\ITH;
use App\Models\transfer_indirect_rm_detail;
use App\Models\transfer_indirect_rm_header;
use Exception;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class TransferLocationController extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
    }

    function saveDraftTransferIndirectRM(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'issue_date' => 'required|date',
            'userid' => 'required',
            'part_code' => 'required|array',
            'part_code.*' => [
                Rule::exists('MITM_TBL', 'MITM_ITMCD')
            ],
            'sup_qty' => 'required|array',
            'sup_qty.*' => 'required|numeric',
        ], [
            'part_code.required' => 'Part code is required',
            'part_code.*.exists' => 'Part code (:input) is not registerd yet',
            'sup_qty.required' => 'Supply quantity is required',
            'sup_qty.*.numeric' => 'Supply quantity (:input) should be numeric',
            'userid.required' => 'user id is required',
            'issue_date.required' => 'date is required',
            'issue_date.date' => 'date (:input) is not valid',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors()->all(), 406);
        }

        $LastID = transfer_indirect_rm_header::whereYear('created_at', date('Y'))->max('doc_order');
        if (!$LastID) {
            $newID = 1;
            $newCode = 'INDT-' . date('y') . '-' . $newID;
        } else {
            $newID = $LastID + 1;
            $newCode = 'INDT-' . date('y') . '-' . $newID;
        }

        DB::beginTransaction();

        $createdHeader = transfer_indirect_rm_header::create([
            'doc_code' => $newCode,
            'doc_order' => $newID,
            'issue_date' => $request->issue_date,
            'location_from' => $request->location_from,
            'location_to' => $request->location_to,
            'created_by' => $request->userid,
        ]);

        if ($createdHeader) {
            try {
                $countDetail = count($request->part_code);
                $detailData = [];
                for ($i = 0; $i < $countDetail; $i++) {
                    $detailData[] = [
                        'created_at' => date('Y-m-d H:i:s'),
                        'created_by' => $request->userid,
                        'id_header' => $createdHeader->id,
                        'model' => $request->model[$i],
                        'assy_code' => $request->assy_code[$i],
                        'part_code' => $request->part_code[$i],
                        'part_name' => $request->part_name[$i],
                        'usage_qty' => $request->usage_qty[$i],
                        'req_qty' => $request->req_qty[$i],
                        'job' => $request->job[$i],
                        'sup_qty' => $request->sup_qty[$i],
                    ];
                }
                transfer_indirect_rm_detail::insert($detailData);
                DB::commit();
                return [
                    'message' => 'Saved successfully',
                    'new_document' => $newCode,
                    'new_document_id' => $createdHeader->id,
                    'data' => transfer_indirect_rm_detail::where('id_header', $createdHeader->id)->get()
                ];
            } catch (Exception $e) {
                DB::rollBack();
                return response()->json([[$e->getMessage() . ' ({' . $e->getLine() . '})']], 406);
            }
        } else {
            return response()->json([['Could not save header data, please contact your admin']], 406);
        }
    }

    function search(Request $request)
    {
        $columnMap = [
            'MITM_ITMCD',
            'MITM_ITMD1',
            'doc_code',
        ];

        $columnDisplayed = [
            'doc_code',
            'issue_date',
            'USERCREATE.MSTEMP_FNM',
            'location_from',
            'location_to',
            'transfer_indirect_rm_headers.id',
            'transfer_indirect_rm_headers.created_at',
            'transfer_indirect_rm_headers.updated_at',
        ];

        $data = transfer_indirect_rm_header::leftJoin('transfer_indirect_rm_details', 'transfer_indirect_rm_headers.id', '=', 'id_header')
            ->leftJoin('MITM_TBL', 'part_code', '=', 'MITM_ITMCD')
            ->leftJoin('MSTEMP_TBL as USERCREATE', 'transfer_indirect_rm_headers.created_by', '=', 'USERCREATE.MSTEMP_ID')
            ->leftJoin('MSTEMP_TBL as USERUPDATE', 'transfer_indirect_rm_headers.updated_by', '=', 'USERUPDATE.MSTEMP_ID')
            ->select(
                array_merge(
                    $columnDisplayed,
                    [
                        DB::raw("COUNT(*) TTLROWS"),
                        DB::raw('max(USERUPDATE.MSTEMP_FNM) WHO_UPDATE')
                    ]
                )
            )
            ->where($columnMap[$request->searchBy], 'like', '%' . $request->searchValue . '%')
            ->whereNull('transfer_indirect_rm_details.deleted_at')
            ->groupBy($columnDisplayed)
            ->get();

        return ['data' => $data];
    }

    function detailsByDocument(Request $request)
    {
        if (is_numeric($request->id)) {
            $data = transfer_indirect_rm_detail::where('id_header', $request->id)
                ->whereNull('deleted_at')
                ->get();
            return ['data' => $data];
        } else {
            return response()->json([['The document is not valid']], 406);
        }
    }

    function updateByDocument(Request $request)
    {
        $affectedRows = transfer_indirect_rm_header::where('id', $request->id)->update([
            'issue_date' => $request->issue_date,
            'location_from' => $request->location_from,
            'location_to' => $request->location_to,
            'updated_by' => $request->userid,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        if ($affectedRows) {
            return ['message' => 'Updated successfully'];
        } else {
            return response()->json([['Could not update']], 406);
        }
    }

    function toSpreadsheet(Request $request)
    {

        $data = transfer_indirect_rm_detail::leftJoin('transfer_indirect_rm_headers as H', 'id_header', '=', 'H.id')->where('id_header', $request->id)
            ->whereNull('transfer_indirect_rm_details.deleted_at')
            ->select('doc_code', 'part_code', DB::raw("SUM(sup_qty) AS total_qty"), 'location_from', 'location_to', 'issue_date', 'submitted_at')
            ->groupBy('part_code', 'doc_code', 'submitted_at', 'location_from', 'location_to', 'issue_date')
            ->get();
        $data = json_decode(json_encode($data), true);

        $dataSpreadsheet = [];
        if (empty($data)) {
            $dataSpreadsheet = [
                ['part_code' => '', 'total_qty' => '']
            ];
        } else {
            # check is already submitted
            $isSubmitted = false;
            foreach ($data as $d) {
                if ($d['submitted_at']) {
                    $isSubmitted = true;
                }
                break;
            }

            if (!$isSubmitted) {
                foreach ($data as $d) {
                    $dataSpreadsheet[] = [
                        'part_code' => $d['part_code'],
                        'qty' => $d['total_qty'],
                    ];
                    $tobeSaved[] = [
                        'ITH_ITMCD' => $d['part_code'],
                        'ITH_DATE' => $d['issue_date'],
                        'ITH_FORM' => 'OUT',
                        'ITH_DOC' => $d['doc_code'],
                        'ITH_QTY' => $d['total_qty'] * -1,
                        'ITH_WH' => $d['location_from'],
                        'ITH_LUPDT' => $d['issue_date'] . ' 21:21:21',
                        'ITH_USRID' => $request->userid
                    ];
                    $tobeSaved[] = [
                        'ITH_ITMCD' => $d['part_code'],
                        'ITH_DATE' => $d['issue_date'],
                        'ITH_FORM' => 'INC',
                        'ITH_DOC' => $d['doc_code'],
                        'ITH_QTY' => $d['total_qty'],
                        'ITH_WH' => $d['location_to'],
                        'ITH_LUPDT' => $d['issue_date'] . ' 21:21:21',
                        'ITH_USRID' => $request->userid
                    ];
                }

                DB::beginTransaction();

                $affectedRowsHead = transfer_indirect_rm_header::where('id', $request->id)->update(
                    [
                        'submitted_by' => $request->userid,
                        'submitted_at' => date('Y-m-d H:i:s')
                    ]
                );

                if ($affectedRowsHead) {
                    try {
                        ITH::insert($tobeSaved);
                        DB::commit();
                    } catch (Exception $e) {
                        DB::rollBack();
                        return response()->json([[$e->getMessage()]]);
                    }
                }
            } else {
                $dataSpreadsheet = [
                    ['part_code' => '', 'total_qty' => '']
                ];
            }
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('TRANSFER');
        $sheet->freezePane('A2');

        $sheet->fromArray(array_keys($dataSpreadsheet[0]), null, 'A1');
        $sheet->setCellValue([1, 2], 'Part Code');
        $sheet->setCellValue([2, 2], 'Qty');

        $sheet->fromArray($dataSpreadsheet, null, 'A2');

        foreach (range('A', 'B') as $r) {
            $sheet->getColumnDimension($r)->setAutoSize(true);
        }

        $stringjudul = "Transfer " . date('Y-m-d H:i:s');
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $filename = $stringjudul;
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
    }
}
