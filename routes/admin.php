<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Admin\AnalyticsController;
use App\Http\Controllers\Web\Admin\AuditController;
use App\Http\Controllers\Web\Admin\CategoryController;
use App\Http\Controllers\Web\Admin\FinanceController;
use App\Http\Controllers\Web\Admin\FinancialOperationsController;
use App\Http\Controllers\Web\Admin\ModerationCenterController;
use App\Http\Controllers\Web\Admin\NotificationDeliveryController;
use App\Http\Controllers\Web\Admin\OrderController;
use App\Http\Controllers\Web\Admin\OrderDisputeController;
use App\Http\Controllers\Web\Admin\PaymentController;
use App\Http\Controllers\Web\Admin\PermissionController;
use App\Http\Controllers\Web\Admin\ProfessionalController;
use App\Http\Controllers\Web\Admin\ProfessionalDocumentController;
use App\Http\Controllers\Web\Admin\RefundController;
use App\Http\Controllers\Web\Admin\ReportController;
use App\Http\Controllers\Web\Admin\RoleController;
use App\Http\Controllers\Web\Admin\ServiceController;
use App\Http\Controllers\Web\Admin\ServiceRealizationController as AdminServiceRealizationController;
use App\Http\Controllers\Web\Admin\ServiceRequestController;
use App\Http\Controllers\Web\Admin\SkillController;
use App\Http\Controllers\Web\Admin\SupportController;
use App\Http\Controllers\Web\Admin\UserController;
use App\Http\Controllers\Web\Admin\VerificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active.account', 'permission:admin.users.view'])->group(function (): void {
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
});
Route::middleware(['auth', 'active.account'])->group(function (): void {
    Route::post('users/{user}/suspend', [UserController::class, 'suspend'])->middleware('permission:admin.users.suspend')->name('users.suspend');
    Route::post('users/{user}/activate', [UserController::class, 'activate'])->middleware('permission:admin.users.activate')->name('users.activate');
});

Route::middleware(['auth', 'active.account', 'permission:rbac.view'])->group(function (): void {
    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
    Route::get('permissions/{permission}', [PermissionController::class, 'show'])->name('permissions.show');
});
Route::middleware(['auth', 'active.account', 'permission:rbac.manage'])->group(function (): void {
    Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
    Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
});
Route::middleware(['auth', 'permission:rbac.view'])->group(function (): void {
    Route::get('roles/{role}', [RoleController::class, 'show'])->name('roles.show');
});

Route::middleware(['auth', 'active.account', 'permission:admin.professionals.view'])->group(function (): void {
    Route::get('professionals', [ProfessionalController::class, 'index'])->name('professionals.index');
    Route::get('professionals/{professional}', [ProfessionalController::class, 'show'])->name('professionals.show');
    Route::get('professionals/documents/{document}', [ProfessionalDocumentController::class, 'download'])->name('professionals.documents.download');
});
Route::middleware(['auth', 'active.account'])->group(function (): void {
    Route::post('professionals/{professional}/suspend', [ProfessionalController::class, 'suspend'])->middleware('permission:admin.professionals.suspend')->name('professionals.suspend');
    Route::post('professionals/{professional}/activate', [ProfessionalController::class, 'activate'])->middleware('permission:admin.professionals.activate')->name('professionals.activate');
    Route::post('professionals/{professional}/verification/start', [ProfessionalController::class, 'startReview'])->middleware('permission:admin.professionals.review')->name('professionals.verification.start');
    Route::post('professionals/{professional}/verification/verify', [ProfessionalController::class, 'verify'])->middleware('permission:admin.professionals.verify')->name('professionals.verification.verify');
    Route::post('professionals/{professional}/verification/reject', [ProfessionalController::class, 'reject'])->middleware('permission:admin.professionals.reject')->name('professionals.verification.reject');
});

Route::middleware(['auth', 'active.account', 'permission:categories.view'])->group(function (): void {
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('categories/{category}', [CategoryController::class, 'show'])->name('categories.show');
});
Route::middleware(['auth', 'active.account', 'permission:categories.manage'])->group(function (): void {
    Route::get('categories/create', [CategoryController::class, 'create'])->name('categories.create');
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
    Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::post('categories/{category}/archive', [CategoryController::class, 'archive'])->name('categories.archive');
});
Route::middleware(['auth', 'active.account', 'permission:skills.view'])->group(function (): void {
    Route::get('skills', [SkillController::class, 'index'])->name('skills.index');
    Route::get('skills/{skill}/edit', [SkillController::class, 'edit'])->middleware('permission:skills.manage')->name('skills.edit');
});
Route::middleware(['auth', 'active.account', 'permission:skills.manage'])->group(function (): void {
    Route::get('skills/create', [SkillController::class, 'create'])->name('skills.create');
    Route::post('skills', [SkillController::class, 'store'])->name('skills.store');
    Route::put('skills/{skill}', [SkillController::class, 'update'])->name('skills.update');
    Route::post('skills/{skill}/archive', [SkillController::class, 'archive'])->name('skills.archive');
});

Route::middleware(['auth', 'active.account', 'permission:admin.services.view'])->group(function (): void {
    Route::get('services', [ServiceController::class, 'index'])->name('services.index');
    Route::get('services/{service}', [ServiceController::class, 'show'])->name('services.show');
});
Route::middleware(['auth', 'active.account', 'permission:admin.services.moderate'])->group(function (): void {
    Route::post('services/{service}/moderate', [ServiceController::class, 'moderate'])->name('services.moderate');
});
Route::middleware(['auth', 'active.account', 'permission:admin.services.view'])->group(function (): void {
    Route::get('service-realizations/{realization}/file', [AdminServiceRealizationController::class, 'file'])->name('service-realizations.file');
});
Route::middleware(['auth', 'active.account', 'permission:admin.service_realizations.moderate'])->group(function (): void {
    Route::post('service-realizations/{realization}/approve', [AdminServiceRealizationController::class, 'approve'])->name('service-realizations.approve');
    Route::post('service-realizations/{realization}/reject', [AdminServiceRealizationController::class, 'reject'])->name('service-realizations.reject');
});

Route::middleware(['auth', 'active.account'])->group(function (): void {
    Route::post('services/{service}/publish', [ServiceController::class, 'publish'])->middleware('permission:admin.services.manage')->name('services.publish');
    Route::post('services/{service}/archive', [ServiceController::class, 'archive'])->middleware('permission:admin.services.manage')->name('services.archive');
});

Route::middleware(['auth', 'active.account', 'permission:requests.view'])->group(function (): void {
    Route::get('service-requests', [ServiceRequestController::class, 'index'])->name('service-requests.index');
    Route::get('service-requests/{serviceRequest}', [ServiceRequestController::class, 'show'])->name('service-requests.show');
});
Route::middleware(['auth', 'active.account'])->group(function (): void {
    Route::post('service-requests/{serviceRequest}/cancel', [ServiceRequestController::class, 'cancel'])->middleware('permission:requests.manage')->name('service-requests.cancel');
    Route::post('service-requests/{serviceRequest}/reject', [ServiceRequestController::class, 'reject'])->middleware('permission:requests.manage')->name('service-requests.reject');
});

Route::middleware(['auth', 'active.account', 'permission:orders.view'])->group(function (): void {
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('orders/{order}/delivery-attachments/{attachment}', [OrderController::class, 'downloadDeliveryAttachment'])
        ->name('orders.delivery-attachments.download');
});
Route::middleware(['auth', 'active.account', 'permission:payments.view'])->group(function (): void {
    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
});

Route::middleware(['auth', 'active.account', 'permission:finance.view'])->group(function (): void {
    Route::get('finance', [FinanceController::class, 'overview'])->name('finance.overview');
});
Route::middleware(['auth', 'active.account', 'permission:transactions.view'])->group(function (): void {
    Route::get('transactions', [FinanceController::class, 'transactions'])->name('finance.transactions');
    Route::get('transactions/{transaction}', [FinanceController::class, 'transaction'])->whereNumber('transaction')->name('finance.transactions.show');
});
Route::middleware(['auth', 'active.account', 'permission:commissions.view'])->group(function (): void {
    Route::get('commissions', [FinanceController::class, 'commissions'])->name('finance.commissions');
    Route::get('commissions/{commission}', [FinanceController::class, 'commission'])->whereNumber('commission')->name('finance.commissions.show');
});
Route::middleware(['auth', 'active.account', 'permission:wallets.view'])->group(function (): void {
    Route::get('wallets', [FinanceController::class, 'wallets'])->name('finance.wallets');
    Route::get('wallets/{wallet}', [FinanceController::class, 'wallet'])->whereNumber('wallet')->name('finance.wallets.show');
});

Route::middleware(['auth', 'active.account', 'permission:refunds.view'])->group(function (): void {
    Route::get('refunds', [RefundController::class, 'index'])->name('finance.refunds');
    Route::get('refunds/{refund}', [RefundController::class, 'show'])->whereNumber('refund')->name('finance.refunds.show');
});
Route::middleware(['auth', 'active.account', 'permission:refunds.manage'])->group(function (): void {
    Route::post('refunds/{refund}/processing', [RefundController::class, 'processing'])->middleware('throttle:10,1')->name('finance.refunds.processing');
    Route::post('refunds/{refund}/reject', [RefundController::class, 'reject'])->middleware('throttle:10,1')->name('finance.refunds.reject');
});
Route::middleware(['auth', 'active.account', 'permission:refunds.execute'])->group(function (): void {
    Route::post('refunds/{refund}/succeeded', [RefundController::class, 'succeeded'])->middleware('throttle:5,1')->name('finance.refunds.succeeded');
    Route::post('refunds/{refund}/failed', [RefundController::class, 'failed'])->middleware('throttle:5,1')->name('finance.refunds.failed');
});
Route::middleware(['auth', 'active.account', 'permission:financial_movements.view'])->group(function (): void {
    Route::get('financial-movements', [FinancialOperationsController::class, 'movements'])->name('finance.movements');
    Route::get('financial-movements/{movement}', [FinancialOperationsController::class, 'movement'])->whereNumber('movement')->name('finance.movements.show');
});
Route::middleware(['auth', 'active.account', 'permission:financial_reports.view'])->group(function (): void {
    Route::get('financial-reports', [FinancialOperationsController::class, 'reports'])->name('finance.reports');
});
Route::middleware(['auth', 'active.account', 'permission:financial_reports.export'])->group(function (): void {
    Route::get('financial-reports/export', [FinancialOperationsController::class, 'export'])->middleware('throttle:5,1')->name('finance.reports.export');
});

Route::middleware(['auth', 'active.account', 'permission:support.manage'])->group(function (): void {
    Route::get('support', [SupportController::class, 'index'])->name('support.index');
    Route::get('support/{ticket}', [SupportController::class, 'show'])->name('support.show');
    Route::post('support/{ticket}/assign', [SupportController::class, 'assign'])->name('support.assign');
    Route::patch('support/{ticket}', [SupportController::class, 'update'])->name('support.update');
    Route::post('support/{ticket}/message', [SupportController::class, 'message'])->name('support.message');
    Route::post('support/{ticket}/resolve', [SupportController::class, 'resolve'])->name('support.resolve');
    Route::post('support/{ticket}/close', [SupportController::class, 'close'])->name('support.close');
});

Route::middleware(['auth', 'active.account', 'permission:audit.view'])->group(function (): void {
    Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
    Route::get('audit/{auditLog}', [AuditController::class, 'show'])->name('audit.show');
});

Route::middleware(['auth', 'active.account', 'permission:admin.dashboard.view'])->group(function (): void {
    Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics');
});
Route::middleware(['auth', 'active.account', 'permission:analytics.export'])->group(function (): void {
    Route::get('analytics/export', [AnalyticsController::class, 'export'])->middleware('throttle:5,1')->name('analytics.export');
});

Route::middleware(['auth', 'active.account', 'permission:admin.professionals.view'])->group(function (): void {
    Route::get('verification', [VerificationController::class, 'index'])->name('verification.index');
    Route::get('verification/{professional}', [VerificationController::class, 'show'])->name('verification.show');
});

Route::middleware(['auth', 'active.account', 'permission:reports.manage'])->group(function (): void {
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::post('reports/{report}/assign', [ReportController::class, 'assign'])->name('reports.assign');
    Route::post('reports/{report}/start-review', [ReportController::class, 'startReview'])->name('reports.start-review');
    Route::post('reports/{report}/moderate', [ReportController::class, 'moderate'])->name('reports.moderate');
    Route::post('reports/{report}/resolve', [ReportController::class, 'resolve'])->name('reports.resolve');
    Route::post('reports/{report}/dismiss', [ReportController::class, 'dismiss'])->name('reports.dismiss');
});

// Existing moderation and dispute workflows from main-proxiwork.
Route::middleware(['auth', 'active.account', 'permission:disputes.view'])->group(function (): void {
    Route::get('disputes', [OrderDisputeController::class, 'index'])->name('disputes.index');
    Route::get('disputes/{dispute}', [OrderDisputeController::class, 'show'])->name('disputes.show');
    Route::get('disputes/{dispute}/attachments/{attachment}', [OrderDisputeController::class, 'downloadAttachment'])->name('disputes.attachments.download');
});
Route::middleware(['auth', 'active.account', 'permission:disputes.manage'])->group(function (): void {
    Route::post('disputes/{dispute}/assign', [OrderDisputeController::class, 'assign'])->name('disputes.assign');
    Route::post('disputes/{dispute}/transition', [OrderDisputeController::class, 'transition'])->name('disputes.transition');
    Route::post('disputes/{dispute}/reopen', [OrderDisputeController::class, 'reopen'])->name('disputes.reopen');
    Route::post('disputes/{dispute}/message', [OrderDisputeController::class, 'message'])->name('disputes.message');
});
Route::middleware(['auth', 'active.account', 'permission:disputes.decide'])->group(function (): void {
    Route::post('disputes/{dispute}/decide', [OrderDisputeController::class, 'decide'])->name('disputes.decide');
});
Route::middleware(['auth', 'active.account', 'permission:reviews.moderate'])->group(function (): void {
    Route::get('moderation/reviews', [ModerationCenterController::class, 'reviews'])->name('moderation.reviews');
    Route::post('moderation/reviews/{review}', [ModerationCenterController::class, 'moderateReview'])->name('moderation.reviews.update');
    Route::post('moderation/review-responses/{response}', [ModerationCenterController::class, 'moderateResponse'])->name('moderation.responses.update');
});
Route::middleware(['auth', 'active.account', 'permission:moderation.history.view'])->group(function (): void {
    Route::get('moderation/history', [ModerationCenterController::class, 'history'])->name('moderation.history');
    Route::get('moderation/resolutions', [ModerationCenterController::class, 'resolutions'])->name('moderation.resolutions');
});


// Phase 7 — supervision du centre de notifications et de son historique de livraison.

Route::middleware(['auth', 'active.account', 'permission:admin.notifications.view'])->group(function (): void {
    Route::get('notifications/deliveries', [NotificationDeliveryController::class, 'index'])->name('notifications.deliveries.index');
    Route::get('notifications/deliveries/{delivery}', [NotificationDeliveryController::class, 'show'])->whereNumber('delivery')->name('notifications.deliveries.show');
});
