<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\StatsApiController;
use App\Http\Controllers\DiscordInteractionController;

Route::get('/users', [StatsApiController::class, 'userStats'])->middleware('throttle:60,1');

// Button clicks on the live Discord rankings message (signed by Discord)
Route::post('/discord/interactions', DiscordInteractionController::class)
    ->middleware('throttle:120,1')
    ->name('discord.interactions');
