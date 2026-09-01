<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ItineraryController;
use App\Http\Controllers\ItineraryWeatherController;
use App\Http\Controllers\MalaysiaPlaceController;

Route::get('/', function () {
	return redirect('/login');
});

Route::get('/login', [AuthController::class, 'login'])->name('login');

Route::post('/google-login', [AuthController::class, 'googleLogin']);

Route::get('/dashboard', function () {
	return view('dashboard.index');
})->middleware('auth');

Route::middleware('auth')->group(function () {
	Route::get('/itinerary-weather', [ItineraryWeatherController::class, 'show'])->name('itinerary.weather');
	Route::get('/malaysia-places', [MalaysiaPlaceController::class, 'search'])->name('malaysia-places.search');
	Route::get('/trips', [ItineraryController::class, 'index'])->name('itineraries.index');
	Route::post('/trips', [ItineraryController::class, 'store'])->name('itineraries.store');
	Route::get('/trips/{trip}', [ItineraryController::class, 'show'])->name('itineraries.show');
	Route::post('/trips/{trip}/share', [ItineraryController::class, 'share'])->name('itineraries.share');
	Route::get('/trips/{trip}/export/pdf', [ItineraryController::class, 'exportPdf'])->name('itineraries.export.pdf');
	Route::get('/trips/{trip}/export/calendar', [ItineraryController::class, 'exportCalendar'])->name('itineraries.export.calendar');
});

Route::get('/shared/itineraries/{token}', [ItineraryController::class, 'shared'])->name('itineraries.shared');

Route::post('/logout', function () {
	Auth::logout();

	return redirect('/login');
});

Route::get('/profile', function () {
	return view('profile.index');
})->middleware('auth');
