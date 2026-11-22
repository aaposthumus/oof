<?php

use App\Http\Controllers\StampArtController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Achievement art framed for the stamp window, for the CMS (admins only).
Route::get('/_nimda/stamps/{achievement:key}.png', [StampArtController::class, 'show'])->name('stamps.show');

// Decoy: the real admin panel lives elsewhere, so /admin must point nowhere.
Route::any('/admin/{path?}', fn () => abort(404))->where('path', '.*');
