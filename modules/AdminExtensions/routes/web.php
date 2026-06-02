<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminExtensions\Http\Controllers\AdminDashboardController;

Route::middleware(['web', 'auth', 'admin-ext.admin', 'admin-ext.rate'])->group(function () {
    Route::get('/admin/subscriptions/overview', [AdminDashboardController::class, 'securityOverview'])->name('admin.subscriptions.overview');
    Route::get('/admin/users/list', [AdminDashboardController::class, 'listUsers'])->name('admin.users.list');
    Route::get('/admin/domains/list', [AdminDashboardController::class, 'listDomains'])->name('admin.domains.list');
    Route::get('/admin/webhooks/logs', [AdminDashboardController::class, 'listWebhookLogs'])->name('admin.webhooks.logs');
    Route::get('/admin/system/events', [AdminDashboardController::class, 'listSystemEvents'])->name('admin.system.events');
});
