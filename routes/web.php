<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SavedPlaceController;
use App\Http\Controllers\TravelPreferenceController;
use App\Http\Controllers\AttractionController;

Route::get('/', function () {
    return redirect('/login');
});

Route::get('/login', [AuthController::class, 'login'])
    ->name('login');

Route::post('/google-login', [AuthController::class, 'googleLogin']);

Route::get('/dashboard', function () {
    return view('dashboard.index');
})->middleware('auth')->name('dashboard');

Route::post('/logout', function (Request $request) {
    auth()->logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/login');
})->middleware('auth')->name('logout');

Route::get('/profile', function () {
    return view('profile');
})->middleware('auth')->name('profile');

Route::post('/profile/update', [ProfileController::class, 'update'])
    ->middleware('auth')
    ->name('profile.update');

Route::middleware('auth')->group(function () {

    Route::get('/attractions', [AttractionController::class, 'index'])
        ->name('attractions.index');

    Route::get('/attractions/{id}', [AttractionController::class, 'show'])
        ->name('attractions.show');

    Route::post('/attractions/{id}/wishlist', [AttractionController::class, 'addToWishlist'])
        ->name('attractions.wishlist.add');

    Route::delete('/attractions/{id}/wishlist', [AttractionController::class, 'removeFromWishlist'])
        ->name('attractions.wishlist.remove');

    Route::get('/saved-places', [SavedPlaceController::class, 'index'])
        ->name('saved-places.index');

    Route::get('/travel-preferences', [TravelPreferenceController::class, 'edit'])
        ->name('travel-preferences.edit');

    Route::post('/travel-preferences', [TravelPreferenceController::class, 'update'])
        ->name('travel-preferences.update');

});