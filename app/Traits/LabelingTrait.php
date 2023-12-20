<?php

namespace App\Traits;

use App\Models\RawMaterialLabel;

trait LabelingTrait
{

    function generateLabelId($data = [])
    {
        $LabelDate = date('Y-m-d');
        $LabelTime = date('H:i:s');
        $LabelCreator = '1'; # from SMT
        $LabelMachineName = substr('00' . $data['machineName'], -2);

        $Labels = RawMaterialLabel::where('created_at', $LabelDate . ' ' . $LabelTime)
            ->count();

        $NewID = substr(str_replace('-', '', $LabelDate), -6) . $LabelCreator . $LabelMachineName . str_replace(':', '', $LabelTime) . ++$Labels;

        RawMaterialLabel::insert([
            'code' => $NewID,
            'doc_code' => $data['documentCode'],
            'item_code' => $data['itemCode'],
            'quantity' => $data['qty'],
            'lot_code' => $data['lotNumber'],
            'created_by' => $data['userID'],
            'created_at' => $LabelDate . ' ' . $LabelTime,
            'composed' => $data['composed'] ?? NULL,
        ]);

        return ['data' => $NewID, 'created_at' => $LabelDate . ' ' . $LabelTime];
    }
}
