<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
// Keeping only the default web root; this project is API-first so we avoid
// adding web authentication routes here.
