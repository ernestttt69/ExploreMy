<?php

namespace App\Http\Controllers;

use App\Models\Attraction;
use App\Models\PreferenceCategory;
use App\Models\State;
use App\Models\UserPreference;
use App\Models\Wishlist;
use App\Services\GreenRewardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttractionController extends Controller
{
    public function index(Request $request)
    {
        $states = State::orderBy('state_name')->get();

        $categories = PreferenceCategory::orderBy('category_name')->get();

        $searchSubmitted = $request->input('search_submitted') === '1';

        if ($searchSubmitted) {
            $hasCriteria =
                $request->filled('search') ||
                $request->filled('state_id') ||
                $request->filled('budget_level') ||
                $request->filled('rating') ||
                ! empty($request->input('categories', []));

            if (! $hasCriteria) {
                return redirect()
                    ->back()
                    ->withErrors([
                        'search' => 'Please enter a place to search or select at least one filter.',
                    ])
                    ->withInput($request->except('search_submitted'));
            }
        }

        $query = Attraction::with([
            'images',
            'state',
            'preferences',
        ]);

        if ($searchSubmitted) {
            if ($request->filled('search')) {
                $search = trim($request->input('search'));

                $query->where(
                    'attraction_name',
                    'like',
                    '%' . $search . '%'
                );
            }

            if ($request->filled('state_id')) {
                $query->where(
                    'state_id',
                    $request->input('state_id')
                );
            }

            if ($request->filled('budget_level')) {
                $query->where(
                    'budget_level',
                    $request->input('budget_level')
                );
            }

            if ($request->filled('rating')) {
                $query->where(
                    'rating',
                    '>=',
                    $request->input('rating')
                );
            }

            $selectedCategories = array_map(
                'intval',
                $request->input('categories', [])
            );

            if (!empty($selectedCategories)) {
                $query->whereHas(
                    'preferences',
                    function ($q) use ($selectedCategories) {
                        $q->whereIn(
                            'preference_categories.preference_id',
                            $selectedCategories
                        );
                    }
                );
            }

            $query->orderByDesc('rating');
        } else {
            $userPreferenceIds = UserPreference::where(
                'user_id',
                Auth::id()
            )
                ->pluck('preference_id')
                ->map(function ($id) {
                    return (int) $id;
                })
                ->toArray();

            if (!empty($userPreferenceIds)) {
                $query->whereHas(
                    'preferences',
                    function ($q) use ($userPreferenceIds) {
                        $q->whereIn(
                            'preference_categories.preference_id',
                            $userPreferenceIds
                        );
                    }
                );
            }

            $query->orderByDesc('rating');
        }

        $attractions = $query->paginate(12);

        $wishlistedAttractionIds = Wishlist::where('user_id', Auth::id())
            ->whereIn(
                'attraction_id',
                collect($attractions->items())->pluck('attraction_id')
            )
            ->pluck('attraction_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return view(
            'attractions.index',
            compact(
                'attractions',
                'states',
                'categories',
                'searchSubmitted',
                'wishlistedAttractionIds'
            )
        );
    }

    public function show($id)
    {
        $attraction = Attraction::with([
            'images',
            'state',
            'preferences',
        ])->findOrFail($id);

        $isWishlisted = Wishlist::where(
            'user_id',
            Auth::id()
        )
            ->where(
                'attraction_id',
                $attraction->attraction_id
            )
            ->exists();

        return view(
            'attractions.show',
            compact(
                'attraction',
                'isWishlisted'
            )
        );
    }

    public function addToWishlist(Request $request, $id)
    {
        $attraction = Attraction::findOrFail($id);

        $existingWishlist = Wishlist::where(
            'user_id',
            Auth::id()
        )
            ->where(
                'attraction_id',
                $attraction->attraction_id
            )
            ->first();

        if (!$existingWishlist) {
            $wishlist = new Wishlist();

            $wishlist->user_id = Auth::id();

            $wishlist->attraction_id =
                $attraction->attraction_id;

            $wishlist->save();
            app(GreenRewardService::class)->queueActivity(Auth::user(), 'save_attraction');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('messages.wishlist_added'),
                'wishlisted' => true,
                'action' => route('attractions.wishlist.remove', $id),
                'label' => '♥ '.__('explore.saved'),
                'ariaLabel' => __('explore.remove_wishlist', ['name' => $attraction->attraction_name]),
            ]);
        }

        return redirect()
            ->back()
            ->with(
                'success',
                __('messages.wishlist_added')
            );
    }

    public function removeFromWishlist(Request $request, $id)
    {
        $attraction = Attraction::findOrFail($id);
        Wishlist::where(
            'user_id',
            Auth::id()
        )
            ->where(
                'attraction_id',
                $id
            )
            ->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('messages.wishlist_removed'),
                'wishlisted' => false,
                'action' => route('attractions.wishlist.add', $id),
                'label' => '♡ '.__('explore.save'),
                'ariaLabel' => __('explore.add_wishlist', ['name' => $attraction->attraction_name]),
            ]);
        }

        return redirect()
            ->back()
            ->with(
                'success',
                __('messages.wishlist_removed')
            );
    }
}
