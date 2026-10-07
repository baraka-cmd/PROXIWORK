<?php

declare(strict_types=1);

use App\Http\Controllers\Web\AdminAuthController;
use App\Http\Controllers\Web\LoginController;
use App\Http\Controllers\Web\RegisterController;
use App\Http\Controllers\Web\RbacDashboardController;
use App\Http\Controllers\Web\Professional\ProfileController;
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

    Route::middleware(['auth', 'permission:rbac.view'])->group(function (): void {
        Route::get('rbac', [RbacDashboardController::class, 'index'])->name('rbac.dashboard');
        Route::post('logout', [AdminAuthController::class, 'destroy'])->name('logout');
    });
});


Route::prefix('professional')->name('professional.')->middleware(['auth', 'active.account', 'role:professional'])->group(function (): void {
    Route::get('profile', [ProfileController::class, 'show'])->name('profile');
    Route::get('services', [ProfileController::class, 'services'])->name('services.index');
    Route::get('services/create', [ProfileController::class, 'createService'])->name('services.create');
    Route::post('services', [ProfileController::class, 'storeService'])->name('services.store');
    Route::get('services/{service}/edit', [ProfileController::class, 'editService'])->name('services.edit');
    Route::patch('services/{service}', [ProfileController::class, 'updateService'])->name('services.update');
    Route::post('services/{service}/publish', [ProfileController::class, 'publishService'])->name('services.publish');
    Route::post('services/{service}/unpublish', [ProfileController::class, 'unpublishService'])->name('services.unpublish');
    Route::delete('services/{service}', [ProfileController::class, 'archiveService'])->name('services.archive');
    Route::post('services/{service}/images', [ProfileController::class, 'addImage'])->name('services.images.store');
    Route::patch('service-images/{image}', [ProfileController::class, 'updateImage'])->name('services.images.update');
    Route::delete('service-images/{image}', [ProfileController::class, 'deleteImage'])->name('services.images.destroy');
});
