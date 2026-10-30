<?php

use App\Http\Controllers\AccountSecurityController;
use App\Http\Controllers\TwoFactorChallengeController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'show'])->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'verify'])->name('two-factor.verify');
    Route::post('/two-factor-challenge/cancel', [TwoFactorChallengeController::class, 'cancel'])->name('two-factor.cancel');
});
Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::delete('/settings/account/sessions', [AccountSecurityController::class, 'sessionsRevoke'])->name('account.sessions.revoke');
    Route::post('/settings/account/photo', [AccountSecurityController::class, 'photo'])->name('account.photo.update');
    Route::get('/settings/account/avatar', [AccountSecurityController::class, 'avatar'])->name('account.avatar');
    Route::get('/settings/account', [AccountSecurityController::class, 'settings'])->name('account.settings');
    Route::delete('/settings/account/totp', [AccountSecurityController::class, 'totpDisable'])->name('account.totp.disable');
    Route::post('/settings/account/recovery-codes', [AccountSecurityController::class, 'recoveryRegenerate'])->name('account.recovery.regenerate');
    Route::post('/settings/account/totp/setup', [AccountSecurityController::class, 'totpSetup'])->name('account.totp.setup');
    Route::post('/settings/account/totp/confirm', [AccountSecurityController::class, 'totpConfirm'])->name('account.totp.confirm');
    Route::post('/settings/account/pin', [AccountSecurityController::class, 'pin'])->name('account.pin.enable');
    Route::put('/settings/account/pin', [AccountSecurityController::class, 'pin'])->name('account.pin.change');
    Route::delete('/settings/account/pin', [AccountSecurityController::class, 'pin'])->name('account.pin.disable');
    Route::post('/settings/account/pin/verify', [AccountSecurityController::class, 'pin'])->name('account.pin.verify');
    Route::patch('/settings/account/profile', [AccountSecurityController::class, 'profile'])->name('account.profile.update');
});
