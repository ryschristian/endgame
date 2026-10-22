<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Http\Controllers\AccountAdministrationController;

Route::inertia('/', 'welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:login-ip')->name('login.store');
    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'store'])->middleware('throttle:account-notifications')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:account-tokens')->name('password.update');
    Route::get('/invitations/{token}', [InvitationAcceptanceController::class, 'show'])->middleware('throttle:account-tokens')->name('invitations.show');
    Route::post('/invitations/{token}', [InvitationAcceptanceController::class, 'store'])->middleware('throttle:account-tokens')->name('invitations.accept');
});

Route::middleware(['auth', 'can:manage-accounts'])->prefix('admin')->group(function () {
    Route::get('/accounts', [AccountAdministrationController::class, 'index'])->name('accounts.index');
    Route::middleware('throttle:account-management')->group(function () {
        Route::post('/invitations', [AccountAdministrationController::class, 'invite'])->name('invitations.store');
        Route::post('/invitations/{invitation}/resend', [AccountAdministrationController::class, 'resend'])->name('invitations.resend');
        Route::post('/invitations/{invitation}/revoke', [AccountAdministrationController::class, 'revoke'])->name('invitations.revoke');
        Route::patch('/accounts/{user}/status', [AccountAdministrationController::class, 'status'])->name('accounts.status');
        Route::patch('/accounts/{user}/administrator', [AccountAdministrationController::class, 'administrator'])->name('accounts.administrator');
    });
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::patch('/account', [AccountController::class, 'update'])->middleware('throttle:account-management')->name('account.update');
    Route::put('/account/password', [AccountController::class, 'password'])->middleware('throttle:account-management')->name('account.password');
});
