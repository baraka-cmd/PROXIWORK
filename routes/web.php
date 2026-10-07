<?php

declare(strict_types=1);

use App\Http\Controllers\Web\AdminAuthController;
use App\Http\Controllers\Web\LoginController;
use App\Http\Controllers\Web\RegisterController;
use App\Http\Controllers\Web\RbacDashboardController;
use App\Http\Controllers\Web\Admin\RoleController;
use App\Http\Controllers\Web\Admin\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('register.store');

    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});

Route::get('/reset-password/{token}', function (Request $request, string $token) {
    return response()->json([
        'success' => true,
        'message' => 'Password reset link received.',
        'data' => [
            'token' => $token,
            'email' => $request->query('email'),
        ],
    ]);
})->middleware('guest')->name('password.reset');

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('login', [AdminAuthController::class, 'create'])->name('login');
        Route::post('login', [AdminAuthController::class, 'store'])
            ->middleware('throttle:admin-login')
            ->name('login.store');
    });

    Route::middleware('auth')->group(function (): void {
        Route::post('logout', [AdminAuthController::class, 'destroy'])->name('logout');
    });

    Route::middleware(['auth', 'permission:admin.users.view'])->group(function (): void {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
    });

    Route::middleware('auth')->group(function (): void {
        Route::post('users/{user}/suspend', [UserController::class, 'suspend'])
            ->middleware('permission:admin.users.suspend')
            ->name('users.suspend');
        Route::post('users/{user}/activate', [UserController::class, 'activate'])
            ->middleware('permission:admin.users.activate')
            ->name('users.activate');
    });

    Route::middleware(['auth', 'permission:rbac.view'])->group(function (): void {
        Route::get('rbac', [RbacDashboardController::class, 'index'])->name('rbac.dashboard');
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    });

    Route::middleware(['auth', 'permission:rbac.manage'])->group(function (): void {
        Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });

    Route::middleware(['auth', 'permission:rbac.view'])->group(function (): void {
        Route::get('roles/{role}', [RoleController::class, 'show'])->name('roles.show');
    });
});
