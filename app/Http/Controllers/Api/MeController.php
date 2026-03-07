<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        if ($user->hasAnyRole(['admin', 'super_admin'])) {
            abort(403, 'Admins should use the backend.');
        }

        $user->load([
            'subscription',
            'paymentRequest',
        ]);

        $plan = null;
        $expiresAt = null;
        $paymentRequestStatus = null;

        if ($user->status === 'active' && $user->subscription) {
            $plan = $user->subscription->plan;
            $expiresAt = optional($user->subscription->expires_at)?->toDateString();
        } else {
            $paymentRequestStatus = optional($user->paymentRequest)->status;
            // optional: show intended plan while pending/rejected
            $plan = optional($user->paymentRequest)->plan;
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->display_name,
                'email' => $user->email,
                'status' => $user->status,
                'subscription_status' => $user->subscription_status,
            ],
            'plan' => $plan,
            'expires_at' => $expiresAt,
            'payment_request_status' => $paymentRequestStatus,
        ]);
    }
}
