<?php

use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/', [WorkspaceController::class, 'index'])->name('workspace');
Route::post('/documents', [WorkspaceController::class, 'store'])->name('documents.store');
Route::patch('/documents/{document}', [WorkspaceController::class, 'update'])->name('documents.update');
Route::patch('/documents/{document}/rename', [WorkspaceController::class, 'rename'])->name('documents.rename');
Route::delete('/documents/{document}', [WorkspaceController::class, 'destroy'])->name('documents.destroy');
Route::post('/documents/{document}/share', [WorkspaceController::class, 'share'])->name('documents.share');
Route::get('/shared/{token}', [WorkspaceController::class, 'publicDocument'])->name('documents.public');
Route::post('/import', [WorkspaceController::class, 'import'])->name('documents.import');
Route::post('/switch-user', [WorkspaceController::class, 'switchUser'])->name('users.switch');
