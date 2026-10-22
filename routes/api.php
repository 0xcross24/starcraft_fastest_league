<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\StatsApiController;

Route::get('/users', [StatsApiController::class, 'userStats'])->middleware('throttle:60,1');
