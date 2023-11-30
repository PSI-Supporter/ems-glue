<?php

namespace App\Traits;

use App\Models\Label;

trait LabelingTrait
{

    function generateLabelId($data = [])
    {
        $LabelDate = date('Y-m-d');
        $LabelTime = date('H:i:s');
        $LabelCreator = '1'; # from SMT
        $LabelMachineName = substr('00' . $data['machineName'], -2);

        $Labels = Label::where('created_at', $LabelDate . ' ' . $LabelTime)
            ->where("SER_DOCTYPE", '0')
            ->count();

        $NewID = substr(str_replace('-', '', $LabelDate), -6) . $LabelCreator . $LabelMachineName . str_replace(':', '', $LabelTime) . ++$Labels;

        Label::insert([
            'SER_ID' => $NewID,
            'SER_DOC' => $data['documentCode'],
            'SER_ITMID' => $data['itemCode'],
            'SER_QTY' => $data['qty'],
            'SER_QTYLOT' => $data['qty'],
            'SER_LOTNO' => $data['lotNumber'],
            'SER_USRID' => $data['userID'],
            'created_at' => $LabelDate . ' ' . $LabelTime,
        ]);

        return ['data' => $NewID, 'created_at' => $LabelDate . ' ' . $LabelTime];
    }
}
