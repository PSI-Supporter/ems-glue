<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class RedmineController extends Controller
{
    protected $FORM_REQUEST_ICT = 4;
    protected $FORM_HISTORICAL_PROBLEM = 6;
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

        if ($request->traceker_id == $this->FORM_REQUEST_ICT) {
        }

        return ['data' => $data, 'custom' => $listOfCustomValue];
    }

    function getIssueData($where)
    {
        if ($where['statusId'] != '-') {
            if ($where['statusId'] === '1') {
                $data = DB::connection('sqlsrv_redmine')->table('issues')
                    ->leftJoin('users', 'assigned_to_id', '=', 'users.id')
                    ->leftJoin('custom_values', 'issues.id', '=', 'custom_values.customized_id')
                    ->where('custom_values.custom_field_id', 12) //closed date field
                    ->where('custom_values.value', '=', '') //closed date value
                    ->select('issues.*', 'firstname')
                    ->where('tracker_id', $where['tracker_id']);
                // die(json_encode($data));
            } else {
                // die('sini2');
                $data = DB::connection('sqlsrv_redmine')->table('issues')
                    ->leftJoin('users', 'assigned_to_id', '=', 'users.id')
                    ->leftJoin('custom_values', 'issues.id', '=', 'custom_values.customized_id')
                    ->where('custom_values.custom_field_id', 12) //closed date field
                    ->where('custom_values.value', '!=', '') //closed date value
                    ->select('issues.*', 'firstname')
                    ->where('tracker_id', $where['tracker_id']);
            }
        } else {
            $data = DB::connection('sqlsrv_redmine')->table('issues')
                ->leftJoin('users', 'assigned_to_id', '=', 'users.id')
                ->select('issues.*', 'firstname')
                ->where('tracker_id', $where['tracker_id']);
        }

        $data = $data->get()->toArray();


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

    function exportIssue(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tracker_id' => 'required'
        ]);

        $errorMessage = '';

        if ($validator->fails()) {
            $errorMessage = $validator->errors();
        }

        $where = ['tracker_id' => $request->tracker_id, 'statusId' => $request->statusId];
        $data = $this->getIssueData($where);

        $spreadSheet = new Spreadsheet();
        $sheet = $spreadSheet->getActiveSheet();

        // $sheet->setCellValue([1, 1], $errorMessage);

        if ($request->traceker_id == $this->FORM_REQUEST_ICT) {
            $sheet->setTitle('FORM REQUEST ICT');
        } else {
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

            $y = 4;
            foreach ($data['data'] as $r) {
                $sheet->setCellValue([1, $y], $r->Date_of_Event);
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
                $sheet->setCellValue([12, $y], $r->Closed_Date);
                $y++;
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
}
