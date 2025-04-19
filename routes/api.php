<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/pull-requests/{owner}/{repo}', [\App\Http\Controllers\PullRequests::class, 'index'])->name('pr');
Route::get('/reviews/{owner}/{repo}', [\App\Http\Controllers\Reviews::class, 'index'])->name('reviews');
Route::get('/commits/{owner}/{repo}', [\App\Http\Controllers\Commits::class, 'index'])->name('commits');
