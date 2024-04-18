<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Http;

class RedmineController extends Controller
{
    protected $FORM_REQUEST_ICT = 4;
    protected $FORM_HISTORICAL_PROBLEM = 6;
    protected $FORM_FOLLOW_UP = 7;
    function getProject()
    {
        $data = DB::connection('sqlsrv_redmine')->table('projects')->get();
        return ['data' => $data];
    }


    function getIssue(Request $request)
    {
        // Form ICT
        $data = DB::connection('sqlsrv_redmine')->table('issues')->where('tracker_id', $request->tracker_id)->get()->toArray();
        $listOfUniqueIssue = [];

        foreach ($data as $r) {
            $listOfUniqueIssue[] = $r->id;
        }

        $listOfCustomValue = empty($listOfUniqueIssue) ? [] : DB::connection('sqlsrv_redmine')->table('custom_values')
            ->leftJoin('custom_fields', 'custom_values.custom_field_id', '=', 'custom_fields.id')
            ->select('custom_values.*', 'name')
            ->where('customized_type', 'Issue')
            ->whereIn('customized_id', $listOfUniqueIssue)
            ->get()->toArray();

        foreach ($data as $r) {
            foreach ($listOfCustomValue as $c) {
                if ($r->id === $c->customized_id) {
                    $r->{str_replace(' ', '_', $c->name)} = $c->value;
                }
            }
        }

        return ['data' => $data, 'custom' => $listOfCustomValue];
    }

    function getIssueData($where)
    {

        $data = DB::connection('sqlsrv_redmine')->table('issues')
            ->leftJoin('users', 'assigned_to_id', '=', 'users.id')
            ->leftJoin('custom_values', 'issues.id', '=', 'custom_values.customized_id')
            ->select('issues.*', 'firstname')
            ->where('tracker_id', $where['tracker_id']);

        switch ($where['tracker_id']) {
            case $this->FORM_HISTORICAL_PROBLEM:
                $data = $data->whereIn('custom_values.custom_field_id', [12, 13]); // closed date and date of event
                $data = $data->where('custom_values.value', '>=', $where['dateFrom']); // date of event
                $data = $data->where('custom_values.value', '<=', $where['dateTo']); // date of event
                break;
            case $this->FORM_REQUEST_ICT:
                $data = $data->whereIn('custom_values.custom_field_id', [12, 4]); // closed date and date of event
                $data = $data->where('custom_values.value', '>=', $where['dateFrom']); // date of event
                $data = $data->where('custom_values.value', '<=', $where['dateTo']); // date of event
                break;
            case $this->FORM_FOLLOW_UP:
                $data = $data->whereIn('custom_values.custom_field_id', [12, 4]); // closed date and date of event
                $data = $data->where('custom_values.value', '>=', $where['dateFrom']); // date of event
                $data = $data->where('custom_values.value', '<=', $where['dateTo']); // date of event
                break;
        }
        logger('AFTER ADD WHERE CLAUSE ' . $data->toSql());
        logger('bindingnya ' . json_encode($data->getBindings()));

        $data = $data->get()->toArray();

        $listOfUniqueIssue = [];

        foreach ($data as $r) {
            $listOfUniqueIssue[] = $r->id;
        }

        // to header
        $data = DB::connection('sqlsrv_redmine')->table('issues')
            ->leftJoin('users', 'assigned_to_id', '=', 'users.id')
            ->leftJoin('issue_statuses', 'status_id', '=', 'issue_statuses.id')
            ->select('issues.*', 'firstname', DB::raw("issue_statuses.name as status_name"))
            ->where('tracker_id', $where['tracker_id'])
            ->whereIn('issues.id', $listOfUniqueIssue)
            ->orderBy('issues.id')
            ->get()->toArray();

        $listOfCustomValue = empty($listOfUniqueIssue) ? [] : DB::connection('sqlsrv_redmine')->table('custom_values')
            ->leftJoin('custom_fields', 'custom_values.custom_field_id', '=', 'custom_fields.id')
            ->select('custom_values.*', 'name')
            ->where('customized_type', 'Issue')
            ->whereIn('customized_id', $listOfUniqueIssue)
            ->get()->toArray();

        foreach ($data as $r) {
            foreach ($listOfCustomValue as $c) {
                if ($r->id === $c->customized_id) {
                    $r->{str_replace(' ', '_', $c->name)} = $c->value;
                }
            }
        }

        return ['data' => $data, 'custom' => $listOfCustomValue];
    }

    function exportIssue(Request $request)
    {
        $where = [
            'tracker_id' => $request->tracker_id,
            'statusId' => $request->statusId,
            'dateFrom' => $request->dateFrom,
            'dateTo' => $request->dateTo,
        ];
        $data = $this->getIssueData($where);

        $spreadSheet = new Spreadsheet();
        $sheet = $spreadSheet->getActiveSheet();

        switch ($request->tracker_id) {
            case $this->FORM_REQUEST_ICT:

                $sheet->setTitle('FORM REQUEST ICT');
                $sheet->setCellValue([1, 3], 'No');
                $sheet->setCellValue([2, 3], 'Request Date');
                $sheet->setCellValue([3, 3], 'Application Type');
                $sheet->setCellValue([4, 3], 'Subject');
                $sheet->setCellValue([5, 3], 'Description');
                $sheet->setCellValue([6, 3], 'Reason');
                $sheet->setCellValue([7, 3], 'User');
                $sheet->setCellValue([8, 3], 'Department');
                $sheet->setCellValue([9, 3], 'ICT Recommendation');
                $sheet->setCellValue([10, 3], 'Target Date');
                $sheet->setCellValue([11, 3], 'PIC');
                $sheet->setCellValue([12, 3], 'Status');
                $sheet->freezePane([1, 4]);

                $y = 4;
                foreach ($data['data'] as $r) {
                    $skip = false;
                    if ($where['statusId'] != '-') {
                        if ($where['statusId'] == 0) { // expected : show closed 
                            if ($r->Closed_Date == '') { // when data still open
                                $skip = true;
                            } else { // when data already closed
                                $skip = false;
                            }
                        } else { // expected : show open 
                            if ($r->Closed_Date == '') { // when data still open
                                $skip = false;
                            } else { // when data already closed
                                $skip = true;
                            }
                        }
                    }
                    if (!$skip) {
                        $DateOfRequest = strtotime($r->Date_of_Request);
                        $fDateOfRequest = date('d/m/Y', $DateOfRequest);

                        if ($r->Target_Date) {
                            $DateOfTarget = strtotime($r->Target_Date);
                            $fDateOfTarget = date('d/m/Y', $DateOfTarget);
                        } else {
                            $fDateOfTarget = NULL;
                        }

                        if ($r->Closed_Date) {
                            $DateOfClosing = strtotime($r->Closed_Date);
                            $fDateOfClosing = date('d/m/Y', $DateOfClosing);
                        } else {
                            $fDateOfClosing = '';
                        }

                        $sheet->setCellValue([1, $y], $r->id);
                        $sheet->setCellValue([2, $y], $fDateOfRequest);
                        $sheet->setCellValue([3, $y], $r->Application_Type);
                        $sheet->setCellValue([4, $y], $r->subject);
                        $sheet->setCellValue([5, $y], $r->description);
                        $sheet->setCellValue([6, $y], $r->Reason ?? '-');
                        $sheet->setCellValue([7, $y], $r->User);
                        $sheet->setCellValue([8, $y], $r->Department);
                        $sheet->setCellValue([9, $y], $r->ICT_Recommendation);
                        $sheet->setCellValue([10, $y], $fDateOfTarget);
                        $sheet->setCellValue([11, $y], $r->firstname);
                        $sheet->setCellValue([12, $y], $fDateOfClosing == '' ? 'Open' : 'Closed at ' . $fDateOfClosing);
                        $y++;
                    }
                }

                foreach (range('A', 'L') as $columnID) {
                    $sheet->getColumnDimension($columnID)
                        ->setAutoSize(true);
                }

                $sheet->getStyle('A3:L3')->getAlignment()->setHorizontal('center')->setVertical('center');
                $sheet->getStyle('A3:L3')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('EFEAE2');

                $sheet->getStyle('A3:L' . ($y - 1))->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $sheet->getStyle('A3:L3')->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOUBLE);
                break;
            case $this->FORM_HISTORICAL_PROBLEM:
                $sheet->setTitle('LIST');
                $sheet->setCellValue([1, 1], 'PT. SMT INDONESIA');
                $sheet->getStyle([4, 1])->getFont()->setBold(true);
                $sheet->setCellValue([4, 1], 'ICT  HISTORICAL  PROBLEM  RECORD');
                $sheet->setCellValue([12, 1], 'FPP-09-05, Rev.01');
                $sheet->mergeCells('D1:H1');

                $sheet->setCellValue([1, 3], 'Date');
                $sheet->setCellValue([2, 3], 'Unit Name');
                $sheet->setCellValue([3, 3], 'Device Type');
                $sheet->setCellValue([4, 3], 'Unit Serial Number');
                $sheet->setCellValue([5, 3], 'User');
                $sheet->setCellValue([6, 3], 'Department');
                $sheet->setCellValue([7, 3], 'Problem');
                $sheet->setCellValue([8, 3], 'Root Cause');
                $sheet->setCellValue([9, 3], 'Corrective Action');
                $sheet->setCellValue([10, 3], 'Preventive Action');
                $sheet->setCellValue([11, 3], 'PIC');
                $sheet->setCellValue([12, 3], 'Status');
                $sheet->freezePane([1, 4]);

                $y = 4;
                foreach ($data['data'] as $r) {
                    $skip = false;
                    if ($where['statusId'] != '-') {
                        if ($where['statusId'] == 0) { // expected : show closed 
                            if ($r->Closed_Date == '') { // when data still open
                                $skip = true;
                            } else { // when data already closed
                                $skip = false;
                            }
                        } else { // expected : show open 
                            if ($r->Closed_Date == '') { // when data still open
                                $skip = false;
                            } else { // when data already closed
                                $skip = true;
                            }
                        }
                    }
                    if (!$skip) {
                        $DateOfEvent = strtotime($r->Date_of_Event);
                        $fDateOfEvent = date('d/m/Y', $DateOfEvent);

                        if ($r->Closed_Date) {
                            $DateOfClosing = strtotime($r->Closed_Date);
                            $fDateOfClosing = date('d/m/Y', $DateOfClosing);
                        } else {
                            $fDateOfClosing = '';
                        }
                        $sheet->setCellValue([1, $y], $fDateOfEvent);
                        $sheet->setCellValue([2, $y], $r->Unit_Name);
                        $sheet->setCellValue([3, $y], $r->Device_Type);
                        $sheet->setCellValue([4, $y], $r->Unit_Serial_Number);
                        $sheet->setCellValue([5, $y], $r->User);
                        $sheet->setCellValue([6, $y], $r->Department);
                        $sheet->setCellValue([7, $y], $r->Problem);
                        $sheet->setCellValue([8, $y], $r->Root_Cause);
                        $sheet->setCellValue([9, $y], $r->Corrective_Action);
                        $sheet->setCellValue([10, $y], $r->Preventive_Action ?? '');
                        $sheet->setCellValue([11, $y], $r->firstname);
                        $sheet->setCellValue([12, $y], $fDateOfClosing == '' ? 'Open' : 'Closed at ' . $fDateOfClosing);
                        $y++;
                    }
                }

                foreach (range('A', 'L') as $columnID) {
                    $sheet->getColumnDimension($columnID)
                        ->setAutoSize(true);
                }

                $sheet->getStyle('D1:D1')->getAlignment()->setHorizontal('center');
                $sheet->getStyle('A3:L3')->getAlignment()->setHorizontal('center')->setVertical('center');
                $sheet->getStyle('A3:L3')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('C5D9F1');

                $sheet->getStyle('A3:L' . ($y - 1))->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $sheet->getStyle('A3:L3')->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOUBLE);

                $sheet->getRowDimension(3)->setRowHeight(25);
                break;
            case $this->FORM_FOLLOW_UP:
                $sheet->setTitle('FORM REQUEST ICT');
                $sheet->setCellValue([1, 3], 'No');
                $sheet->setCellValue([2, 3], 'Request Date');
                $sheet->setCellValue([3, 3], 'Target Date');
                $sheet->setCellValue([4, 3], 'Subject');
                $sheet->setCellValue([5, 3], 'User');
                $sheet->setCellValue([6, 3], 'Department');
                $sheet->setCellValue([7, 3], 'Completion Date');
                $sheet->setCellValue([8, 3], 'PIC');
                $sheet->setCellValue([9, 3], 'Status');
                $sheet->freezePane([1, 4]);

                $y = 4;
                foreach ($data['data'] as $r) {
                    $skip = false;
                    if ($where['statusId'] != '-') {
                        if ($where['statusId'] == 0) { // expected : show closed 
                            if ($r->Closed_Date == '') { // when data still open
                                $skip = true;
                            } else { // when data already closed
                                $skip = false;
                            }
                        } else { // expected : show open 
                            if ($r->Closed_Date == '') { // when data still open
                                $skip = false;
                            } else { // when data already closed
                                $skip = true;
                            }
                        }
                    }
                    if (!$skip) {
                        $DateOfRequest = strtotime($r->Date_of_Request);
                        $fDateOfRequest = date('d/m/Y', $DateOfRequest);

                        if ($r->Completion_Date) {
                            $DateOfCompletion = strtotime($r->Completion_Date);
                            $fDateOfCompletion = date('d/m/Y', $DateOfCompletion);
                        } else {
                            $fDateOfCompletion = '';
                        }

                        if ($r->Target_Date ?? '' != '') {
                            $DateOfTarget = strtotime($r->Target_Date);
                            $fDateOfTarget = date('d/m/Y', $DateOfTarget);
                        } else {
                            $fDateOfTarget = '';
                        }

                        $sheet->setCellValue([1, $y], $r->id);
                        $sheet->setCellValue([2, $y], $fDateOfRequest);
                        $sheet->setCellValue([3, $y], $fDateOfTarget);
                        $sheet->setCellValue([4, $y], $r->subject);
                        $sheet->setCellValue([5, $y], $r->User);
                        $sheet->setCellValue([6, $y], $r->Department);
                        $sheet->setCellValue([7, $y], $fDateOfCompletion);
                        $sheet->setCellValue([8, $y], $r->firstname);
                        $sheet->setCellValue([9, $y], $r->status_name);
                        $y++;
                    }
                }

                foreach (range('A', 'I') as $columnID) {
                    $sheet->getColumnDimension($columnID)
                        ->setAutoSize(true);
                }

                $sheet->getStyle('A3:I3')->getAlignment()->setHorizontal('center')->setVertical('center');
                $sheet->getStyle('A3:I3')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('EFEAE2');

                $sheet->getStyle('A3:I' . ($y - 1))->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $sheet->getStyle('A3:I3')->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOUBLE);
                break;
        }
        $fileName = 'fileNameSaja';

        if ($request->outputType === 'PDF') {
            $writer = new Mpdf($spreadSheet);
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline;filename="' . $fileName . '.pdf"');
            header('Cache-Control: max-age=0');
            $writer->setPaperSize(PageSetup::PAPERSIZE_A4);
            $writer->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        } else {
            $writer = new Xlsx($spreadSheet);
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="' . $fileName . '.xlsx"');
            header('Cache-Control: max-age=0');
        }
        $writer->save('php://output');
    }

    function formICT()
    {
        $trackers = DB::connection('sqlsrv_redmine')->table('trackers')->where('id', '>', 3)->get();
        return view('report.form_ict', ['trackers' => $trackers]);
    }

    function wrapGetIssue()
    {
        $res = Http::get('http://192.168.0.10:3000/issues.json');
        $resJsonO = json_decode($res->body());
        return $resJsonO;
    }

    function wrapPostIssue()
    {
        $data = [
            'issue' => [
                'project_id' => 3,
                'tracker_id' => 6,
                'subject' => 'coba saja',
                'priority_id' => 3,
                'custom_fields' => [
                    [
                        'id' => 5,
                        'name' => 'Unit Name',
                        'value' => '-'
                    ],
                    [
                        'id' => 6,
                        'name' => 'Device Type',
                        'value' => '-'
                    ],
                    [
                        'id' => 7,
                        'name' => 'Unit Serial Number',
                        'value' => '-'
                    ],
                    [
                        'id' => 8,
                        'name' => 'User',
                        'value' => 'usernya'
                    ],
                    [
                        'id' => 9,
                        'name' => 'Problem',
                        'value' => 'problemnya'
                    ],
                    [
                        'id' => 10,
                        'name' => 'Root Cause',
                        'value' => 'root causenya'
                    ],
                    [
                        'id' => 11,
                        'name' => 'Corrective Action',
                        'value' => '-'
                    ],
                    [
                        'id' => 13,
                        'name' => 'Date of Event',
                        'value' => '2024-03-21'
                    ],
                    [
                        'id' => 14,
                        'name' => 'Preventive Action',
                        'value' => '--'
                    ],
                ]
            ]
        ];
        $Person = ['ANA' => 5, 'RIKY' => 7, 'RACHMAN' => 8];
        $strJSON = '';

        $objJSON = json_decode($strJSON);

        $responseResume = [];

        $res = Http::get('http://192.168.0.10:3000/issues.json');
        $resList = json_decode($res->body());

        $uploadedSubject = [];

        // make a simplead uploaded subject
        foreach ($objJSON as $r) {
            foreach ($resList->issues as $s) {
                if ($s->subject === $r->Problem) {
                    if (!in_array($s->subject, $uploadedSubject)) {
                        $uploadedSubject[] = $s->subject;
                    }
                }
            }
        }

        $unUploaded = [];
        foreach ($objJSON as $r) {
            if (!in_array($r->Problem, $uploadedSubject)) {
                $unUploaded[] = $r->Problem;
                $data = [
                    'issue' => [
                        'project_id' => 3,
                        'tracker_id' => 6,
                        'subject' => $r->Problem,
                        'priority_id' => 3,
                        'assigned_to_id' => $Person[$r->PIC],
                        'custom_fields' => [
                            [
                                'id' => 5,
                                'name' => 'Unit Name',
                                'value' => $r->Unit_Name
                            ],
                            [
                                'id' => 6,
                                'name' => 'Device Type',
                                'value' => $r->Device_Type
                            ],
                            [
                                'id' => 7,
                                'name' => 'Unit Serial Number',
                                'value' => $r->Unit_Serial_Number
                            ],
                            [
                                'id' => 8,
                                'name' => 'User',
                                'value' => $r->User
                            ],
                            [
                                'id' => 9,
                                'name' => 'Problem',
                                'value' => $r->Problem
                            ],
                            [
                                'id' => 10,
                                'name' => 'Root Cause',
                                'value' => $r->Root_Cause
                            ],
                            [
                                'id' => 11,
                                'name' => 'Corrective Action',
                                'value' => $r->Corrective_Action
                            ],
                            [
                                'id' => 13,
                                'name' => 'Date of Event',
                                'value' => $r->Date
                            ],
                            [
                                'id' => 14,
                                'name' => 'Preventive Action',
                                'value' => $r->Preventive_Action
                            ],
                            [
                                'id' => 12,
                                'name' => 'Closed Date',
                                'value' => empty($r->CloseDate) ? NULL : $r->CloseDate,
                            ],
                        ]
                    ]
                ];

                $response = Http::withBasicAuth('1210034', '!1210034')
                    ->post('http://192.168.0.10:3000/issues.json', $data);
                $responseResume[] = [
                    'responseCode' => $response->status(),
                    'responseBody' => json_decode($response->body()),
                    'opt' => $response,
                    'data' => $data
                ];
            }
        }


        return $responseResume;
    }

    function wrapUpdateIssue()
    {
        $data = [
            'issue' => [
                'subject' => 'Tidak bisa Scan HT CIMS',
                'notes' => 'subject changed'
            ]
        ];

        $response = Http::withBasicAuth('1210034', '!1210034')
            ->put('http://192.168.0.10:3000/issues/9.json', $data);

        return [
            'response' => $response->status(),
            'opt' => $response
        ];
    }
}
