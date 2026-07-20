<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\GoldPriceController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TradingSignalController;
use App\Http\Controllers\Admin\MarketStructureController;
use App\Http\Controllers\Admin\MarketTrendController;
use App\Http\Controllers\Team\NotificationController;
use App\Http\Controllers\Team\SettingsController as TeamSettingsController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Team\DashboardController as TeamDashboardController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatAdminController;
use App\Models\MarketStructure;
use App\Models\MarketTrend;
use App\Models\TradeLog;
use App\Models\TradingSignal;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    $user = auth()->user();

    if ($user->hasRole('team')) {
        return redirect()->route('team.dashboard');
    }

    if ($user->hasRole('super_admin') || $user->hasRole('admin')) {
        return redirect()->route('admin.dashboard');
    }

    return redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified', 'role:super_admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/admins', [AdminController::class, 'index'])->name('admin.admins.index');
        Route::get('/admins/create', [AdminController::class, 'create'])->name('admin.admins.create');
        Route::post('/admins', [AdminController::class, 'store'])->name('admin.admins.store');
        Route::get('/admins/{admin}/edit', [AdminController::class, 'edit'])->name('admin.admins.edit');
        Route::put('/admins/{admin}', [AdminController::class, 'update'])->name('admin.admins.update');
        Route::delete('/admins/{admin}', [AdminController::class, 'destroy'])->name('admin.admins.destroy');
        Route::patch('/admins/{admin}/block', [AdminController::class, 'block'])->name('admin.admins.block');
        Route::patch('/admins/{admin}/unblock', [AdminController::class, 'unblock'])->name('admin.admins.unblock');

        Route::get('/teams', [TeamController::class, 'index'])->name('admin.teams.index');
        Route::get('/teams/create', [TeamController::class, 'create'])->name('admin.teams.create');
        Route::post('/teams', [TeamController::class, 'store'])->name('admin.teams.store');
        Route::get('/teams/{team}/edit', [TeamController::class, 'edit'])->name('admin.teams.edit');
        Route::put('/teams/{team}', [TeamController::class, 'update'])->name('admin.teams.update');
        Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->name('admin.teams.destroy');
        Route::patch('/teams/{team}/block', [TeamController::class, 'block'])->name('admin.teams.block');
        Route::patch('/teams/{team}/unblock', [TeamController::class, 'unblock'])->name('admin.teams.unblock');

        Route::get('/signals', function () {
            $signal = TradingSignal::where('status', 'open')->latest('id')->first()
                ?? TradingSignal::latest('id')->first();

            $structure = MarketStructure::latest('id')->first();
            $trend = MarketTrend::latest('id')->first();

            $logs = TradeLog::where('closed_at', '>=', now()->subDays(30))
                ->latest('closed_at')
                ->latest('id')
                ->get()
                ->map(function ($log) {
                    $resultLabel = match ($log->result) {
                        'profit' => 'Profit',
                        'loss' => 'Loss',
                        'breakeven' => 'Breakeven',
                        default => (float) $log->profit_loss >= 0 ? 'Profit' : 'Loss',
                    };

                    return [
                        'result' => $resultLabel,
                        'signal_type' => ucfirst($log->signal_type),
                        'hit_level' => match ($log->close_reason) {
                            'tp' => 'Take Profit',
                            'sl' => 'Stop Loss',
                            'manual' => 'Manual Close',
                            'cancelled' => 'Cancelled',
                            default => 'Closed',
                        },
                        'price' => (string) ($log->close_price ?? '0.00'),
                        'time' => $log->closed_at?->format('d M Y, h:i A') ?? '',
                    ];
                })
                ->values();

            return Inertia::render('Admin/Signals', [
                'signal' => $signal,
                'structure' => $structure,
                'trend' => $trend,
                'logs' => $logs,
                'signalStoreUrl' => route('admin.signals.store'),
                'signalUpdateUrl' => ($signal && $signal->status === 'open')
                    ? route('admin.signals.update', $signal->id)
                    : '',
                'structureUpdateUrl' => route('admin.market-structure.update'),
                'trendUpdateUrl' => route('admin.market-trend.update'),
                'livePriceEndpoint' => route('admin.gold-price'),
                'logsExportUrl' => '',
                'closeSignalUrl' => ($signal && $signal->status === 'open')
                    ? route('admin.signals.close', $signal->id)
                    : '',
            ]);
        })->name('admin.signals.index');

        Route::post('/signals', [TradingSignalController::class, 'store'])->name('admin.signals.store');
        Route::put('/signals/{id}', [TradingSignalController::class, 'update'])->name('admin.signals.update');
        Route::post('/signals/{id}/close', [TradingSignalController::class, 'close'])->name('admin.signals.close');
        Route::put('/market-structure', [MarketStructureController::class, 'update'])->name('admin.market-structure.update');
        Route::put('/market-trend', [MarketTrendController::class, 'update'])->name('admin.market-trend.update');

        // Chat settings - super admin only. Access revocation + feature flags.
        Route::get('/chat-settings', fn () => Inertia::render('Admin/ChatSettings'))->name('admin.chat-settings');
    });

Route::middleware(['auth', 'verified', 'role:super_admin|admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');

        Route::get('/users', [UserController::class, 'index'])->name('admin.users.index');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('admin.users.show');
        Route::patch('/users/{user}/approve', [UserController::class, 'approve'])->name('admin.users.approve');
        Route::patch('/users/{user}/reject', [UserController::class, 'reject'])->name('admin.users.reject');
        Route::patch('/users/{user}/block', [UserController::class, 'block'])->name('admin.users.block');
        Route::patch('/users/{user}/unblock', [UserController::class, 'unblock'])->name('admin.users.unblock');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');

        Route::get('/settings', [SettingsController::class, 'edit'])->name('admin.settings.edit');
        Route::post('/settings', [SettingsController::class, 'update'])->name('admin.settings.update');

        Route::get('/gold-price', [GoldPriceController::class, 'show'])->name('admin.gold-price');
    });

Route::middleware(['auth', 'verified', 'role:team'])
    ->prefix('team')
    ->group(function () {
        Route::get('/dashboard', [TeamDashboardController::class, 'index'])->name('team.dashboard');

        Route::get('/gold-price', [GoldPriceController::class, 'show'])->name('team.gold-price');

        Route::get('/settings', [TeamSettingsController::class, 'edit'])->name('team.settings.edit');
        Route::patch('/settings', [TeamSettingsController::class, 'update'])->name('team.settings.update');

        Route::get('/notifications', [NotificationController::class, 'index'])->name('team.notifications.index');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('team.notifications.read-all');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('team.notifications.read');
    });

Route::middleware(['auth', 'verified'])
    ->prefix('chat')
    ->group(function () {
        Route::get('/messages', [ChatController::class, 'index'])->name('chat.messages.index');
        Route::post('/messages', [ChatController::class, 'store'])
            ->middleware('throttle:30,1')->name('chat.messages.store');

        Route::put('/messages/{message}', [ChatController::class, 'update'])
            ->middleware('chat.feature:edit')->name('chat.messages.update');
        Route::delete('/messages/{message}', [ChatController::class, 'destroy'])
            ->middleware('chat.feature:delete')->name('chat.messages.destroy');

        Route::get('/unread', [ChatController::class, 'unreadCount'])->name('chat.unread');
        Route::post('/read', [ChatController::class, 'markRead'])->name('chat.read');
    });

Route::middleware(['auth', 'verified', 'role:super_admin'])
    ->prefix('chat/admin')
    ->group(function () {
        Route::get('/', [ChatAdminController::class, 'index'])->name('chat.admin.index');
        Route::post('/users/{user}/access', [ChatAdminController::class, 'setUserAccess'])->name('chat.admin.users.access');
        Route::post('/roles/access', [ChatAdminController::class, 'setRoleAccess'])->name('chat.admin.roles.access');
        Route::post('/features', [ChatAdminController::class, 'updateFeature'])->name('chat.admin.features');
    });

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';