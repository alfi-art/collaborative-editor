<?php

use App\Models\document;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DocumentController;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        $documents = document::all();
        return view('dashboard', ['documents' => $documents]);
    })->name('dashboard');
    
    Route::post('/documents', [DocumentController::class, 'store']);
    Route::get('/documents/{id}/edit', [DocumentController::class, 'edit']);
    Route::put('/documents/{id}', [DocumentController::class, 'update']);
    Route::get('/documents/{id}/revisions', [DocumentController::class, 'getRevisions']);
    Route::post('/documents/{id}/rollback/{revisionId}', [DocumentController::class, 'rollback']);
    Route::post('/documents/{id}/cursor', [DocumentController::class, 'broadcastCursor']);
});

require __DIR__.'/auth.php';