<?php

use App\Http\Controllers\FileController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReplayController;
use App\Http\Controllers\StatsController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\HomeController;

Route::get('/', [HomeController::class, 'index'])->name('homepage');

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
  Route::get('/upload', [ReplayController::class, 'index'])->name('upload.index');
  Route::post('/upload', [ReplayController::class, 'upload'])->name('upload.upload');
  Route::get('upload/download/{uuid}', [ReplayController::class, 'download'])->name('upload.download');
});

require __DIR__ . '/auth.php';
