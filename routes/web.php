<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TripController;


Route::get('/', function () {
	return redirect('/login');
});

Route::get('/login', [AuthController::class, 'login'])->name('login');

Route::post('/google-login', [AuthController::class, 'googleLogin']);

Route::get('/dashboard', function () {
	return view('dashboard.index');
})->middleware('auth');

Route::get('/trips', function () {
    return view('trips.index');
})->middleware('auth');

Route::post('/logout', function () {
	Auth::logout();

	return redirect('/login');
});

Route::get('/profile', function () {
	return view('profile.index');
})->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/trips', [TripController::class, 'index'])->name('trips.index');

    Route::get('/itineraries/create', [TripController::class, 'create'])->name('itineraries.create');
    Route::post('/itineraries', [TripController::class, 'store'])->name('itineraries.store');
    Route::get('/itineraries/{trip}', [TripController::class, 'show'])->name('itineraries.show');
});