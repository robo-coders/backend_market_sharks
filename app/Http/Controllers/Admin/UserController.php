<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use App\Mail\PaymentApproved;
use App\Mail\PaymentRejected;
use App\Mail\UserBlocked;
use App\Mail\UserUnblocked;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function index()
    {
        $isSuperAdmin = auth()->user()->hasRole('super_admin');

        $users = User::role('user')
            ->with(['subscription', 'paymentRequest'])
            ->get();

        $users = $users->map(function ($user) {
            $user->computed_status = $this->computeStatus($user);

            return $user;
        });

        $counts = [
            'all'            => $users->count(),
            'pending'        => $users->where('computed_status', 'pending')->count(),
            'payment_review' => $users->where('computed_status', 'payment_review')->count(),
            'blocked'        => $users->where('computed_status', 'blocked')->count(),
            'rejected'       => $users->where('computed_status', 'rejected')->count(),
            'active'         => $users->where('computed_status', 'active')->count(),
            'expiring'       => $users->where('computed_status', 'expiring')->count(),
            'expired'        => $users->where('computed_status', 'expired')->count(),
        ];

        $users = $users->map(function ($user) use ($isSuperAdmin) {
            $data = $user->toArray();

            $data['status'] = $user->computed_status;

            if (!$isSuperAdmin) {
                $data['email'] = '—';
                $data['whatsapp'] = '—';
            }

            return $data;
        });

        return Inertia::render('Admin/Users/Index', [
            'users'   => $users,
            'counts'  => $counts,
            'success' => session('success'),
        ]);
    }

    public function show(User $user)
    {
        $user->load(['subscription', 'paymentRequest']);
        $user->computed_status = $this->computeStatus($user);

        return Inertia::render('Admin/Users/Show', [
            'user' => array_merge($user->toArray(), [
                'status' => $user->computed_status,
            ]),
        ]);
    }

    public function approve(User $user)
    {
        DB::transaction(function () use ($user) {
            $user->load('paymentRequest');

            $paymentRequest = $user->paymentRequest;

            if (!$paymentRequest) {
                abort(422, 'No payment request found for this user.');
            }

            $paymentRequest->update([
                'status'      => 'approved',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            $user->update(['status' => 'active']);

            $user->subscription()->updateOrCreate(
                [],
                [
                    'plan'       => $paymentRequest->plan,
                    'starts_at'  => now(),
                    'expires_at' => now()->addDays(match ($paymentRequest->plan) {
                        'premium' => 90,
                        default   => 30,
                    }),
                    'status'     => 'active',
                ]
            );
        });

        try {
            Mail::to($user->email)->send(new PaymentApproved($user));
        } catch (\Throwable $e) {
            Log::warning('Approval email failed to send', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        return back()->with('success', 'User approved and subscription created.');
    }

    public function reject(User $user)
    {
        DB::transaction(function () use ($user) {
            $user->load('paymentRequest');

            $paymentRequest = $user->paymentRequest;

            if (!$paymentRequest) {
                abort(422, 'No payment request found for this user.');
            }

            $paymentRequest->update([
                'status'      => 'rejected',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            $user->update(['status' => 'rejected']);
        });

        try {
            Mail::to($user->email)->send(new PaymentRejected($user));
        } catch (\Throwable $e) {
            Log::warning('Rejection email failed to send', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        return back()->with('success', 'Payment request rejected.');
    }

    public function block(User $user)
    {
        DB::transaction(function () use ($user) {
            $user->load('subscription');

            $user->update(['status' => 'blocked']);

            if ($user->subscription) {
                $user->subscription->update(['status' => 'canceled']);
            }
        });

        try {
            Mail::to($user->email)->send(new UserBlocked($user));
        } catch (\Throwable $e) {
            Log::warning('User-blocked email failed to send', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        return back();
    }

    public function unblock(User $user)
    {
        DB::transaction(function () use ($user) {
            $user->load('subscription');

            $hasActiveSubscription =
                $user->subscription
                && $user->subscription->expires_at
                && $user->subscription->expires_at->isFuture();

            $user->update([
                'status' => $hasActiveSubscription ? 'active' : 'pending',
            ]);

            if ($user->subscription) {
                $user->subscription->update([
                    'status' => $hasActiveSubscription ? 'active' : 'expired',
                ]);
            }
        });

        try {
            Mail::to($user->email)->send(new UserUnblocked($user));
        } catch (\Throwable $e) {
            Log::warning('User-unblocked email failed to send', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        return back();
    }

    public function destroy(User $user)
    {
        if ($user->subscription) {
            $user->subscription->delete();
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }

    private function computeStatus(User $user): string
    {
        if ($user->status === 'pending') return 'pending';
        if ($user->status === 'payment_review') return 'payment_review';
        if ($user->status === 'blocked') return 'blocked';
        if ($user->status === 'rejected') return 'rejected';
        if ($user->status === 'expired') return 'expired';

        if ($user->status === 'active') {
            if (!$user->subscription?->expires_at) {
                return 'pending';
            }

            if ($user->subscription->expires_at->isPast()) {
                return 'expired';
            }

            if ($user->subscription->expires_at->isFuture() && $user->subscription->isExpiringSoon()) {
                return 'expiring';
            }

            return 'active';
        }

        return 'pending';
    }
}