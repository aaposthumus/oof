<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Decoy: the real admin panel lives elsewhere, so /admin must point nowhere.
Route::any('/admin/{path?}', fn () => abort(404))->where('path', '.*');
