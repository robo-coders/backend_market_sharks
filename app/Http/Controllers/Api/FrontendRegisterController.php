<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class FrontendRegisterController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'is_anonymous'   => 'required|boolean',
            'first_name'     => 'required_if:is_anonymous,false',
            'last_name'      => 'required_if:is_anonymous,false',
            'nickname'       => 'required_if:is_anonymous,true',
            'email'          => 'required|email|unique:users',
            'whatsapp_number'=> 'required|string|max:20',
            'password'       => 'required|min:6',
        ]);

        $name = $request->is_anonymous
            ? $request->nickname ?? 'Anonymous User'
            : $request->first_name . ' ' . $request->last_name;

        $user = User::create([
            'name'            => $name,
            'email'           => $request->email,
            'whatsapp_number' => $request->whatsapp_number,
            'password'        => Hash::make($request->password),
            'status'          => 'pending',
            'is_anonymous'    => $request->is_anonymous,
            'nickname'        => $request->is_anonymous ? $request->nickname : null,
        ]);

        $user->assignRole('user');

        return response()->json([
            'message' => 'Registration successful.',
            'user_id' => $user->id,
            'token'   => $user->createToken('spa-token')->plainTextToken,
        ]);
    }
}