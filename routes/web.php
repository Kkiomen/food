<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::resource('scrap-recipes', \App\Http\Controllers\ScrapRecipeController::class)
        ->only(['index', 'show', 'edit', 'update', 'destroy']);

    Route::resource('scrap-categories', \App\Http\Controllers\ScrapCategoryController::class)
        ->only(['index', 'show', 'edit', 'update', 'destroy']);
});


Route::get('/test', [\App\Http\Controllers\TestController::class, 'index']);


require __DIR__.'/settings.php';
