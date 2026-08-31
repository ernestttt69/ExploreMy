<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ItineraryController;

Route::get('/', function () {
	return redirect('/login');
});

Route::get('/login', [AuthController::class, 'login'])->name('login');

Route::post('/google-login', [AuthController::class, 'googleLogin']);

Route::get('/dashboard', function () {
	return view('dashboard.index');
})->middleware('auth');

Route::middleware('auth')->group(function () {
	Route::get('/trips', [ItineraryController::class, 'index'])->name('itineraries.index');
	Route::post('/trips', [ItineraryController::class, 'store'])->name('itineraries.store');
	Route::get('/trips/{trip}', [ItineraryController::class, 'show'])->name('itineraries.show');
	Route::post('/trips/{trip}/items', [ItineraryController::class, 'storeItem'])->name('itineraries.items.store');
	Route::put('/trips/{trip}/items/{item}', [ItineraryController::class, 'updateItem'])->name('itineraries.items.update');
	Route::delete('/trips/{trip}/items/{item}', [ItineraryController::class, 'destroyItem'])->name('itineraries.items.destroy');
	Route::post('/trips/{trip}/items/reorder', [ItineraryController::class, 'reorder'])->name('itineraries.items.reorder');
	Route::post('/trips/{trip}/items/{item}/eco-alternative', [ItineraryController::class, 'applyEcoAlternative'])->name('itineraries.items.eco');
	Route::post('/trips/{trip}/save', [ItineraryController::class, 'save'])->name('itineraries.save');
	Route::post('/trips/{trip}/sync', [ItineraryController::class, 'sync'])->name('itineraries.sync');
	Route::post('/trips/{trip}/share', [ItineraryController::class, 'share'])->name('itineraries.share');
	Route::get('/trips/{trip}/export/pdf', [ItineraryController::class, 'exportPdf'])->name('itineraries.export.pdf');
	Route::get('/trips/{trip}/export/calendar', [ItineraryController::class, 'exportCalendar'])->name('itineraries.export.calendar');
});

Route::get('/shared/itineraries/{token}', [ItineraryController::class, 'shared'])->name('itineraries.shared');
Route::post('/shared/itineraries/{token}/items', [ItineraryController::class, 'sharedStoreItem'])->name('itineraries.shared.items.store');
Route::put('/shared/itineraries/{token}/items/{item}', [ItineraryController::class, 'sharedUpdateItem'])->name('itineraries.shared.items.update');
Route::delete('/shared/itineraries/{token}/items/{item}', [ItineraryController::class, 'sharedDestroyItem'])->name('itineraries.shared.items.destroy');
Route::post('/shared/itineraries/{token}/items/{item}/eco-alternative', [ItineraryController::class, 'sharedApplyEcoAlternative'])->name('itineraries.shared.items.eco');

Route::post('/logout', function () {
	Auth::logout();

	return redirect('/login');
});

Route::get('/profile', function () {
	return view('profile.index');
})->middleware('auth');
