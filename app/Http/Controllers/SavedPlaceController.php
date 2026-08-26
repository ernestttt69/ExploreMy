<?php

namespace App\Http\Controllers;

use App\Models\SavedPlaceCollection;
use App\Models\SavedPlaceCollectionItem;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SavedPlaceController extends Controller
{
    public function index()
    {
        $savedPlaces = Wishlist::with([
            'attraction.images',
            'attraction.state',
            'attraction.preferences',
        ])
            ->where('user_id', Auth::id())
            ->get();

        $collections = SavedPlaceCollection::with([
            'items.wishlist.attraction.images',
        ])
            ->withCount('items')
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        return view(
            'saved-places.index',
            compact('savedPlaces', 'collections')
        );
    }

    public function storeCollection(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'wishlist_ids' => ['required', 'array', 'min:1'],
            'wishlist_ids.*' => ['integer'],
        ], [
            'name.required' => __('messages.collection_name_required'),
            'wishlist_ids.required' => __('messages.collection_place_required'),
        ]);

        $wishlistIds = Wishlist::where('user_id', Auth::id())
            ->whereIn('wishlist_id', $validated['wishlist_ids'])
            ->pluck('wishlist_id');

        if ($wishlistIds->count() !== count(array_unique($validated['wishlist_ids']))) {
            return back()->withErrors(['wishlist_ids' => __('messages.saved_places_only')])->withInput();
        }

        DB::transaction(function () use ($validated, $wishlistIds): void {
            $collection = SavedPlaceCollection::create([
                'user_id' => Auth::id(),
                'name' => trim($validated['name']),
            ]);

            foreach ($wishlistIds as $wishlistId) {
                SavedPlaceCollectionItem::create([
                    'collection_id' => $collection->collection_id,
                    'wishlist_id' => $wishlistId,
                ]);
            }
        });

        return redirect()->route('saved-places.index')->with('success', __('messages.collection_created'));
    }

    public function addToCollection(Request $request, $collectionId)
    {
        $validated = $request->validate([
            'wishlist_ids' => ['required', 'array', 'min:1'],
            'wishlist_ids.*' => ['integer'],
        ], [
            'wishlist_ids.required' => __('messages.collection_add_required'),
        ]);

        $collection = SavedPlaceCollection::where('collection_id', $collectionId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $wishlistIds = Wishlist::where('user_id', Auth::id())
            ->whereIn('wishlist_id', $validated['wishlist_ids'])
            ->pluck('wishlist_id');

        if ($wishlistIds->count() !== count(array_unique($validated['wishlist_ids']))) {
            return back()->withErrors(['wishlist_ids' => __('messages.saved_places_only')])->withInput();
        }

        $existingIds = $collection->items()->pluck('wishlist_id')->all();
        $newIds = $wishlistIds->diff($existingIds);

        if ($newIds->isEmpty()) {
            return back()->with('error', __('messages.collection_duplicate'));
        }

        foreach ($newIds as $wishlistId) {
            SavedPlaceCollectionItem::create([
                'collection_id' => $collection->collection_id,
                'wishlist_id' => $wishlistId,
            ]);
        }

        return redirect()->route('saved-places.index')->with('success', __('messages.collection_added'));
    }
}
