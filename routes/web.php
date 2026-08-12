<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RecommendationController;

// Set default homepage '/' AND '/recommendations' to public view
Route::get('/', [RecommendationController::class, 'index'])->name('recommendations.index');
Route::get('/recommendations', [RecommendationController::class, 'index']);
Route::get('/attractions/{id}', [RecommendationController::class, 'show'])->name('attractions.show');

Route::get('/login', [AuthController::class, 'login'])->name('login');

Route::post('/google-login', [AuthController::class, 'googleLogin']);

Route::get('/dashboard', function () {
	return view('dashboard.index');
})->middleware('auth');

Route::get('/trips', function () {
    return view('index', compact('trips'));
})->middleware('auth');

Route::post('/logout', function () {
	Auth::logout();

	return redirect('/login');
});

Route::get('/profile', function () {
	return view('profile.index');
})->middleware('auth');