<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CountryController extends Controller
{
    function getAll(Request $request)
    {
        return ['data' => DB::table("MMADE_TBL")->get()];
    }
}
