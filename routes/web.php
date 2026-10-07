<?php

declare(strict_types=1);

use App\Http\Controllers\Web\AdminAuthController;
use App\Http\Controllers\Web\LoginController;
use App\Http\Controllers\Web\RegisterController;
use App\Http\Controllers\Web\RbacDashboardController;
use App\Http\Controllers\Web\Client\FavoriteController;
use App\Http\Controllers\Web\Client\QuotationController;
use App\Http\Controllers\Web\Client\ServiceRequestController;
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

    Route::prefix('client')->name('client.')->middleware(['active.account', 'role:client'])->group(function (): void {
        Route::get('favorites', [FavoriteController::class, 'index'])->name('favorites.index');
        Route::put('favorites/{professionalProfile}', [FavoriteController::class, 'store'])->name('favorites.store');
        Route::delete('favorites/{favorite}', [FavoriteController::class, 'destroy'])->name('favorites.destroy');

        Route::get('requests', [ServiceRequestController::class, 'index'])->name('requests.index');
        Route::get('requests/{serviceRequest}', [ServiceRequestController::class, 'show'])->name('requests.show');
        Route::post('requests/{serviceRequest}/submit', [ServiceRequestController::class, 'submit'])->name('requests.submit');
        Route::post('requests/{serviceRequest}/cancel', [ServiceRequestController::class, 'cancel'])->name('requests.cancel');

        Route::get('quotes', [QuotationController::class, 'index'])->name('quotes.index');
        Route::get('quotes/{quotation}', [QuotationController::class, 'show'])->name('quotes.show');
        Route::post('quotes/{quotation}/accept', [QuotationController::class, 'accept'])->name('quotes.accept');
        Route::post('quotes/{quotation}/reject', [QuotationController::class, 'reject'])->name('quotes.reject');
        Route::post('quotes/{quotation}/counter-offer', [QuotationController::class, 'counterOffer'])->name('quotes.counter-offer');
    });
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
