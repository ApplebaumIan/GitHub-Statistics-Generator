<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/pull-requests/{owner}/{repo}', [\App\Http\Controllers\PullRequests::class, 'image'])->name('pr');
Route::get('/pull-requests/{owner}/{repo}/mermaid', [\App\Http\Controllers\PullRequests::class, 'mermaid_text'])->name('mpr');
Route::get('/reviews/{owner}/{repo}', [\App\Http\Controllers\Reviews::class, 'image'])->name('reviews');
Route::get('/reviews/{owner}/{repo}/mermaid', [\App\Http\Controllers\Reviews::class, 'mermaid_text'])->name('mreviews');
Route::get('/commits/{owner}/{repo}', [\App\Http\Controllers\Commits::class, 'image'])->name('commits');
Route::get('/commits/{owner}/{repo}/mermaid', [\App\Http\Controllers\Commits::class, 'mermaid_text'])->name('mcommits');
