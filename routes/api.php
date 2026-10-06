<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AddressController;
use App\Http\Controllers\Api\V1\Audit\AuditLogController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\Client\Dashboard\ClientDashboardController;
use App\Http\Controllers\Api\V1\FavoriteController;
use App\Http\Controllers\Api\V1\Notification\NotificationController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PaymentWebhookController;
use App\Http\Controllers\Api\V1\Professional\Dashboard\ProfessionalDashboardController;
use App\Http\Controllers\Api\V1\ProfessionalSearchController;
use App\Http\Controllers\Api\V1\ProfessionalServiceController;
use App\Http\Controllers\Api\V1\ProfessionalServiceImageController;
use App\Http\Controllers\Api\V1\ProfessionalSkillController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\QuotationController;
use App\Http\Controllers\Api\V1\QuotationOfferController;
use App\Http\Controllers\Api\V1\Rbac\RoleController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\ServiceRequestController;
use App\Http\Controllers\Api\V1\SkillController;
use App\Http\Controllers\Api\V1\WalletController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth-login');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth-sensitive');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth-sensitive');
        Route::get('verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
            ->middleware('signed')
            ->name('verification.verify');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('change-password', [AuthController::class, 'changePassword'])->middleware('throttle:auth-sensitive');
            Route::post('email/verification-notification', [AuthController::class, 'resendVerification'])->middleware('throttle:auth-sensitive');
        });
    });

    Route::prefix('skills')->middleware(['security.headers', 'throttle:api'])->group(function (): void {
        Route::get('/', [SkillController::class, 'index']);
        Route::get('{skill}', [SkillController::class, 'show']);
    });

    Route::prefix('categories')->middleware(['security.headers', 'throttle:api'])->group(function (): void {
        Route::get('/', [CategoryController::class, 'index']);
        Route::get('{category}', [CategoryController::class, 'show']);
    });

    Route::prefix('services')->middleware(['security.headers', 'throttle:api'])->group(function (): void {
        Route::get('/', [ServiceController::class, 'index']);
        Route::get('{service}', [ServiceController::class, 'show']);
    });

    Route::get('professionals', [ProfessionalSearchController::class, 'index'])
        ->middleware(['security.headers', 'throttle:api']);

    Route::post('payments/webhooks/{provider}', [PaymentWebhookController::class, 'handle'])
        ->middleware(['security.headers', 'throttle:api']);

    Route::middleware(['security.headers', 'auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::get('profile', [ProfileController::class, 'show']);
        Route::patch('profile', [ProfileController::class, 'update']);

        Route::apiResource('addresses', AddressController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::post('addresses/{address}/default', [AddressController::class, 'setDefault']);

        Route::prefix('service-requests')->group(function (): void {
            Route::get('/', [ServiceRequestController::class, 'clientIndex'])->middleware('role:client');
            Route::post('/', [ServiceRequestController::class, 'store'])->middleware('role:client');
            Route::get('{serviceRequest}', [ServiceRequestController::class, 'show']);
            Route::patch('{serviceRequest}', [ServiceRequestController::class, 'update'])->middleware('role:client');
            Route::post('{serviceRequest}/submit', [ServiceRequestController::class, 'submit'])->middleware('role:client');
            Route::post('{serviceRequest}/cancel', [ServiceRequestController::class, 'cancel'])->middleware('role:client');
            Route::post('{serviceRequest}/reject', [ServiceRequestController::class, 'reject'])->middleware('role:professional');
            Route::post('{serviceRequest}/quotation', [QuotationController::class, 'store'])->middleware('role:professional');
        });

        Route::get('quotations/{quotation}', [QuotationController::class, 'show']);
        Route::post('quotations/{quotation}/accept', [QuotationController::class, 'accept'])->middleware('role:client');
        Route::post('quotations/{quotation}/reject', [QuotationController::class, 'reject'])->middleware('role:client');
        Route::post('quotations/{quotation}/offers', [QuotationOfferController::class, 'store']);
        Route::get('orders/{order}', [OrderController::class, 'show']);
        Route::post('orders/{order}/review', [ReviewController::class, 'store'])->middleware('role:client');
        Route::get('reviews/{review}', [ReviewController::class, 'show']);
        Route::post('reviews/{review}/response', [ReviewController::class, 'respond'])->middleware('role:professional');
        Route::post('admin/reviews/{review}/moderate', [ReviewController::class, 'moderate'])->middleware('permission:reviews.moderate');
        Route::post('admin/review-responses/{reviewResponse}/moderate', [ReviewController::class, 'moderateResponse'])->middleware('permission:reviews.moderate');
        Route::get('orders/{order}/payment', [PaymentController::class, 'show'])->middleware('role:client');
        Route::post('orders/{order}/payments', [PaymentController::class, 'store'])
            ->middleware(['role:client', 'throttle:payment']);

        Route::prefix('professional/wallet')->middleware('role:professional')->group(function (): void {
            Route::get('/', [WalletController::class, 'show']);
            Route::post('withdrawals', [WalletController::class, 'withdraw'])->middleware('throttle:payment');
        });

        Route::get('favorites', [FavoriteController::class, 'index']);
        Route::put('favorites/{professionalProfile}', [FavoriteController::class, 'store']);
        Route::delete('favorites/{professionalProfile}', [FavoriteController::class, 'destroy']);

        Route::prefix('admin/skills')->group(function (): void {
            Route::get('/', [SkillController::class, 'adminIndex'])->middleware('permission:skills.view');
            Route::get('{skill}', [SkillController::class, 'adminShow'])->middleware('permission:skills.view');
            Route::post('/', [SkillController::class, 'store'])->middleware('permission:skills.manage');
            Route::patch('{skill}', [SkillController::class, 'update'])->middleware('permission:skills.manage');
            Route::delete('{skill}', [SkillController::class, 'destroy'])->middleware('permission:skills.manage');
        });

        Route::prefix('client')->middleware('role:client')->group(function (): void {
            Route::get('dashboard', ClientDashboardController::class);
        });

        Route::prefix('professional')->middleware('role:professional')->group(function (): void {
            Route::get('dashboard', ProfessionalDashboardController::class);
            Route::get('service-requests', [ServiceRequestController::class, 'professionalIndex']);

            Route::prefix('skills')->group(function (): void {
                Route::get('/', [ProfessionalSkillController::class, 'index']);
                Route::post('/', [ProfessionalSkillController::class, 'store']);
                Route::delete('{skill}', [ProfessionalSkillController::class, 'destroy']);
            });

            Route::prefix('services')->group(function (): void {
                Route::get('/', [ProfessionalServiceController::class, 'index']);
                Route::post('/', [ProfessionalServiceController::class, 'store']);
                Route::get('{service}', [ProfessionalServiceController::class, 'show']);
                Route::patch('{service}', [ProfessionalServiceController::class, 'update']);
                Route::delete('{service}', [ProfessionalServiceController::class, 'destroy']);
                Route::post('{service}/publish', [ProfessionalServiceController::class, 'publish']);
                Route::post('{service}/unpublish', [ProfessionalServiceController::class, 'unpublish']);
                Route::post('{service}/images', [ProfessionalServiceImageController::class, 'store']);
            });
        });

        Route::patch('professional/service-images/{image}', [ProfessionalServiceImageController::class, 'update'])
            ->middleware('role:professional');
        Route::delete('professional/service-images/{image}', [ProfessionalServiceImageController::class, 'destroy'])
            ->middleware('role:professional');

        Route::prefix('admin/categories')->group(function (): void {
            Route::get('/', [CategoryController::class, 'adminIndex'])->middleware('permission:categories.view');
            Route::get('{category}', [CategoryController::class, 'adminShow'])->middleware('permission:categories.view');
            Route::post('/', [CategoryController::class, 'store'])->middleware('permission:categories.manage');
            Route::patch('{category}', [CategoryController::class, 'update'])->middleware('permission:categories.manage');
            Route::delete('{category}', [CategoryController::class, 'destroy'])->middleware('permission:categories.manage');
        });

        Route::prefix('notifications')->group(function (): void {
            Route::get('/', [NotificationController::class, 'index']);
            Route::post('{notification}/read', [NotificationController::class, 'markAsRead']);
            Route::post('read-all', [NotificationController::class, 'markAllAsRead']);
            Route::get('preferences', [NotificationController::class, 'preferences']);
            Route::patch('preferences', [NotificationController::class, 'updatePreferences']);
        });
    });

    Route::middleware(['security.headers', 'auth:sanctum', 'throttle:api'])->prefix('audit')->group(function (): void {
        Route::get('logs', [AuditLogController::class, 'index'])->middleware('permission:audit.view');
        Route::get('logs/{auditLog}', [AuditLogController::class, 'show'])->middleware('permission:audit.view');
    });

    Route::middleware(['security.headers', 'auth:sanctum', 'throttle:api'])->prefix('rbac')->group(function (): void {
        Route::get('me', function (Request $request) {
            $user = $request->user()->load(['roles.permissions']);

            return response()->json([
                'message' => 'Autorisations effectives récupérées avec succès.',
                'data' => [
                    'roles' => $user->roles->pluck('name')->values(),
                    'permissions' => $user->roles
                        ->flatMap(fn ($role) => $role->permissions->pluck('name'))
                        ->unique()
                        ->values(),
                ],
                'meta' => [],
            ]);
        });

        Route::get('roles', [RoleController::class, 'index'])->middleware('permission:rbac.view');
        Route::get('roles/{role}', [RoleController::class, 'show'])->middleware('permission:rbac.view');
        Route::get('permissions', [RoleController::class, 'permissions'])->middleware('permission:rbac.view');
        Route::post('roles', [RoleController::class, 'store'])->middleware('permission:rbac.manage');
        Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('permission:rbac.manage');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:rbac.manage');
    });
});
