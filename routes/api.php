<?php

use App\Http\Controllers\RagController;
use Illuminate\Support\Facades\Route;

Route::post('/ask', [RagController::class, 'ask']);
