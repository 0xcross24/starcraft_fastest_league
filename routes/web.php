<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('homepage');
});

Route::get('/rules', function () {
    return view('rules');
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

Route::get('/login', function () {
    return view('login');
});