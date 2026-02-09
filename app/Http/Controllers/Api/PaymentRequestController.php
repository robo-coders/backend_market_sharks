<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use App\Models\PaymentRequest;
use App\Models\User;


class PaymentRequestController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'plan' => ['required', 'in:basic,premium,enterprise'],
            'payment_method' => ['required', 'in:bank,btc,jazzcash,easypaisa'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'whatsapp' => ['required', 'string', 'max:30'],
            'proof' => ['required', 'image', 'max:2048'], // 2MB
        ]);

        $user = User::firstOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['first_name'].' '.$data['last_name'],
                'password' => Hash::make(Str::random(32)),
                'whatsapp_number' => $data['whatsapp'],
                'status' => 'pending',
            ]
        );

        $proofPath = $request->file('proof')->store('payment-proofs', 'public');

        PaymentRequest::create([
            'user_id' => $user->id,
            'plan' => $data['plan'],
            'payment_method' => $data['payment_method'],
            'proof_path' => $proofPath,
            'status' => 'pending',
        ]);

        Password::sendResetLink(['email' => $user->email]);

        return response()->json([
            'message' => 'Payment submitted. Please check your email.',
        ]);


        
    }
}
