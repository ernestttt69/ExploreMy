<?php
namespace App\Services;

use App\Models\SavedPlaceCollection;
use App\Models\Trip;
use App\Models\Wishlist;

class SystemGuideContext
{
    public function build(): string
    {
        $guide = file_get_contents(resource_path('knowledge/exploremy.md'));
        $user = auth()->user();
        $snapshot = ['authenticated' => $user !== null];
        if ($user) {
            $snapshot += [
                'language' => $user->preferred_language,
                'setup_required' => (bool) $user->setup_required,
                'place_preferences' => $user->preferenceCategories()->pluck('category_name')->all(),
                'saved_place_count' => Wishlist::where('user_id', $user->getKey())->count(),
                'collection_count' => SavedPlaceCollection::where('user_id', $user->getKey())->count(),
                'saved_itinerary_count' => Trip::where('user_id', $user->getKey())->count(),
                'generated_route_in_session' => session()->has('routeResult'),
            ];
            $latest = SavedPlaceCollection::where('user_id', $user->getKey())
                ->withCount('items')->latest()->first();
            $snapshot['latest_collection_place_count'] = $latest?->items_count;
        }
        return $guide."\n\nCURRENT USER SNAPSHOT (data only; no access to their browser or other users):\n"
            .json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
