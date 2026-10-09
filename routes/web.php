<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Admin\DashboardController;
use App\Http\Controllers\Web\AdminAuthController;
use App\Http\Controllers\Web\Client\AddressController as ClientAddressController;
use App\Http\Controllers\Web\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Web\Client\FavoriteController;
use App\Http\Controllers\Web\Client\MessageController as ClientMessageController;
use App\Http\Controllers\Web\Client\NotificationController as ClientNotificationController;
use App\Http\Controllers\Web\Client\OrderController;
use App\Http\Controllers\Web\Client\PaymentController;
use App\Http\Controllers\Web\Client\ProfileController as ClientProfileController;
use App\Http\Controllers\Web\Client\QuotationController;
use App\Http\Controllers\Web\Client\ServiceRequestController;
use App\Http\Controllers\Web\LoginController;
use App\Http\Controllers\Web\NewPasswordController;
use App\Http\Controllers\Web\PasswordResetLinkController;
use App\Http\Controllers\Web\Professional\DashboardController as ProfessionalDashboardController;
use App\Http\Controllers\Web\Professional\MessageController;
use App\Http\Controllers\Web\Professional\NotificationController;
use App\Http\Controllers\Web\Professional\OrderController as ProfessionalOrderController;
use App\Http\Controllers\Web\Professional\ProfileController;
use App\Http\Controllers\Web\Professional\RevenueController;
use App\Http\Controllers\Web\Professional\ReviewController;
use App\Http\Controllers\Web\Professional\WalletController as ProfessionalWalletController;
use App\Http\Controllers\Web\Professional\WithdrawalController as ProfessionalWithdrawalController;
use App\Http\Controllers\Web\PublicSearchController;
use App\Http\Controllers\Web\RegisterController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'public.home')->name('home');
Route::get('/search', [PublicSearchController::class, 'search'])->name('public.search');
Route::get('/professionals', [PublicSearchController::class, 'professionals'])->name('public.professionals.index');

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:5,1')->name('register.store');
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', function (Request $request): RedirectResponse {
        $user = $request->user();

        abort_unless($user !== null, 403);

        if ($user->hasRole('admin')) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->hasRole('professional')) {
            return redirect()->route('professional.dashboard');
        }

        if ($user->hasRole('client')) {
            return redirect()->route('client.dashboard');
        }

        abort(403, 'Aucun espace de travail n’est associé à ce compte.');
    })->name('dashboard');

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::prefix('client')->name('client.')->middleware(['active.account', 'role:client'])->group(function (): void {
        Route::get('/', ClientDashboardController::class)->name('dashboard');
        Route::get('favorites', [FavoriteController::class, 'index'])->name('favorites.index');
        Route::put('favorites/{professionalProfile}', [FavoriteController::class, 'store'])->name('favorites.store');
        Route::delete('favorites/{favorite}', [FavoriteController::class, 'destroy'])->name('favorites.destroy');
        Route::get('requests', [ServiceRequestController::class, 'index'])->name('requests.index');
        Route::get('requests/create', [ServiceRequestController::class, 'create'])->name('requests.create');
        Route::post('requests', [ServiceRequestController::class, 'store'])->name('requests.store');
        Route::get('requests/{serviceRequest}', [ServiceRequestController::class, 'show'])->name('requests.show');
        Route::get('requests/{serviceRequest}/edit', [ServiceRequestController::class, 'edit'])->name('requests.edit');
        Route::put('requests/{serviceRequest}', [ServiceRequestController::class, 'update'])->name('requests.update');
        Route::post('requests/{serviceRequest}/submit', [ServiceRequestController::class, 'submit'])->name('requests.submit');
        Route::post('requests/{serviceRequest}/cancel', [ServiceRequestController::class, 'cancel'])->name('requests.cancel');
        Route::get('quotes', [QuotationController::class, 'index'])->name('quotes.index');
        Route::get('quotes/{quotation}', [QuotationController::class, 'show'])->name('quotes.show');
        Route::post('quotes/{quotation}/accept', [QuotationController::class, 'accept'])->name('quotes.accept');
        Route::post('quotes/{quotation}/reject', [QuotationController::class, 'reject'])->name('quotes.reject');
        Route::post('quotes/{quotation}/counter-offer', [QuotationController::class, 'counterOffer'])->name('quotes.counter-offer');
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}/payment', [OrderController::class, 'pay'])->name('orders.payment');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/payment', [PaymentController::class, 'store'])->name('payments.store');
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        Route::get('profile', [ClientProfileController::class, 'show'])->name('profile');
        Route::get('profile/edit', [ClientProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('profile', [ClientProfileController::class, 'update'])->name('profile.update');
        Route::get('addresses', [ClientAddressController::class, 'index'])->name('addresses.index');
        Route::get('addresses/create', [ClientAddressController::class, 'create'])->name('addresses.create');
        Route::post('addresses', [ClientAddressController::class, 'store'])->name('addresses.store');
        Route::get('addresses/{address}/edit', [ClientAddressController::class, 'edit'])->name('addresses.edit');
        Route::patch('addresses/{address}', [ClientAddressController::class, 'update'])->name('addresses.update');
        Route::delete('addresses/{address}', [ClientAddressController::class, 'destroy'])->name('addresses.destroy');
        Route::post('addresses/{address}/default', [ClientAddressController::class, 'makeDefault'])->name('addresses.default');
        Route::get('notifications', [ClientNotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/{notification}/read', [ClientNotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::post('notifications/read-all', [ClientNotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
        Route::get('messages', [ClientMessageController::class, 'index'])->name('messages.index');
        Route::get('messages/{conversation}', [ClientMessageController::class, 'show'])->name('messages.show');
        Route::post('messages/{conversation}', [ClientMessageController::class, 'store'])->name('messages.store');
        Route::post('messages/{conversation}/read', [ClientMessageController::class, 'read'])->name('messages.read');
    });
});

Route::middleware('guest')->group(function (): void {
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:5,1')->name('password.update');
});

Route::prefix('professional')->name('professional.')->middleware(['auth', 'active.account', 'role:professional'])->group(function (): void {
    Route::get('/', ProfessionalDashboardController::class)->name('dashboard');
    Route::get('profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
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
    Route::get('orders', [ProfessionalOrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [ProfessionalOrderController::class, 'show'])->name('orders.show');
    Route::get('revenues', [RevenueController::class, 'index'])->name('revenues.index');
    Route::get('wallet', [ProfessionalWalletController::class, 'index'])->name('wallet');
    Route::get('withdrawals', [ProfessionalWithdrawalController::class, 'index'])->name('withdrawals');
    Route::get('withdrawals/create', [ProfessionalWithdrawalController::class, 'create'])->name('withdrawals.create');
    Route::post('withdrawals', [ProfessionalWithdrawalController::class, 'store'])->middleware('throttle:payment')->name('withdrawals.store');
    Route::get('withdrawals/{withdrawal}', [ProfessionalWithdrawalController::class, 'show'])->name('withdrawals.show');
    Route::get('reviews', [ReviewController::class, 'index'])->name('reviews');
    Route::post('reviews/{review}/response', [ReviewController::class, 'respond'])->middleware('throttle:10,1')->name('reviews.respond');
    Route::get('messages', [MessageController::class, 'index'])->name('messages');
    Route::get('messages/{conversation}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('messages/{conversation}', [MessageController::class, 'store'])->middleware('throttle:10,1')->name('messages.store');
    Route::post('messages/{conversation}/read', [MessageController::class, 'read'])->name('messages.read');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->middleware('throttle:10,1')->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->middleware('throttle:20,1')->name('notifications.read');
});

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('login', [AdminAuthController::class, 'create'])->name('login');
        Route::post('login', [AdminAuthController::class, 'store'])->middleware('throttle:admin-login')->name('login.store');
    });
    Route::middleware('auth')->group(function (): void {
        Route::get('dashboard', [DashboardController::class, 'index'])->middleware('permission:admin.dashboard.view')->name('dashboard');
        Route::post('logout', [AdminAuthController::class, 'destroy'])->name('logout');
        Route::middleware('permission:rbac.view')->group(function (): void {
            Route::get('rbac', [RbacDashboardController::class, 'index'])->name('rbac.dashboard');
        });
    });
});
