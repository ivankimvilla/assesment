<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/shared/{token}', [WorkspaceController::class, 'publicDocument'])->name('documents.public');
Route::middleware('guest')->group(function () {
	Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
	Route::post('/login', [AuthController::class, 'login'])->name('login.store');
	Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
	Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

Route::middleware('auth')->group(function () {
	Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
	Route::get('/', [WorkspaceController::class, 'index'])->name('workspace');
	Route::post('/documents', [WorkspaceController::class, 'store'])->name('documents.store');
	Route::patch('/documents/{document}', [WorkspaceController::class, 'update'])->name('documents.update');
	Route::patch('/documents/{document}/rename', [WorkspaceController::class, 'rename'])->name('documents.rename');
	Route::delete('/documents/{document}', [WorkspaceController::class, 'destroy'])->name('documents.destroy');
	Route::post('/documents/{document}/share', [WorkspaceController::class, 'share'])->name('documents.share');
	Route::post('/import', [WorkspaceController::class, 'import'])->name('documents.import');
});
