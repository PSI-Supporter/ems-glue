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
        return ['cd' =>  $data ? 1 : 0,  'data' => $data];
    }
}
