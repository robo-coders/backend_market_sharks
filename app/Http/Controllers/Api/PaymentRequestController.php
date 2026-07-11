<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use App\Models\PaymentRequest;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class PaymentRequestController extends Controller
{
    public function store(Request $request)
    {
        $authUser = $request->user();

        if (!$authUser && $request->bearerToken()) {
            $token = PersonalAccessToken::findToken($request->bearerToken());

            if ($token && $token->tokenable instanceof User) {
                $authUser = $token->tokenable;
            }
        }

        if ($authUser) {
            $data = $request->validate([
                'plan' => 'required|in:basic,premium,enterprise',
                'payment_method' => 'required|in:bank,btc,jazzcash,easypaisa',
                'proof' => 'required|image|max:2048',
            ]);

            $existingPendingRequest = PaymentRequest::where('user_id', $authUser->id)
                ->where('status', 'pending')
                ->exists();

            if ($existingPendingRequest || $authUser->status === 'payment_review') {
                return response()->json([
                    'message' => 'You already have a payment request under review.',
                ], 422);
            }

            $hasActiveSubscription = $authUser->subscription
                && $authUser->subscription->status === 'active'
                && $authUser->subscription->expires_at
                && $authUser->subscription->expires_at->isFuture();

            if ($authUser->status === 'active' && $hasActiveSubscription) {
                return response()->json([
                    'message' => 'You already have an active plan. Only one plan is allowed at a time.',
                ], 422);
            }

            $proofPath = $request->file('proof')->store('payment-proofs', 'public');

            DB::transaction(function () use ($authUser, $data, $proofPath) {
                PaymentRequest::create([
                    'user_id' => $authUser->id,
                    'plan' => $data['plan'],
                    'payment_method' => $data['payment_method'],
                    'proof_path' => $proofPath,
                    'status' => 'pending',
                ]);

                $authUser->forceFill([
                    'status' => 'payment_review',
                ])->save();
            });

            return response()->json([
                'message' => 'Request submitted. Waiting for approval.',
                'user_id' => $authUser->id,
                'status' => 'pending',
            ]);
        }

        $data = $request->validate([
            'plan' => 'required|in:basic,premium,enterprise',
            'payment_method' => 'required|in:bank,btc,jazzcash,easypaisa',
            'first_name' => 'required_if:is_anonymous,false|nullable|string|max:100',
            'last_name' => 'required_if:is_anonymous,false|nullable|string|max:100',
            'nickname' => 'required_if:is_anonymous,true|nullable|string|max:100',
            'is_anonymous' => 'boolean',
            'email' => 'required|email',
            'whatsapp_number' => 'required|string|max:30',
            'proof' => 'required|image|max:2048',
        ]);

        $existingUser = User::where('email', $data['email'])->first();

        if ($existingUser) {
            $existingPendingRequest = PaymentRequest::where('user_id', $existingUser->id)
                ->where('status', 'pending')
                ->exists();

            if ($existingPendingRequest || $existingUser->status === 'payment_review') {
                return response()->json([
                    'message' => 'A payment request is already under review for this account.',
                ], 422);
            }

            $hasActiveSubscription = $existingUser->subscription
                && $existingUser->subscription->status === 'active'
                && $existingUser->subscription->expires_at
                && $existingUser->subscription->expires_at->isFuture();

            if ($existingUser->status === 'active' && $hasActiveSubscription) {
                return response()->json([
                    'message' => 'This account already has an active plan. Please log in instead.',
                ], 422);
            }
        }

        $isAnonymous = filter_var($data['is_anonymous'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $name = $isAnonymous
            ? ($data['nickname'] ?? 'Anonymous User')
            : trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));

        $user = User::firstOrCreate(
            ['email' => $data['email']],
            [
                'name' => $name,
                'password' => Hash::make(Str::random(32)),
                'whatsapp_number' => $data['whatsapp_number'],
                'status' => 'pending',
                'is_anonymous' => $isAnonymous,
                'nickname' => $isAnonymous ? ($data['nickname'] ?? null) : null,
            ]
        );

        $user->assignRole('user');

        $proofPath = $request->file('proof')->store('payment-proofs', 'public');

        DB::transaction(function () use ($user, $data, $proofPath) {
            PaymentRequest::create([
                'user_id' => $user->id,
                'plan' => $data['plan'],
                'payment_method' => $data['payment_method'],
                'proof_path' => $proofPath,
                'status' => 'pending',
            ]);

            $user->forceFill([
                'status' => 'payment_review',
            ])->save();
        });

        Password::sendResetLink(['email' => $user->email]);

        return response()->json([
            'message' => 'Request submitted. Waiting for approval.',
            'user_id' => $user->id,
            'status' => 'pending',
            'login_token' => $user->createToken('frontend')->plainTextToken,
        ]);
    }
}