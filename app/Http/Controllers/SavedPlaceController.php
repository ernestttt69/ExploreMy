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
        $savedPlaces = Wishlist::whereHas('attraction')->with([
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
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', ...($request->filled('start_time') ? ['after:start_time'] : [])],
            'attraction_ids' => ['sometimes', 'array'],
            'attraction_ids.*' => ['integer'],
        ], [
            'name.required' => __('messages.collection_name_required'),
            'name.unique' => 'You already have a collection with this name. Please choose a different one.',
            'start_date.required' => 'Please select a start date.',
            'start_date.after_or_equal' => 'The start date cannot be before today.',
            'end_date.required' => 'Please select an end date.',
            'end_date.after_or_equal' => 'The end date cannot be before the start date.',
            'attraction_ids.required' => __('messages.collection_place_required'),
        ]);

        $attractionIds = Wishlist::where('user_id', Auth::id())
            ->whereIn('attraction_id', ($validated['attraction_ids'] ?? []))
            ->pluck('attraction_id');

        if ($attractionIds->count() !== count(array_unique(($validated['attraction_ids'] ?? [])))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'attraction_ids' => __('messages.saved_places_only'),
            ]);
        }

        $collection = DB::transaction(function () use ($validated, $attractionIds) {
            $collection = SavedPlaceCollection::create([
                'user_id' => Auth::id(),
                'name' => trim($validated['name']),
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'start_time' => $validated['start_time'] ?? null,
                'end_time' => $validated['end_time'] ?? null,
            ]);

            foreach ($attractionIds as $attractionId) {
                SavedPlaceCollectionItem::create([
                    'collection_id' => $collection->collection_id,
                    'attraction_id' => $attractionId,
                ]);
            }
            return $collection;
        });

        if ($request->expectsJson()) {
            $collection->load('items.attraction.images')->loadCount('items');
            $savedPlaces = Wishlist::with('attraction')->where('user_id', Auth::id())->get();

            return response()->json([
                'message' => __('messages.collection_created'),
                'html' => view('saved-places.collection-card', compact('collection', 'savedPlaces'))->render(),
            ], 201);
        }

        return redirect()->route('saved-places.index')->with('success', __('messages.collection_created'));
    }

    public function addToCollection(Request $request, $collectionId)
    {
        $validated = $request->validate([
            'attraction_ids' => ['required', 'array', 'min:1'],
            'attraction_ids.*' => ['integer'],
        ], [
            'attraction_ids.required' => __('messages.collection_add_required'),
        ]);

        $collection = SavedPlaceCollection::where('collection_id', $collectionId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $attractionIds = Wishlist::where('user_id', Auth::id())
            ->whereIn('attraction_id', ($validated['attraction_ids'] ?? []))
            ->pluck('attraction_id');

        if ($attractionIds->count() !== count(array_unique(($validated['attraction_ids'] ?? [])))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['attraction_ids' => __('messages.saved_places_only')]);
        }

        $existingIds = $collection->items()->pluck('attraction_id')->all();
        $newIds = $attractionIds->diff($existingIds);

        if ($newIds->isEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['attraction_ids' => __('messages.collection_duplicate')]);
        }

        foreach ($newIds as $attractionId) {
            SavedPlaceCollectionItem::create([
                'collection_id' => $collection->collection_id,
                'attraction_id' => $attractionId,
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('messages.collection_added'),
                'html' => $this->collectionHtml($collection),
            ]);
        }

        return redirect()->route('saved-places.index')->with('success', __('messages.collection_added'));
    }

    public function destroyCollection(Request $request, $collectionId)
    {
        $collection = SavedPlaceCollection::where('collection_id', $collectionId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // Deleting the collection cascades to its items via the DB foreign key.
        $collection->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => __('messages.collection_deleted')]);
        }

        return redirect()->route('saved-places.index')->with('success', 'Collection deleted.');
    }

    public function removePlaceFromCollection(Request $request, $collectionId, $collectionItemId)
    {
        $collection = SavedPlaceCollection::where('collection_id', $collectionId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $deleted = SavedPlaceCollectionItem::where('collection_id', $collectionId)
            ->where('collection_item_id', $collectionItemId)
            ->delete();

        abort_unless($deleted, 404);

        if ($request->expectsJson()) {
            return response()->json(['message' => __('messages.collection_place_removed'), 'html' => $this->collectionHtml($collection)]);
        }

        return redirect()->route('saved-places.index')->with('success', 'Place removed from collection.');
    }
    private function collectionHtml(SavedPlaceCollection $collection): string
    {
        $collection->load(['items' => fn ($query) => $query->whereHas('attraction'), 'items.attraction.images'])
            ->loadCount(['items' => fn ($query) => $query->whereHas('attraction')]);
        $savedPlaces = Wishlist::with('attraction')->whereHas('attraction')->where('user_id', Auth::id())->get();
        return view('saved-places.collection-card', compact('collection', 'savedPlaces'))->render();
    }
}
