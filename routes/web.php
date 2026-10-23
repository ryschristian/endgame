<?php

use App\Http\Controllers\AccountAdministrationController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectInvitationController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\ProjectRoleController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

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
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('/projects/{project}/members', [ProjectMemberController::class, 'index'])->name('projects.members');
    Route::get('/projects/{project}/roles', [ProjectRoleController::class, 'index'])->name('projects.roles');
    Route::get('/project-invitations/{token}', [ProjectInvitationController::class, 'show'])->middleware('throttle:account-tokens')->name('project-invitations.show');
    Route::middleware('throttle:account-management')->group(function () {
        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::patch('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::post('/projects/{project}/archive', [ProjectController::class, 'archive'])->name('projects.archive');
        Route::post('/projects/{project}/restore', [ProjectController::class, 'restore'])->name('projects.restore');
        Route::post('/projects/{project}/members', [ProjectMemberController::class, 'store'])->name('projects.members.store');
        Route::patch('/projects/{project}/members/{member}', [ProjectMemberController::class, 'update'])->name('projects.members.update');
        Route::delete('/projects/{project}/members/{member}', [ProjectMemberController::class, 'destroy'])->name('projects.members.destroy');
        Route::post('/projects/{project}/roles', [ProjectRoleController::class, 'store'])->name('projects.roles.store');
        Route::patch('/projects/{project}/roles/{role}', [ProjectRoleController::class, 'update'])->name('projects.roles.update');
        Route::delete('/projects/{project}/roles/{role}', [ProjectRoleController::class, 'destroy'])->name('projects.roles.destroy');
        Route::post('/projects/{project}/invitations', [ProjectInvitationController::class, 'store'])->name('projects.invitations.store');
        Route::post('/projects/{project}/invitations/{invitation}/resend', [ProjectInvitationController::class, 'resend'])->name('projects.invitations.resend');
        Route::post('/projects/{project}/invitations/{invitation}/revoke', [ProjectInvitationController::class, 'revoke'])->name('projects.invitations.revoke');
        Route::post('/project-invitations/{token}', [ProjectInvitationController::class, 'accept'])->name('project-invitations.accept');
    });
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::patch('/account', [AccountController::class, 'update'])->middleware('throttle:account-management')->name('account.update');
    Route::put('/account/password', [AccountController::class, 'password'])->middleware('throttle:account-management')->name('account.password');
});
