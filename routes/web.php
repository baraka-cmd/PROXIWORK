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
use App\Http\Controllers\Web\Client\ServiceCatalogController;
use App\Http\Controllers\Web\Client\ServiceRequestController;
use App\Http\Controllers\Web\EmailVerificationController;
use App\Http\Controllers\Web\HomePageController;
use App\Http\Controllers\Web\LoginController;
use App\Http\Controllers\Web\NewPasswordController;
use App\Http\Controllers\Web\PasswordResetLinkController;
use App\Http\Controllers\Web\Professional\DashboardController as ProfessionalDashboardController;
use App\Http\Controllers\Web\Professional\DocumentController as ProfessionalDocumentController;
use App\Http\Controllers\Web\Professional\MessageController;
use App\Http\Controllers\Web\Professional\NotificationController;
use App\Http\Controllers\Web\Professional\OrderController as ProfessionalOrderController;
use App\Http\Controllers\Web\Professional\ProfileController;
use App\Http\Controllers\Web\Professional\QuotationController as ProfessionalQuotationController;
use App\Http\Controllers\Web\Professional\RevenueController;
use App\Http\Controllers\Web\Professional\ReviewController;
use App\Http\Controllers\Web\Professional\ServiceRealizationController as ProfessionalServiceRealizationController;
use App\Http\Controllers\Web\Professional\ServiceRequestController as ProfessionalServiceRequestController;
use App\Http\Controllers\Web\Professional\WalletController as ProfessionalWalletController;
use App\Http\Controllers\Web\Professional\WithdrawalController as ProfessionalWithdrawalController;
use App\Http\Controllers\Web\PublicSearchController;
use App\Http\Controllers\Web\PublicServiceRealizationController;
use App\Http\Controllers\Web\RbacDashboardController;
use App\Http\Controllers\Web\RegisterController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomePageController::class, 'index'])->name('home');
Route::get('/search', [PublicSearchController::class, 'search'])->name('public.search');
Route::get('/service-realizations/{realization}/image', [PublicServiceRealizationController::class, 'image'])->name('public.service-realizations.image');
Route::get('/services/{service:slug}', [PublicSearchController::class, 'service'])->name('public.services.show');
Route::get('/professionals', [PublicSearchController::class, 'professionals'])->name('public.professionals.index');
Route::get('/professionals/{professional}/photo', [PublicSearchController::class, 'professionalPhoto'])->name('public.professionals.photo');
Route::get('/professionals/{professional}', [PublicSearchController::class, 'professional'])->name('public.professionals.show');

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
        Route::post('favorites/services/{service}', [FavoriteController::class, 'storeService'])->name('favorites.services.store');
        Route::delete('favorites/{favorite}', [FavoriteController::class, 'destroy'])->name('favorites.destroy');
        Route::get('requests', [ServiceRequestController::class, 'index'])->name('requests.index');
        Route::get('requests/create', [ServiceRequestController::class, 'create'])->name('requests.create');
        Route::post('requests', [ServiceRequestController::class, 'store'])->name('requests.store');
        Route::get('requests/{serviceRequest}', [ServiceRequestController::class, 'show'])->name('requests.show');
        Route::get('requests/{serviceRequest}/edit', [ServiceRequestController::class, 'edit'])->name('requests.edit');
        Route::put('requests/{serviceRequest}', [ServiceRequestController::class, 'update'])->name('requests.update');
        Route::post('requests/{serviceRequest}/address', [ServiceRequestController::class, 'setAddress'])->name('requests.address');
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
        Route::post('orders/{order}/payment', [PaymentController::class, 'store'])->middleware('throttle:payment')->name('payments.store');
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');
        Route::post('payments/{payment}/refund-requests', [PaymentController::class, 'requestRefund'])->middleware('throttle:payment')->name('payments.refunds.store');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        Route::get('profile', [ClientProfileController::class, 'show'])->name('profile');
        Route::get('profile/edit', [ClientProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('profile', [ClientProfileController::class, 'update'])->name('profile.update');
        Route::get('profile/photo', [ClientProfileController::class, 'photo'])->name('profile.photo');
        Route::post('profile/photo', [ClientProfileController::class, 'storePhoto'])->middleware('throttle:6,1')->name('profile.photo.store');
        Route::delete('profile/photo', [ClientProfileController::class, 'destroyPhoto'])->name('profile.photo.destroy');
        Route::patch('profile/password', [ClientProfileController::class, 'updatePassword'])->middleware('throttle:6,1')->name('profile.password.update');
        Route::post('profile/email-change', [ClientProfileController::class, 'requestEmailChange'])->middleware('throttle:6,1')->name('profile.email-change.store');
        Route::get('profile/email-change/{token}', [ClientProfileController::class, 'showEmailChangeConfirmation'])->middleware('throttle:10,1')->name('profile.email-change.confirmation');
        Route::post('profile/email-change/{token}/confirm', [ClientProfileController::class, 'confirmEmailChange'])->middleware('throttle:6,1')->name('profile.email-change.confirm');
        Route::get('addresses', [ClientAddressController::class, 'index'])->name('addresses.index');
        Route::get('addresses/create', [ClientAddressController::class, 'create'])->name('addresses.create');
        Route::post('addresses', [ClientAddressController::class, 'store'])->middleware('throttle:10,1')->name('addresses.store');
        Route::get('addresses/{address}/edit', [ClientAddressController::class, 'edit'])->name('addresses.edit');
        Route::patch('addresses/{address}', [ClientAddressController::class, 'update'])->middleware('throttle:10,1')->name('addresses.update');
        Route::delete('addresses/{address}', [ClientAddressController::class, 'destroy'])->middleware('throttle:10,1')->name('addresses.destroy');
        Route::post('addresses/{address}/default', [ClientAddressController::class, 'makeDefault'])->middleware('throttle:10,1')->name('addresses.default');
        Route::get('notifications', [ClientNotificationController::class, 'index'])->name('notifications.index');
        Route::patch('notifications/preferences', [ClientNotificationController::class, 'updatePreferences'])
            ->middleware('throttle:10,1')
            ->name('notifications.preferences.update');
        Route::post('notifications/read-all', [ClientNotificationController::class, 'markAllAsRead'])
            ->middleware('throttle:10,1')
            ->name('notifications.read-all');
        Route::post('notifications/{notification}/read', [ClientNotificationController::class, 'markAsRead'])
            ->middleware('throttle:30,1')
            ->name('notifications.read');
        Route::post('notifications/{notification}/archive', [ClientNotificationController::class, 'archive'])
            ->middleware('throttle:20,1')
            ->name('notifications.archive');
        Route::post('notifications/{notification}/restore', [ClientNotificationController::class, 'restore'])
            ->middleware('throttle:20,1')
            ->name('notifications.restore');
        Route::get('messages', [ClientMessageController::class, 'index'])->name('messages.index');
        Route::post('professionals/{professionalProfile}/messages', [ClientMessageController::class, 'start'])->middleware('throttle:message-send')->name('messages.start');
        Route::get('messages/attachments/{attachment}', [ClientMessageController::class, 'downloadAttachment'])->name('messages.attachments.download');
        Route::get('messages/{conversation}/updates', [ClientMessageController::class, 'updates'])->middleware('throttle:60,1')->name('messages.updates');
        Route::get('messages/{conversation}', [ClientMessageController::class, 'show'])->name('messages.show');
        Route::post('messages/{conversation}', [ClientMessageController::class, 'store'])->middleware('throttle:message-send')->name('messages.store');
        Route::post('messages/{conversation}/read', [ClientMessageController::class, 'read'])->middleware('throttle:30,1')->name('messages.read');
        Route::post('messages/{conversation}/read-receipts', [ClientMessageController::class, 'toggleReadReceipts'])->middleware('throttle:30,1')->name('messages.read-receipts');
        Route::post('messages/{conversation}/pin', [ClientMessageController::class, 'togglePin'])->middleware('throttle:30,1')->name('messages.pin');
        Route::post('messages/{conversation}/archive', [ClientMessageController::class, 'toggleArchive'])->middleware('throttle:30,1')->name('messages.archive');
        Route::post('messages/{conversation}/block', [ClientMessageController::class, 'block'])->middleware('throttle:10,1')->name('messages.block');
        Route::post('messages/{conversation}/messages/{message}/report', [ClientMessageController::class, 'reportMessage'])->middleware('throttle:10,1')->name('messages.report');
        Route::get('services', [ServiceCatalogController::class, 'index'])
            ->name('services.index');
    });
});

Route::middleware('guest')->group(function (): void {
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:5,1')->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify.web');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:auth-sensitive')
        ->name('verification.send');
});

Route::prefix('professional')->name('professional.')->middleware(['auth', 'active.account', 'role:professional'])->group(function (): void {
    Route::get('/', ProfessionalDashboardController::class)->name('dashboard');
    Route::get('requests', [ProfessionalServiceRequestController::class, 'index'])->name('requests.index');
    Route::get('requests/{serviceRequest}', [ProfessionalServiceRequestController::class, 'show'])->name('requests.show');
    Route::post('requests/{serviceRequest}/reject', [ProfessionalServiceRequestController::class, 'reject'])->middleware('throttle:10,1')->name('requests.reject');
    Route::get('quotes', [ProfessionalQuotationController::class, 'index'])->name('quotes.index');
    Route::get('quotes/create/{serviceRequest}', [ProfessionalQuotationController::class, 'create'])->name('quotes.create');
    Route::post('quotes/create/{serviceRequest}', [ProfessionalQuotationController::class, 'store'])->middleware('throttle:10,1')->name('quotes.store');
    Route::get('quotes/{quotation}', [ProfessionalQuotationController::class, 'show'])->name('quotes.show');
    Route::post('quotes/{quotation}/accept-client-offer', [ProfessionalQuotationController::class, 'acceptClientOffer'])->middleware('throttle:10,1')->name('quotes.accept-client-offer');
    Route::post('quotes/{quotation}/reject-client-offer', [ProfessionalQuotationController::class, 'rejectClientOffer'])->middleware('throttle:10,1')->name('quotes.reject-client-offer');
    Route::post('quotes/{quotation}/counter-offer', [ProfessionalQuotationController::class, 'counterOffer'])->middleware('throttle:10,1')->name('quotes.counter-offer');
    Route::post('quotes/{quotation}/renew', [ProfessionalQuotationController::class, 'renew'])->middleware('throttle:10,1')->name('quotes.renew');
    Route::get('profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('profile/photo', [ProfileController::class, 'photo'])->name('profile.photo');
    Route::post('profile/photo', [ProfileController::class, 'storePhoto'])->middleware('throttle:6,1')->name('profile.photo.store');
    Route::delete('profile/photo', [ProfileController::class, 'destroyPhoto'])->middleware('throttle:10,1')->name('profile.photo.destroy');
    Route::patch('profile/skills', [ProfileController::class, 'updateSkills'])->middleware('throttle:10,1')->name('profile.skills.update');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('profile', [ProfileController::class, 'show'])->name('profile');
    Route::get('documents', [ProfessionalDocumentController::class, 'index'])->name('documents.index');
    Route::post('documents', [ProfessionalDocumentController::class, 'store'])->name('documents.store');
    Route::delete('documents/{document}', [ProfessionalDocumentController::class, 'destroy'])->name('documents.destroy');
    Route::get('services', [ProfileController::class, 'services'])->name('services.index');
    Route::get('services/create', [ProfileController::class, 'createService'])->name('services.create');
    Route::post('services', [ProfileController::class, 'storeService'])->name('services.store');
    Route::get('services/{service}/edit', [ProfileController::class, 'editService'])->name('services.edit');
    Route::get('services/{service}/preview', [ProfileController::class, 'previewService'])->name('services.preview');
    Route::patch('services/{service}', [ProfileController::class, 'updateService'])->name('services.update');
    Route::post('services/{service}/publish', [ProfileController::class, 'publishService'])->name('services.publish');
    Route::post('services/{service}/unpublish', [ProfileController::class, 'unpublishService'])->name('services.unpublish');
    Route::delete('services/{service}', [ProfileController::class, 'archiveService'])->name('services.archive');
    Route::post('services/{service}/images', [ProfileController::class, 'addImage'])->name('services.images.store');
    Route::post('services/{service}/realizations', [ProfessionalServiceRealizationController::class, 'store'])->middleware('throttle:6,1')->name('services.realizations.store');
    Route::get('service-realizations/{realization}/file', [ProfessionalServiceRealizationController::class, 'file'])->name('services.realizations.file');
    Route::delete('service-realizations/{realization}', [ProfessionalServiceRealizationController::class, 'destroy'])->middleware('throttle:10,1')->name('services.realizations.destroy');
    Route::patch('service-images/{image}/file', [ProfileController::class, 'replaceImage'])->middleware('throttle:10,1')->name('services.images.replace');
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
    Route::get('messages/attachments/{attachment}', [MessageController::class, 'downloadAttachment'])->name('messages.attachments.download');
    Route::get('messages/{conversation}/updates', [MessageController::class, 'updates'])->middleware('throttle:60,1')->name('messages.updates');
    Route::get('messages/{conversation}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('messages/{conversation}', [MessageController::class, 'store'])->middleware('throttle:message-send')->name('messages.store');
    Route::post('messages/{conversation}/read', [MessageController::class, 'read'])->middleware('throttle:30,1')->name('messages.read');
    Route::post('messages/{conversation}/read-receipts', [MessageController::class, 'toggleReadReceipts'])->middleware('throttle:30,1')->name('messages.read-receipts');
    Route::post('messages/{conversation}/pin', [MessageController::class, 'togglePin'])->middleware('throttle:30,1')->name('messages.pin');
    Route::post('messages/{conversation}/archive', [MessageController::class, 'toggleArchive'])->middleware('throttle:30,1')->name('messages.archive');
    Route::post('messages/{conversation}/block', [MessageController::class, 'block'])->middleware('throttle:10,1')->name('messages.block');
    Route::post('messages/{conversation}/messages/{message}/report', [MessageController::class, 'reportMessage'])->middleware('throttle:10,1')->name('messages.report');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->middleware('throttle:10,1')->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->middleware('throttle:20,1')->name('notifications.read');
});

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('login', [AdminAuthController::class, 'create'])->name('login');
        Route::post('login', [AdminAuthController::class, 'store'])->middleware('throttle:admin-login')->name('login.store');
    });
    Route::middleware(['auth', 'active.account'])->group(function (): void {
        Route::get('dashboard', [DashboardController::class, 'index'])->middleware('permission:admin.dashboard.view')->name('dashboard');
        Route::post('logout', [AdminAuthController::class, 'destroy'])->name('logout');
        Route::middleware('permission:rbac.view')->group(function (): void {
            Route::get('rbac', [RbacDashboardController::class, 'index'])->name('rbac.dashboard');
        });
    });
});
