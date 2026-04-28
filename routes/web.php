<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SettingsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('admin.dashboard')
        : redirect()->route('login');
})->name('home');

/*
|--------------------------------------------------------------------------
| Super Admin Only
|--------------------------------------------------------------------------
*/
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

    });

/*
|--------------------------------------------------------------------------
| Admin & Super Admin
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:super_admin|admin'])
    ->prefix('admin')
    ->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');

        Route::get('/users', [UserController::class, 'index'])->name('admin.users.index');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('admin.users.show');
        Route::patch('/users/{user}/approve', [UserController::class, 'approve'])->name('admin.users.approve');
        Route::patch('/users/{user}/reject', [UserController::class, 'reject'])->name('admin.users.reject');
        Route::patch('/users/{user}/block', [UserController::class, 'block'])->name('admin.users.block');
        Route::patch('/users/{user}/unblock', [UserController::class, 'unblock'])->name('admin.users.unblock');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');

        Route::get('/settings', [SettingsController::class, 'edit'])->name('admin.settings.edit');
        Route::post('/settings', [SettingsController::class, 'update'])->name('admin.settings.update');

    });

/*
|--------------------------------------------------------------------------
| Profile
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';