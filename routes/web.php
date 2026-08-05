<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TransportController;

Route::get('/', function () {
    return redirect('/transport');
});


// Main Transport Route Planner View
Route::get('/transport', [TransportController::class, 'index'])->name('transport.index');

// Search Route Path
Route::match(['get', 'post'], '/transport/search', [TransportController::class, 'search'])->name('transport.search');

// New route for AJAX walking directions
Route::get('/transport/walking-directions', [TransportController::class, 'walkingDirections'])->name('transport.walking');

// View Nearby Stations
Route::match(['get', 'post'], '/transport/nearby', [TransportController::class, 'nearbyStations'])->name('transport.nearby');

// View Specific Station Details (AJAX Endpoint)
Route::get('/transport/station/{placeId}', [TransportController::class, 'stationDetails'])->name('transport.station-details');

// Check Transport Line Info
Route::get('/transport/line-info', [TransportController::class, 'lineInfo'])->name('transport.line-info');




