<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/pull-requests/{owner}/{repo}',[\App\Http\Controllers\PullRequests::class,"index"] )->name('pr');
