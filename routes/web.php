<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AttractionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoutePlanningController;
use App\Http\Controllers\SavedPlaceController;
use App\Http\Controllers\TransportController;
use App\Http\Controllers\Admin\AttractionController as AdminAttractionController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\GreenRewardController;
use App\Http\Controllers\ItineraryController;
use App\Http\Controllers\ItineraryWeatherController;
use App\Http\Controllers\MalaysiaPlaceController;
use App\Http\Controllers\ChatbotController;

Route::get('/', [AttractionController::class, 'index'])->name('explore');
Route::post('/language', [\App\Http\Controllers\LanguageController::class, 'update'])->name('language.update');

Route::get('/attractions', [AttractionController::class, 'index'])
    ->name('attractions.index');
Route::get('/attractions/{id}', [AttractionController::class, 'show'])
    ->name('attractions.show');

Route::view('/about-malaysia', 'about-malaysia')->name('about-malaysia');

Route::get('/login', [AuthController::class, 'login'])->name('login');

Route::post('/google-login', [AuthController::class, 'googleLogin']);
Route::get('/setup', [\App\Http\Controllers\SetupController::class, 'show'])->middleware('auth')->name('setup.show');
Route::post('/setup', [\App\Http\Controllers\SetupController::class, 'store'])->middleware('auth')->name('setup.store');

Route::get('/dashboard', function () {
	/** @var \App\Models\User $user */
	$user = auth()->user();
    if ($user->setup_required) return redirect()->route('setup.show');

    $collection = \App\Models\SavedPlaceCollection::where('user_id', $user->user_id)
        ->withCount(['items' => fn ($query) => $query->whereHas('attraction')])
        ->latest()->first();
    $upcomingTrip = \App\Models\Trip::where('user_id', $user->user_id)
        ->whereNotNull('start_date')
        ->where(function ($query) {
            $query->whereDate('end_date', '>=', today())
                ->orWhere(function ($query) {
                    $query->whereNull('end_date')->whereDate('start_date', '>=', today());
                });
        })->orderBy('start_date')->first();

    return view('dashboard.index', compact('user', 'collection', 'upcomingTrip'));
})->middleware('auth')->name('dashboard');

Route::get('/rewards', [GreenRewardController::class, 'index'])
    ->middleware('auth')->name('rewards');

Route::post('/logout', function (Request $request) {
	auth()->logout();
	$request->session()->invalidate();
	$request->session()->regenerateToken();

	return redirect()->route('explore');
})->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
	Route::post('/chatbot/message', ChatbotController::class)
		->middleware('throttle:15,1')->name('chatbot.message');
	Route::get('/itinerary-weather', [ItineraryWeatherController::class, 'show'])->name('itinerary.weather');
	Route::get('/malaysia-places', [MalaysiaPlaceController::class, 'search'])->name('malaysia-places.search');
	Route::get('/trips', [ItineraryController::class, 'index'])->name('itineraries.index');
	Route::post('/trips', [ItineraryController::class, 'store'])->name('itineraries.store');
	Route::post('/trips/from-generated-route', [ItineraryController::class, 'storeGeneratedRoute'])->name('itineraries.store-generated-route');
	Route::get('/trips/{trip}', [ItineraryController::class, 'show'])->name('itineraries.show');
	Route::post('/trips/{trip}/share', [ItineraryController::class, 'share'])->name('itineraries.share');
	Route::get('/trips/{trip}/export/pdf', [ItineraryController::class, 'exportPdf'])->name('itineraries.export.pdf');
	Route::get('/trips/{trip}/export/calendar', [ItineraryController::class, 'exportCalendar'])->name('itineraries.export.calendar');
});

Route::get('/shared/itineraries/{token}', [ItineraryController::class, 'shared'])->name('itineraries.shared');

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
    Route::get('/route-planning/saved-places/search', [RoutePlanningController::class, 'searchSavedPlaces'])
        ->name('route.saved-places.search');

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

    Route::post('/attractions/{id}/wishlist', [AttractionController::class, 'addToWishlist'])
        ->name('attractions.wishlist.add');

    Route::delete('/attractions/{id}/wishlist', [AttractionController::class, 'removeFromWishlist'])
        ->name('attractions.wishlist.remove');

    Route::get('/saved-places', [SavedPlaceController::class, 'index'])
        ->name('saved-places.index');

    Route::post('/saved-places/collections', [SavedPlaceController::class, 'storeCollection'])
        ->name('saved-places.collections.store');

    Route::delete('/saved-places/collections/{collection}', [SavedPlaceController::class, 'destroyCollection'])
        ->name('saved-places.collections.destroy');

    Route::post('/saved-places/collections/{collection}/places', [SavedPlaceController::class, 'addToCollection'])
        ->name('saved-places.collections.places.store');

    Route::delete('/saved-places/collections/{collection}/places/{place}', [SavedPlaceController::class, 'removePlaceFromCollection'])
        ->name('saved-places.collections.places.destroy');

});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'login'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'authenticate'])
        ->middleware('throttle:5,1')->name('authenticate');

    Route::middleware('admin')->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
        Route::delete('/attractions/{attraction}/images/{image}', [AdminAttractionController::class, 'destroyImage'])
            ->name('attractions.images.destroy');
        Route::resource('attractions', AdminAttractionController::class)->except('show');
    });
});
