<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Api\MeController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

//Admin Pages
Route::middleware(['auth', 'verified', 'role:super_admin'])
    ->prefix('admin')
    ->group( function () {

    Route::get('/admins', [AdminController::class, 'index'])
        ->name('admin.admins.index');
    Route::get('/admins/create', [AdminController::class, 'create'])
        ->name('admin.admins.create');
    Route::post('/admins', [AdminController::class, 'store'])
        ->name('admin.admins.store');
    Route::get('/admins/{admin}/edit', [AdminController::class, 'edit'])
        ->name('admin.admins.edit');
    Route::put('/admins/{admin}', [AdminController::class, 'update'])
        ->name('admin.admins.update');
    Route::delete('/admins/{admin}', [AdminController::class, 'destroy'])
        ->name('admin.admins.destroy');
    Route::patch('/admins/{admin}/block', [AdminController::class, 'block'])
        ->name('admin.admins.block');
    Route::patch('/admins/{admin}/unblock', [AdminController::class, 'unblock'])
        ->name('admin.admins.unblock');

    });
Route::middleware(['auth', 'verified', 'role:super_admin|admin'])
    ->prefix('admin')
    ->group(function () {

        Route::get('/dashboard', function () {
            return Inertia::render('Admin/Dashboard');
        })->name('admin.dashboard');

        Route::get('/users', [UserController::class, 'index'])
            ->name('admin.users.index');

        Route::patch('/users/{user}/approve', [UserController::class, 'approve'])
            ->name('admin.users.approve');

        Route::get('/users/{user}', [UserController::class, 'show'])
            ->name('admin.users.show');
            
        Route::delete('/users/{user}', [UserController::class, 'destroy'])
            ->name('admin.users.destroy');


    });

/*
|--------------------------------------------------------------------------
| Public home page
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

/*
|--------------------------------------------------------------------------
| Public site
|--------------------------------------------------------------------------
*/


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
