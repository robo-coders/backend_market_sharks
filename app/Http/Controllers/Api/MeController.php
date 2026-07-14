<?php

namespace App\Http\Controllers\Api;

use App\Models\Setting;
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

        $subscription = $user->subscription;

        $hasValidSubscription =
            $subscription
            && $subscription->expires_at
            && $subscription->expires_at->isFuture();

        $isExpiredSubscription =
            $subscription
            && $subscription->expires_at
            && $subscription->expires_at->isPast();

        $effectiveStatus = $user->status;

        if ($user->status === 'active') {
            if (!$subscription || !$subscription->expires_at) {
                $effectiveStatus = 'pending';
            } elseif ($isExpiredSubscription) {
                $effectiveStatus = 'expired';
            }
        }

        $plan = null;
        $expiresAt = null;

        if ($hasValidSubscription) {
            $plan = $subscription->plan;
            $expiresAt = $subscription->expires_at->toDateString();
        } else {
            $plan = optional($user->paymentRequest)->plan;
        }

        return response()->json([
            'user' => [
                'id'                  => $user->id,
                'name'                => $user->display_name,
                'email'               => $user->email,
                'status'              => $effectiveStatus,
                'subscription_status' => $hasValidSubscription ? 'active' : ($isExpiredSubscription ? 'expired' : null),
            ],
            'plan'                   => $plan,
            'expires_at'             => $expiresAt,
            'payment_request_status' => optional($user->paymentRequest)->status,
            'whatsapp_link'          => $hasValidSubscription ? Setting::get('whatsapp_group_link') : null,
        ]);
    }
}