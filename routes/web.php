
<?php

use App\Http\Controllers\BuildOrderController;
use App\Http\Controllers\AllReplaysController;
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

Route::get('/replays', [AllReplaysController::class, 'index'])->name('replays.all');

Route::get('/player/{user}', [ReplayController::class, 'displayPlayer'])
  ->name('player');

Route::middleware('auth')->group(function () {
  Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
  Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
  Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
  Route::get('/profile/maps/download/{filename}', [ProfileController::class, 'downloadMap'])->where('filename', 'OP SFL-\\.scm|SFLClan\\.scm')->name('maps.download');
});


Route::middleware('auth')->group(function () {
  Route::get('/upload', [ReplayController::class, 'index'])->name('upload.index');
  Route::post('/upload', [ReplayController::class, 'upload'])->name('upload.upload');
  Route::get('/replay/download/{uuid}', [ReplayController::class, 'download'])->name('replay.download');
});

Route::get('/builds', [BuildOrderController::class, 'index'])->name('builds.index');
Route::get('/builds/create', [BuildOrderController::class, 'create'])->name('builds.create');
Route::post('/builds', [BuildOrderController::class, 'store'])->name('builds.store');
Route::get('/builds/{id}', [BuildOrderController::class, 'show'])->name('builds.show');
Route::get('/builds/{id}/edit', [BuildOrderController::class, 'edit'])->name('builds.edit');
Route::put('/builds/{id}', [BuildOrderController::class, 'update'])->name('builds.update');
Route::delete('/builds/{id}', [BuildOrderController::class, 'destroy'])->name('builds.destroy');

require __DIR__ . '/auth.php';
