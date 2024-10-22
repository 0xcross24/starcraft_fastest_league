<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\FileUploadController;
use App\Http\Controllers\ReplayController;
use App\Http\Controllers\StatsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('homepage');
});

Route::get('/rules', function() {
  return view('rules');
});

Route::get('/forum', function () {
    return view('forum');
});

Route::get('/rankings', function () {
  return view('rankings');
});

Route::get('/seasons', function () {
  return view('seasons');
});

Route::get('/streams', function () {
  return view('streams');
});

Route::get('/dashboard', [ReplayController::class, 'displayAll'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


Route::middleware('auth')->group(function() {
  Route::get('/replays', [ReplayController::class, 'index'])->name('replays.index');
  Route::post('/replays', [ReplayController::class, 'upload'])->name('replays.upload');
  Route::get('/replays/{uuid}', [ReplayController::class, 'display'])->name('replays.results');
});

require __DIR__.'/auth.php';
