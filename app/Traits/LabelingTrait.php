<?php

namespace App\Traits;

use App\Models\RawMaterialLabel;

trait LabelingTrait
{

    function generateLabelId($data = [])
    {
        sleep(0.9);
        $LabelDate = date('Y-m-d');
        $LabelTime = date('H:i:s');
        $LabelCreator = '1'; # from SMT
        $LabelMachineName = substr('00' . $data['machineName'], -2);

        $Labels = RawMaterialLabel::where('created_at', $LabelDate . ' ' . $LabelTime)
            ->count();

        $orderChar = [
            1 => '1',
            2 => '2',
            3 => '3',
            4 => '4',
            5 => '5',
            6 => '6',
            7 => '7',
            8 => '8',
            9 => '9',
            10 => 'A',
            11 => 'B',
            12 => 'C',
            13 => 'D',
            14 => 'E',
            15 => 'F',
            16 => 'G',
            17 => 'H',
            18 => 'I',
            19 => 'J',
            20 => 'K',
            21 => 'L',
            22 => 'M',
            23 => 'N',
            24 => 'O',
            25 => 'P',
            26 => 'Q',
            27 => 'R',
            28 => 'S',
            29 => 'T',
            30 => 'U',
            31 => 'V',
            32 => 'W',
            33 => 'X',
            34 => 'Y',
            35 => 'Z',
        ];

        $NewID = substr(str_replace('-', '', $LabelDate), -6) . $LabelCreator . $LabelMachineName . str_replace(':', '', $LabelTime) . $orderChar[++$Labels];

        RawMaterialLabel::insert([
            'code' => $NewID,
            'doc_code' => $data['documentCode'],
            'item_code' => $data['itemCode'],
            'quantity' => $data['qty'],
            'lot_code' => $data['lotNumber'],
            'created_by' => $data['userID'],
            'created_at' => $LabelDate . ' ' . $LabelTime,
            'composed' => $data['composed'] ?? NULL,
            'parent_code' => $data['parent_code'] ?? NULL,
        ]);


        return ['data' => $NewID, 'created_at' => $LabelDate . ' ' . $LabelTime];
    }
}
