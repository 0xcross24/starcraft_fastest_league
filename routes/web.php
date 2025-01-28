<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\FileUploadController;
use App\Http\Controllers\ReplayController;
use App\Http\Controllers\StatsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
  return view('homepage');
})->name('homepage');

Route::get('/rules', function () {
  return view('rules');
});

Route::get('/rankings', [StatsController::class, 'displayAllRanking'])->name('rankings');

Route::get('/streams', function () {
  return view('streams');
});

Route::get('/player/{user}', [ReplayController::class, 'displayPlayer'])
  ->name('player');

Route::middleware('auth')->group(function () {
  Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
  Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
  Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


Route::middleware('auth')->group(function () {
  Route::get('/replays', [ReplayController::class, 'index'])->name('replays.index');
  Route::post('/replays', [ReplayController::class, 'upload'])->name('replays.upload');
  Route::get('/replays/{uuid}', [ReplayController::class, 'display'])->name('replays.results');
});

require __DIR__ . '/auth.php';
