<?php

namespace App\Http\Controllers;

use App\Models\ITH;
use App\Models\sync_xtrf_h;
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
            'submitted_at',
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
        DB::beginTransaction();
        $affectedRows = transfer_indirect_rm_header::where('id', $request->id)->update([
            'issue_date' => $request->issue_date,
            'location_from' => $request->location_from,
            'location_to' => $request->location_to,
            'updated_by' => $request->userid,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        if ($affectedRows) {
            try {
                if ($request->part_code) {
                    $countDetail = count($request->part_code);
                    $affectedRowsDetails = 0;
                    for ($i = 0; $i < $countDetail; $i++) {
                        if (strlen($request->rows_id[$i]) > 0) {
                            $affectedRowsDetails += transfer_indirect_rm_detail::where('id', $request->rows_id[$i])->update([
                                'model' => $request->model[$i],
                                'assy_code' => $request->assy_code[$i],
                                'part_code' => $request->part_code[$i],
                                'part_name' => $request->part_name[$i],
                                'usage_qty' => $request->usage_qty[$i],
                                'req_qty' => $request->req_qty[$i],
                                'job' => $request->job[$i],
                                'sup_qty' => $request->sup_qty[$i],
                                'updated_by' => $request->userid
                            ]);
                        } else {
                            transfer_indirect_rm_detail::insert([[
                                'created_at' => date('Y-m-d H:i:s'),
                                'created_by' => $request->userid,
                                'id_header' => $request->id,
                                'model' => $request->model[$i],
                                'assy_code' => $request->assy_code[$i],
                                'part_code' => $request->part_code[$i],
                                'part_name' => $request->part_name[$i],
                                'usage_qty' => $request->usage_qty[$i],
                                'req_qty' => $request->req_qty[$i],
                                'job' => $request->job[$i],
                                'sup_qty' => $request->sup_qty[$i],
                            ]]);
                        }
                    }
                }
                DB::commit();
                return [
                    'message' => 'Updated successfully',
                    'data' => transfer_indirect_rm_detail::where('id_header', $request->id)
                        ->whereNull('deleted_at')->get()
                ];
            } catch (Exception $e) {
                DB::rollBack();
                return response()->json([[$e->getMessage() . ' ({' . $e->getLine() . '})']], 406);
            }
        } else {
            return response()->json([['Could not update']], 406);
        }
    }

    function toSpreadsheet(Request $request)
    {

        $data = transfer_indirect_rm_detail::leftJoin('transfer_indirect_rm_headers as H', 'id_header', '=', 'H.id')
            ->leftJoin('MITM_TBL', 'part_code', '=', 'MITM_ITMCD')
            ->where('id_header', $request->id)
            ->where('MITM_ITMD1', 'NOT LIKE', '%AIRCAP%')
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
                    if (!in_array($d['location_from'], ['AIWH1'])) {
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
                    } catch (Exception $e) {
                        DB::rollBack();
                        return response()->json([[$e->getMessage()]]);
                    }
                }
                DB::commit();
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

    function deleteByItem(Request $request)
    {
        DB::beginTransaction();
        try {
            if ($request->rows_id) {
                $countDetail = count($request->rows_id);
                $affectedRowsDetails = 0;
                for ($i = 0; $i < $countDetail; $i++) {
                    if (strlen($request->rows_id[$i]) > 0) {
                        $affectedRowsDetails += transfer_indirect_rm_detail::where('id', $request->rows_id[$i])->update([
                            'deleted_at' => date('Y-m-d H:i:s'),
                            'deleted_by' => $request->userid,
                        ]);
                    }
                }
            }
            DB::commit();
            return ['message' => 'Deleted successfully'];
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([[$e->getMessage() . ' ({' . $e->getLine() . '})']], 406);
        }
    }

    function xGetDocument(Request $request)
    {
        $data = DB::table('XSTKTRND1')
            ->leftJoin('XSTKTRND2', 'STKTRND1_RQRLSNO', '=', 'STKTRND2_RQRLSNO')
            ->where('STKTRND1_LOCCDFR', $request->locationFrom)
            ->where('STKTRND1_LOCCDTO', $request->locationTo)
            ->whereNotNull('STKTRND1_RQAPPROVEDT')
            ->select(DB::raw('RTRIM(STKTRND1_DOCNO) DOCNO'), DB::raw('CONVERT(DATE,STKTRND1_ISUDT) ISUDT'), DB::raw("COUNT(*) TTLROWS"))
            ->groupBy('STKTRND1_DOCNO', 'STKTRND1_ISUDT')
            ->orderBy('STKTRND1_ISUDT')
            ->get();
        return ['data' => $data];
    }

    function xGetDocumentDetail(Request $request)
    {
        $data = DB::table('XSTKTRND1')
            ->leftJoin('XSTKTRND2', 'STKTRND1_RQRLSNO', '=', 'STKTRND2_RQRLSNO')
            ->leftJoin('XMITM_V', 'STKTRND2_ITMCD', '=', 'MITM_ITMCD')
            ->where('STKTRND1_DOCNO', base64_decode($request->id))
            ->whereIn('STKTRND1_DOCCD', ['TRF', 'ADJ'])
            ->select(
                DB::raw('CONVERT(DATE,STKTRND1_ISUDT) ISUDT'),
                DB::raw('RTRIM(STKTRND1_LOCCDFR) LOCCDFR'),
                DB::raw('RTRIM(STKTRND1_LOCCDTO) LOCCDTO'),
                DB::raw('RTRIM(MITM_ITMCD) ITMCD'),
                DB::raw('RTRIM(MITM_ITMD1) ITMD1'),
                DB::raw('RTRIM(MITM_SPTNO) SPTNO'),
                'STKTRND2_TRNQT',
                DB::raw('RTRIM(MITM_STKUOM) STKUOM'),
            )
            ->orderBy('STKTRND2_LINE')
            ->get();
        return ['data' => $data];
    }

    function saveXdocument(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'document' => [
                    Rule::unique('sync_xtrf_hs', 'xdocument_number')
                ],
                'userId' => 'required'
            ],
            [
                'userId.required' => 'User ID is required',
                'document.unique' => 'The document is already added'
            ]
        );

        if ($validator->fails()) {
            return response()->json($validator->errors(), 406);
        }

        $ttlRows = DB::table("XSTKTRND1")
            ->where('STKTRND1_DOCNO', $request->document)
            ->whereIn('STKTRND1_DOCCD', ['TRF', 'ADJ'])
            ->count();

        if ($ttlRows === 0) {
            return response()->json(['Document is not found'], 400);
        }

        try {
            sync_xtrf_h::create(
                [
                    'xdocument_number' => $request->document,
                    'created_by' => $request->userId
                ]
            );
            return ['message' => 'OK'];
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    function autoConformXdocument()
    {
        // get list un-synchronized document
        $NotSyncYet = sync_xtrf_h::select('xdocument_number')->whereNull('synchronized_at')->get();
        $NotSyncYetList = [];
        foreach ($NotSyncYet as $r) {
            $NotSyncYetList[] = $r->xdocument_number;
        }

        // get list approved based on the un-synchronized document
        $XSigneddocument = DB::table('XSTKTRND1')
            ->whereIn('STKTRND1_DOCNO', $NotSyncYetList)
            ->whereNotNull('STKTRND1_RQAPPROVEDT')
            ->select(
                DB::raw("RTRIM(STKTRND1_DOCNO) STKTRND1_DOCNO"),
                DB::raw("RTRIM(STKTRND1_LOCCDFR) STKTRND1_LOCCDFR"),
                DB::raw("RTRIM(STKTRND1_LOCCDTO) STKTRND1_LOCCDTO"),
            )
            ->get();

        $SavedDocs = 0;
        foreach ($XSigneddocument as $r) {

            // get list detail of the approved document
            $XSigneddocumentDetail = DB::table('XITRN_TBL')
                ->where('ITRN_DOCNO', trim($r->STKTRND1_DOCNO))
                ->select(
                    DB::raw("RTRIM(ITRN_LOCCD) ITRN_LOCCD"),
                    DB::raw("RTRIM(ITRN_ITMCD) ITRN_ITMCD"),
                    DB::raw("RTRIM(ITRN_USRID) ITRN_USRID"),
                    DB::raw("CONVERT(DATE,ITRN_ISUDT) ITRN_ISUDT"),
                    "IOQT",
                    "ITRN_LINE"
                )
                ->get();

            // make a list tobe synchronized
            $tobeSaved = [];
            foreach ($XSigneddocumentDetail as $_r) {
                $tobeSaved[] = [
                    "ITH_ITMCD" => $_r->ITRN_ITMCD,
                    "ITH_DATE" => $_r->ITRN_ISUDT,
                    "ITH_FORM" => $_r->IOQT > 0 ? 'TRFIN-RM' : 'TRFOUT-RM',
                    "ITH_DOC" => trim($r->STKTRND1_DOCNO),
                    "ITH_QTY" => $_r->IOQT,
                    "ITH_WH" => $_r->ITRN_LOCCD,
                    "ITH_REMARK" => $_r->ITRN_LINE,
                    "ITH_LUPDT" => $_r->ITRN_ISUDT . ' 07:07:07',
                    "ITH_USRID" => $_r->ITRN_USRID,
                ];
            }

            if (!empty($tobeSaved)) {
                $synchronizedRows = DB::table("ITH_TBL")->where("ITH_DOC", trim($r->STKTRND1_DOCNO))->count();

                // check wheter the approved document already synchronized
                if ($synchronizedRows === 0) {
                    try {
                        DB::table("ITH_TBL")->insert($tobeSaved);
                        $SavedDocs++;
                        logger('SYCXDOC success message :' . trim($r->STKTRND1_DOCNO));
                    } catch (Exception $e) {
                        logger('SYCXDOC exception message : ' . $e->getMessage());
                    }
                }

                // update un-synchronized flag document to be synchronized
                sync_xtrf_h::where('xdocument_number', trim($r->STKTRND1_DOCNO))
                    ->whereNull('synchronized_at')
                    ->update(['synchronized_at' => date('Y-m-d H:i:s')]);
            }
        }

        $outputMessage = "Synchronized documents : " . $SavedDocs;

        logger($outputMessage);
        return ['message' => $outputMessage];
    }

    function manualConformXdocument(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'document_number' => 'required',
        ], [
            'document_number.required' => 'Document Number is required',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors()->all(), 406);
        }

        // get list detail of the approved document
        $XSigneddocumentDetail = DB::table('XITRN_TBL')
            ->where('ITRN_DOCNO', trim($request->document_number))
            ->select(
                DB::raw("RTRIM(ITRN_LOCCD) ITRN_LOCCD"),
                DB::raw("RTRIM(ITRN_ITMCD) ITRN_ITMCD"),
                DB::raw("RTRIM(ITRN_USRID) ITRN_USRID"),
                DB::raw("CONVERT(DATE,ITRN_ISUDT) ITRN_ISUDT"),
                "IOQT",
                "ITRN_LINE"
            )
            ->get();

        // make a list tobe synchronized
        $tobeSaved = [];
        foreach ($XSigneddocumentDetail as $_r) {
            $tobeSaved[] = [
                "ITH_ITMCD" => $_r->ITRN_ITMCD,
                "ITH_DATE" => $_r->ITRN_ISUDT,
                "ITH_FORM" => $_r->IOQT > 0 ? 'TRFIN-RM' : 'TRFOUT-RM',
                "ITH_DOC" => trim($request->document_number),
                "ITH_QTY" => $_r->IOQT,
                "ITH_WH" => $_r->ITRN_LOCCD,
                "ITH_REMARK" => $_r->ITRN_LINE,
                "ITH_LUPDT" => $_r->ITRN_ISUDT . ' 07:07:07',
                "ITH_USRID" => $_r->ITRN_USRID,
            ];
        }

        if (!empty($tobeSaved)) {
            $synchronizedRows = DB::table("ITH_TBL")->where("ITH_DOC", trim($request->document_number))->count();

            // check wheter the approved document already synchronized
            if ($synchronizedRows === 0) {
                try {
                    DB::table("ITH_TBL")->insert($tobeSaved);
                    logger('SYCXDOC Manually success message :' . trim($request->document_number));
                    return [
                        'message' => 'SYCXDOC Manually success message',
                        'data' => [
                            'document_number' => trim($request->document_number),
                            'execution_datetime' => date('Y-m-d H:i:s')
                        ]
                    ];
                } catch (Exception $e) {
                    logger('SYCXDOC Manually exception message : ' . $e->getMessage());
                    return [
                        'message' => $e->getMessage(),
                        'data' => [
                            'document_number' => trim($request->document_number),
                            'execution_datetime' => date('Y-m-d H:i:s')
                        ]
                    ];
                }
            }
        } else {
            return [
                'message' => 'Not Found',
                'data' => [
                    'document_number' => trim($request->document_number),
                    'execution_datetime' => date('Y-m-d H:i:s')
                ]
            ];
        }
    }
}
