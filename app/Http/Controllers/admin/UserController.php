<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use App\Mail\PaymentApproved;
use App\Mail\PaymentRejected;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index()
    {
        $isSuperAdmin = auth()->user()->hasRole('super_admin');

        $users = User::role('user')
            ->with(['subscription', 'paymentRequest'])
            ->get();

        $counts = [
            'all'            => $users->count(),
            'pending'        => $users->where('status', 'pending')->count(),
            'payment_review' => $users->where('status', 'payment_review')->count(),
            'blocked'        => $users->where('status', 'blocked')->count(),
            'rejected'       => $users->where('status', 'rejected')->count(),
            'active'         => $users->filter(
                                    fn($u) => $u->status === 'active'
                                        && !$u->subscription?->isExpired()
                                        && !$u->subscription?->isExpiringSoon()
                                )->count(),
            'expiring'       => $users->filter(
                                    fn($u) => $u->status === 'active'
                                        && $u->subscription?->isExpiringSoon()
                                )->count(),
            'expired'        => $users->filter(
                                    fn($u) => $u->status === 'active'
                                        && $u->subscription?->isExpired()
                                )->count(),
        ];

        $users = $users->map(function ($user) use ($isSuperAdmin) {
            $data = $user->toArray();
            if (!$isSuperAdmin) {
                $data['email']    = '—';
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

        return Inertia::render('Admin/Users/Show', [
            'user' => $user,
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
                    'expires_at' => now()->addMonth(),
                    'status'     => 'active',
                ]
            );
        });

        Mail::to($user->email)->send(new PaymentApproved($user));
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

        Mail::to($user->email)->send(new PaymentRejected($user));
        return back()->with('success', 'Payment request rejected.');
    }

    public function block(User $user)
    {
        DB::transaction(function () use ($user) {
            $user->update(['status' => 'blocked']);

            if ($user->subscription) {
                $user->subscription->update(['status' => 'canceled']);
            }
        });

        return back();
    }

    public function unblock(User $user)
    {
        DB::transaction(function () use ($user) {
            $hasActiveSubscription = $user->subscription
                && $user->subscription->status !== 'expired'
                && $user->subscription->status !== 'canceled';

            $user->update([
                'status' => $hasActiveSubscription ? 'active' : 'pending',
            ]);

            if ($hasActiveSubscription) {
                $user->subscription->update(['status' => 'active']);
            }
        });

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
}