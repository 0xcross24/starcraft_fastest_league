<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('homepage');
});

Route::get('/rankings', function() {
  return view('rankings', [
    'rankings' => [
      'player1' => [
        'rank'   => '1',
        'league' => 'S',
        'player' => 'player1',
        'points' => '2150',
        'record' => '40-0',
        'country' => 'Canada', 
      ],
      'player2' => [
        'rank'   => '2',
        'league' => 'A',
        'player' => 'player2',
        'points' => '2000',
        'record' => '35-5',
        'country' => 'Canada', 
      ],
      'player3' => [
        'rank'   => '3',
        'league' => 'B',
        'player' => 'player3',
        'points' => '1950',
        'record' => '30-10',
        'country' => 'United States', 
      ] 
    ]
  ]);
});

Route::get('/rules', function () {
    return view('rules', [
      'accounts' => Account::all()
    ]);
});

Route::get('/forum', function () {
    return view('forum');
});

Route::get('/replays', function () {
    return view('replays');
});

Route::get('/seasons', function () {
  return view('seasons');
});

Route::get('/streams', function () {
  return view('streams');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
