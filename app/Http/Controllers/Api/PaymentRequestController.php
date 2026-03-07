<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use App\Models\PaymentRequest;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;

class PaymentRequestController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'plan' => 'required|in:basic,premium,enterprise',
            'payment_method' => 'required|in:bank,btc,jazzcash,easypaisa',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'nickname' => 'nullable|string|max:100',
            'is_anonymous' => 'boolean',
            'email' => 'required|email',
            'whatsapp_number' => 'required|string|max:30',
            'proof_path' => 'nullable|required|image|max:2048',
        ]);

        $name = ($data['is_anonymous'] ?? false)
            ? ($data['nickname'] ?? 'Anonymous User')
            : $data['first_name'].' '.$data['last_name'];

        $user = User::firstOrCreate(
            ['email' => $data['email']],
            [
                'name' => $name,
                'password' => Hash::make(Str::random(32)),
                'whatsapp_number' => $data['whatsapp_number'],
                'status' => 'pending',
                'is_anonymous' => $data['is_anonymous'] ?? false,
                'nickname' => $data['is_anonymous'] ? $data['nickname'] : null,
            ]
        );

        $user->assignRole('user');

        // $proofPath = $request->file('proof')->store('payment-proofs', 'public');

        $proofPath = $request->hasFile('proof') 
            ? $request->file('proof')->store('payment-proofs', 'public')
            : 'test-proof.jpg'; // Default for testing

        PaymentRequest::create([
            'user_id' => $user->id,
            'plan' => $data['plan'],
            'payment_method' => $data['payment_method'],
            'proof_path' => $proofPath,
            'status' => 'pending',
        ]);

        Password::sendResetLink(['email' => $user->email]);

        return response()->json([
            'message' => 'Request submitted. Waiting for approval.',
            'user_id' => $user->id,
            'status' => 'pending',
            'login_token' => $user->createToken('frontend')->plainTextToken, // Auto-login
        ]);
    }
}
