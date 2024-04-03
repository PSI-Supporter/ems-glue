<?php

namespace App\Traits;

trait BusinessSelectionTrait
{

    function getPartLocationRoutes($businessGroupCode, $transactionContext): array
    {
        switch ($businessGroupCode) {
            case 'PSI1PPZIEP':
                $wh_1 = 'ARWH1';
                $wh_2 = 'PLANT1';
                break;
            case 'PSI2PPZADI':
                $wh_1 = 'ARWH2';
                $wh_2 = 'PLANT2';
                break;
            case 'PSI2PPZINS':
                $wh_1 = 'NRWH2';
                $wh_2 = 'PLANT_NA';
                break;
            case 'PSI2PPZOMC':
                $wh_1 = 'NRWH2';
                $wh_2 = 'PLANT_NA';
                break;
            case 'PSI2PPZOMI':
                $wh_1 = 'ARWH2';
                $wh_2 = 'PLANT2';
                break;
            case 'PSI2PPZSSI':
                $wh_1 = 'NRWH2';
                $wh_2 = 'PLANT_NA';
                break;
            case 'PSI2PPZSTY':
                $wh_1 = 'ARWH2';
                $wh_2 = 'PLANT2';
                break;
            case 'PSI2PPZTDI':
                $wh_1 = 'ARWH2';
                $wh_2 = 'PLANT2';
                break;
        }
        $data = [];
        switch ($transactionContext) {
            case 'ISSUE-PART':
                $data = ['LOC_FROM' => $wh_1, 'LOC_TO' => $wh_2];
                break;
            case 'CANCEL-ISSUE-PART':
                $data = ['LOC_FROM' => $wh_2, 'LOC_TO' => $wh_1];
                break;
            case 'EDIT-ISSUE-PART':
                $data = ['LOC_FROM' => $wh_2, 'LOC_TO' => $wh_1];
                break;
        }
        return $data;
    }
}
