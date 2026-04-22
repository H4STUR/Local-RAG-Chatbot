<?php

use Illuminate\Support\Facades\Route;

// Page views only — all data lives in routes/api.php
Route::get('/', fn () => view('welcome'));
Route::get('/chat', fn () => view('chat'));
Route::get('/ask-test', fn () => view('ask-test'));
Route::get('/upload', fn () => view('upload'));
