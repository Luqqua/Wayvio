<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminSecurity\Http\Controllers\AdminMonitoringController;

Route::middleware(['web', 'auth', 'admin'])->group(function () {
    Route::get('/admin/monitoring/subscriptions', [AdminMonitoringController::class, 'subscription'])->name('admin.monitoring.subscriptions');
    Route::get('/admin/monitoring/analytics', [AdminMonitoringController::class, 'analytics'])->name('admin.monitoring.analytics');
    Route::get('/admin/monitoring/domains', [AdminMonitoringController::class, 'domains'])->name('admin.monitoring.domains');
    Route::get('/admin/monitoring/billing', [AdminMonitoringController::class, 'billing'])->name('admin.monitoring.billing');
    Route::get('/admin/monitoring/logs', [AdminMonitoringController::class, 'logs'])->name('admin.monitoring.logs');
});
