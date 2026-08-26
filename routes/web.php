<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AttractionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoutePlanningController;
use App\Http\Controllers\SavedPlaceController;
use App\Http\Controllers\TravelPreferenceController;
use App\Http\Controllers\TransportController;
use App\Http\Controllers\Admin\AttractionController as AdminAttractionController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\GreenRewardController;


Route::get('/', function () {
	return redirect('/login');
});

Route::get('/login', [AuthController::class, 'login'])->name('login');

Route::post('/google-login', [AuthController::class, 'googleLogin']);

Route::get('/dashboard', function () {
	$user = auth()->user();
	$preferences = collect();
	if ($user->personalisation_consent) {
		$preferences = $user->preferenceCategories()->orderBy('category_name')->get();
	}
	return view('dashboard.index', compact('user', 'preferences'));
})->middleware('auth')->name('dashboard');

Route::get('/trips', function () {
    return view('trips.index');
})->middleware('auth')->name('trips.index');

Route::get('/rewards', [GreenRewardController::class, 'index'])
    ->middleware('auth')->name('rewards');

Route::post('/logout', function (Request $request) {
	auth()->logout();
	$request->session()->invalidate();
	$request->session()->regenerateToken();

	return redirect('/login');
})->middleware('auth')->name('logout');

Route::get('/profile', [ProfileController::class, 'show'])
	->middleware('auth')->name('profile');

Route::post('/profile/update',[ProfileController::class,'update'])
	->middleware('auth')
	->name('profile.update');

Route::delete('/profile', [ProfileController::class, 'destroy'])
	->middleware('auth')->name('profile.destroy');

Route::middleware('auth')->group(function () {
    Route::post('/rewards/daily-login', [GreenRewardController::class, 'dailyLogin'])->name('rewards.daily-login');
    Route::post('/rewards/shop/{item}/purchase', [GreenRewardController::class, 'purchase'])->name('rewards.purchase');
    Route::post('/rewards/inventory/{inventory}/fertilize', [GreenRewardController::class, 'fertilize'])->name('rewards.fertilize');
    Route::post('/rewards/activity', [GreenRewardController::class, 'activity'])->name('rewards.activity');
    Route::post('/rewards/activity/collect', [GreenRewardController::class, 'collectActivity'])->name('rewards.activity.collect');
    Route::post('/rewards/achievements/{achievement}/collect', [GreenRewardController::class, 'collectAchievement'])->name('rewards.achievements.collect');
    Route::get('/route-planning', [RoutePlanningController::class, 'index'])
        ->name('route.index');

    Route::post('/route-preference', [RoutePlanningController::class, 'storePreference'])
        ->name('route.preference');

    Route::get('/transportation', [TransportController::class, 'index'])
        ->name('transportation');

    Route::get('/transport', [TransportController::class, 'index'])
        ->name('transport.index');
    Route::match(['get', 'post'], '/transport/search', [TransportController::class, 'search'])
        ->name('transport.search');
    Route::get('/transport/walking-directions', [TransportController::class, 'walkingDirections'])
        ->name('transport.walking');
    Route::match(['get', 'post'], '/transport/nearby', [TransportController::class, 'nearbyStations'])
        ->name('transport.nearby');
    Route::get('/transport/station/{placeId}', [TransportController::class, 'stationDetails'])
        ->name('transport.station-details');
    Route::get('/transport/line-info', [TransportController::class, 'lineInfo'])
        ->name('transport.line-info');

    Route::view('/about-malaysia', 'about-malaysia')->name('about-malaysia');

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

    Route::post('/saved-places/collections', [SavedPlaceController::class, 'storeCollection'])
        ->name('saved-places.collections.store');

    Route::post('/saved-places/collections/{collection}/places', [SavedPlaceController::class, 'addToCollection'])
        ->name('saved-places.collections.places.store');

    Route::get('/travel-preferences', [TravelPreferenceController::class, 'edit'])
        ->name('travel-preferences.edit');

    Route::post('/travel-preferences', [TravelPreferenceController::class, 'update'])
        ->name('travel-preferences.update');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'login'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'authenticate'])
        ->middleware('throttle:5,1')->name('authenticate');

    Route::middleware('admin')->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
        Route::resource('attractions', AdminAttractionController::class)->except('show');
    });
});
