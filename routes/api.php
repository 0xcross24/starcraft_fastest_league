<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\StatsApiController;

Route::get('/', function (Request $request) {
    return response()->json(['message' => 'API root']);
});

Route::get('/users', [StatsApiController::class, 'userStats']);
