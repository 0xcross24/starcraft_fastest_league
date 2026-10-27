<?php

use App\Http\Controllers\Admin\ReplayController as AdminReplayController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AllReplaysController;
use App\Http\Controllers\PlayerNameController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReplayController;
use App\Http\Controllers\PlayerSearchController;
use App\Http\Controllers\ReplayZipController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\BuildOrderController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('homepage');

Route::get('/rules', function () {
  return view('rules');
});

Route::get('/rankings', [StatsController::class, 'displayAllRanking'])->name('rankings');

Route::get('/replays', [AllReplaysController::class, 'index'])->name('replays.all');

Route::get('/players/search', [PlayerSearchController::class, 'index'])
  ->middleware('throttle:60,1')
  ->name('players.search');

Route::get('/player/{user}', [ReplayController::class, 'displayPlayer'])
  ->name('player');

Route::middleware('auth')->group(function () {
  Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
  Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
  Route::post('/profile/names', [PlayerNameController::class, 'store'])->name('player-names.store');
  Route::delete('/profile/names/{playerName}', [PlayerNameController::class, 'destroy'])->name('player-names.destroy');
  Route::get('/profile/maps/download/{filename}', [ProfileController::class, 'downloadMap'])->where('filename', 'OP SFL-\\.scm|SFLClan\\.scm|WkClan  Styler 빠른무한\(Ob\)\\.scm')->name('maps.download');
});


Route::middleware('auth')->group(function () {
  Route::get('/upload', [ReplayController::class, 'index'])->name('upload.index');
  Route::post('/upload', [ReplayController::class, 'upload'])->middleware('throttle:30,10')->name('upload.upload');
  Route::get('/replay/download/{uuid}', [ReplayController::class, 'download'])->name('replay.download');
  Route::get('/replays/download', [ReplayZipController::class, 'download'])->middleware('throttle:5,10')->name('replays.download-zip');
});

// Admin portal
// password.confirm: re-enter the password before using the portal (valid 3 hours),
// so a hijacked or left-open session cannot change roles or delete accounts.
Route::middleware(['auth', 'admin', 'password.confirm'])->prefix('admin')->name('admin.')->group(function () {
  Route::redirect('/', '/admin/users')->name('index');
  Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
  Route::get('/users/{user}', [AdminUserController::class, 'edit'])->name('users.edit');
  Route::patch('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
  Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
  Route::post('/users/{user}/names', [AdminUserController::class, 'addName'])->name('users.names.store');
  Route::delete('/users/{user}/names/{playerName}', [AdminUserController::class, 'removeName'])->scopeBindings()->name('users.names.destroy');
  Route::post('/users/{user}/password-reset', [AdminUserController::class, 'sendPasswordReset'])->name('users.password-reset');
  Route::get('/replays', [AdminReplayController::class, 'index'])->name('replays.index');
  Route::post('/replays/{replayId}/invalidate', [AdminReplayController::class, 'invalidate'])->name('replays.invalidate');
});

// Build Order routes
Route::middleware(['auth', 'admin'])->group(function () {
  Route::get('/builds/create', [BuildOrderController::class, 'create'])->name('builds.create');
  Route::post('/builds', [BuildOrderController::class, 'store'])->name('builds.store');
  Route::get('/builds/{id}/edit', [BuildOrderController::class, 'edit'])->name('builds.edit');
  Route::put('/builds/{id}', [BuildOrderController::class, 'update'])->name('builds.update');
  Route::delete('/builds/{id}', [BuildOrderController::class, 'destroy'])->name('builds.destroy');
});

Route::get('/builds', [BuildOrderController::class, 'index'])->name('builds.index');
Route::get('/builds/{id}', [BuildOrderController::class, 'show'])->name('builds.show');

require __DIR__ . '/auth.php';
