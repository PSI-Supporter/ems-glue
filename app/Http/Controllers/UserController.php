<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserLoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function login(Request $request)
    {
        $data = [
            'nick_name' => $request->input('username'),
            'password' => $request->input('password'),
            'active' => '1',
        ];

        if (Auth::attempt($data)) {
            // $request->session()->regenerate();
            $user = User::where('nick_name', $request->input('username'))->first();
            $user->token = $user->createToken($request->input('username') . 'bebas')->plainTextToken;
            return $user;
        } else {
            throw new HttpResponseException(response([
                'errors' => [
                    'message' => [
                        'username or password wrong'
                    ]
                ]
            ], 401));
        }
    }

    function getName(Request $request)
    {
        $data = DB::table('VNPSI_USERS')->where('ID', $request->id)->first(['user_nicename']);
        if ($data) {
            if (str_contains($data->user_nicename, ' ')) {
                $userName = explode(' ', $data->user_nicename);
                $data->user_nicename = $userName[0];
            }
        }
        return ['cd' =>  $data ? 1 : 0,  'data' => $data];
    }

    function getByGroup(Request $request)
    {
        $groups = array_merge([''], $request->groupId);
        $data = DB::table('MSTEMP_TBL')->whereIn('MSTEMP_GRP', $groups)->get([DB::raw("CONCAT(RTRIM(MSTEMP_FNM), ' ', RTRIM(MSTEMP_FNM)) FULL_NAME")]);
        return ['data' => $data];
    }

    function getActiveUserGroup()
    {
        $data = DB::table('MSTEMP_TBL')->leftJoin('MSTGRP_TBL', 'MSTEMP_TBL.MSTEMP_GRP', '=', 'MSTGRP_TBL.MSTGRP_ID')
            ->where('MSTEMP_TBL.MSTEMP_STS', 1)
            ->select('MSTGRP_TBL.MSTGRP_ID', 'MSTGRP_TBL.MSTGRP_NM')
            ->distinct()
            ->orderBy('MSTGRP_NM')
            ->get();
        return ['data' => $data];
    }
}
