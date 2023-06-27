<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BusinessGroupController extends Controller
{
    function getAll(){
        return ['data' => DB::table("MBSG_TBL")->get()];
    }
}
