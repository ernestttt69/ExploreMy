<?php

namespace App\Http\Controllers;

use App\Models\SavedPlaceCollection;
use App\Models\SavedPlaceCollectionItem;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
            'items' => fn ($q) => $q->whereHas('attraction'),
            'items.attraction.images',
        ])
            ->withCount([
                'items' => fn ($q) => $q->whereHas('attraction'),
            ])
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
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('saved_place_collections', 'name')->where('user_id', Auth::id()),
            ],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'attraction_ids' => ['required', 'array', 'min:1'],
            'attraction_ids.*' => ['integer'],
        ], [
            'name.required' => 'Give your collection a name.',
            'name.unique' => 'You already have a collection with this name. Please choose a different one.',
            'start_date.required' => 'Please select a start date.',
            'start_date.after_or_equal' => 'The start date cannot be before today.',
            'end_date.required' => 'Please select an end date.',
            'end_date.after_or_equal' => 'The end date cannot be before the start date.',
            'attraction_ids.required' => 'Choose at least one saved place for this collection.',
        ]);

        $attractionIds = Wishlist::where('user_id', Auth::id())
            ->whereIn('attraction_id', $validated['attraction_ids'])
            ->pluck('attraction_id');

        if ($attractionIds->count() !== count(array_unique($validated['attraction_ids']))) {
            return back()->withErrors(['attraction_ids' => 'Choose places from your saved places only.'])->withInput();
        }

        DB::transaction(function () use ($validated, $attractionIds): void {
            $collection = SavedPlaceCollection::create([
                'user_id' => Auth::id(),
                'name' => trim($validated['name']),
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
            ]);

            foreach ($attractionIds as $attractionId) {
                SavedPlaceCollectionItem::create([
                    'collection_id' => $collection->collection_id,
                    'attraction_id' => $attractionId,
                ]);
            }
        });

        return redirect()->route('saved-places.index')->with('success', 'Collection created.');
    }

    public function addToCollection(Request $request, $collectionId)
    {
        $validated = $request->validate([
            'attraction_ids' => ['required', 'array', 'min:1'],
            'attraction_ids.*' => ['integer'],
        ], [
            'attraction_ids.required' => 'Choose at least one saved place to add.',
        ]);

        $collection = SavedPlaceCollection::where('collection_id', $collectionId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $attractionIds = Wishlist::where('user_id', Auth::id())
            ->whereIn('attraction_id', $validated['attraction_ids'])
            ->pluck('attraction_id');

        if ($attractionIds->count() !== count(array_unique($validated['attraction_ids']))) {
            return back()->withErrors(['attraction_ids' => 'Choose places from your saved places only.'])->withInput();
        }

        $existingIds = $collection->items()->pluck('attraction_id')->all();
        $newIds = $attractionIds->diff($existingIds);

        if ($newIds->isEmpty()) {
            return back()->with('error', 'Those places are already in this collection.');
        }

        foreach ($newIds as $attractionId) {
            SavedPlaceCollectionItem::create([
                'collection_id' => $collection->collection_id,
                'attraction_id' => $attractionId,
            ]);
        }

        return redirect()->route('saved-places.index')->with('success', 'Places added to your collection.');
    }

    public function destroyCollection(Request $request, $collectionId)
    {
        $collection = SavedPlaceCollection::where('collection_id', $collectionId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // Deleting the collection cascades to its items via the DB foreign key.
        $collection->delete();

        return redirect()->route('saved-places.index')->with('success', 'Collection deleted.');
    }

    public function removePlaceFromCollection(Request $request, $collectionId, $collectionItemId)
    {
        $collection = SavedPlaceCollection::where('collection_id', $collectionId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        SavedPlaceCollectionItem::where('collection_id', $collectionId)
            ->where('collection_item_id', $collectionItemId)
            ->delete();

        return redirect()->route('saved-places.index')->with('success', 'Place removed from collection.');
    }
}
