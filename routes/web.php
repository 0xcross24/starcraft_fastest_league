<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('homepage');
});

Route::get('/rankings', function () {
    return view('rankings');
});


Route::get('/rules', function () {
    return view('rules');
});

Route::get('/streams', function () {
    return view('streams');
});