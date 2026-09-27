<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LoginPopupController;
use App\Http\Controllers\DataDaerahController;
use App\Http\Controllers\FruitCalculatorController;
use App\Http\Controllers\PurchaseOrderController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'authenticate']);
});

Route::get('/verify/po/{purchaseOrder}', [DashboardController::class, 'verifyPurchaseOrder'])
    ->middleware('signed:relative')
    ->name('purchase-orders.verify');
Route::get('/verify/fee-mitra', [DashboardController::class, 'verifyPartnerFee'])
    ->middleware('signed:relative')
    ->name('partner-fee.verify');
Route::get('/verify/payment/{type}', [DashboardController::class, 'verifyPayment'])
    ->middleware('signed:relative')
    ->name('payment.verify');

Route::middleware(['auth'])->group(function () {
    Route::prefix('dashboard/fruit-calculator')->name('fruit-calculator.')->group(function () {
        Route::get('/', [FruitCalculatorController::class, 'index'])->name('index');
        Route::post('/fruits/bulk', [FruitCalculatorController::class, 'storeMany']);
        Route::post('/fruits', [FruitCalculatorController::class, 'store']);
        Route::put('/fruits/{fruit}', [FruitCalculatorController::class, 'update']);
        Route::get('/fruits/{fruit}/history', [FruitCalculatorController::class, 'history']);
        Route::get('/decisions', [FruitCalculatorController::class, 'decisions']);
        Route::post('/decisions', [FruitCalculatorController::class, 'decide']);
        Route::get('/decisions/{decision}', [FruitCalculatorController::class, 'showDecision']);
    });
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/users/{user}/login-popup', [LoginPopupController::class, 'update'])->name('dashboard.login-popup.update');
    Route::post('/dashboard/users/{user}/login-popup/toggle', [LoginPopupController::class, 'toggle'])->name('dashboard.login-popup.toggle');
    Route::post('/dashboard', [DashboardController::class, 'index']);
    Route::put('/admin/region/{id}', [DashboardController::class, 'updateRegion']);    
    Route::put('/admin/account/{account}', [DashboardController::class, 'updateManagedAccount'])
        ->name('admin.account.update');
    Route::delete('/admin/account/{account}', [DashboardController::class, 'destroyManagedAccount'])
        ->name('admin.account.destroy');
    Route::put('/account/password', [DashboardController::class, 'updateOwnPassword'])
        ->name('account.password.update');
    Route::delete('/region/{id}', [DashboardController::class, 'destroyRegion'])->name('admin.region.destroy');
    Route::get('/dashboard/analisa-keuntungan', [DashboardController::class, 'getAnalisaKeuntungan'])->name('dashboard.analisa');
    Route::get('/dashboard/reset', [DashboardController::class, 'resetFilter'])->name('dashboard.reset');
    Route::post('/dashboard/po', [DashboardController::class, 'store'])->name('dashboard.po.store');
    Route::get('/dashboard/po/{purchaseOrder}', [DashboardController::class, 'show'])->name('dashboard.po.show');
    Route::put('/dashboard/po/{purchaseOrder}', [DashboardController::class, 'update'])->name('dashboard.po.update');
    Route::delete('/dashboard/po/{purchaseOrder}', [DashboardController::class, 'destroy'])->name('dashboard.po.destroy');

    // Route Status Pembayaran & Cetak Nota Dapur
    Route::patch('/dashboard/po/{purchaseOrder}/toggle-payment', [DashboardController::class, 'togglePaymentStatus'])
        ->name('dashboard.po.toggle-payment');

    Route::get('/dashboard/po/{purchaseOrder}/receipt', [DashboardController::class, 'printKitchenReceipt'])
        ->name('dashboard.po.receipt');
    Route::post('/dashboard/fee-mitra/verification-link', [DashboardController::class, 'createPartnerFeeVerificationLink'])
        ->name('dashboard.partner-fee.verification-link');
    Route::post('/dashboard/payment/verification-link', [DashboardController::class, 'createPaymentVerificationLink'])
        ->name('dashboard.payment.verification-link');
    Route::post('/dashboard/fee-mitra/pay-all', [DashboardController::class, 'payAllPartnerFees'])
        ->name('dashboard.partner-fee.pay-all');
    Route::post('/dashboard/supplier/pay-all', [DashboardController::class, 'payAllSupplierPayments'])
        ->name('dashboard.supplier.pay-all');
    Route::post('/dashboard/fee-ksppg/pay-all', [DashboardController::class, 'payAllKsppgFees'])
        ->name('dashboard.ksppg-fee.pay-all');
    Route::post('/dashboard/admin-region', [DashboardController::class, 'storeAdminAndRegion'])
        ->name('dashboard.admin-region.store');
});

require __DIR__.'/auth.php';
