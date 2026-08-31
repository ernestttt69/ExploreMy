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
use Illuminate\Support\Facades\Validator;

class AttractionController extends Controller
{
    public function index(Request $request)
    {
        $states = State::orderBy('state_name')->get();

        $categories = PreferenceCategory::orderBy('category_name')->get();

        $searchSubmitted = $request->input('search_submitted') === '1';

        if ($searchSubmitted) {
            $validator = Validator::make(
                $request->all(),
                [
                    'search' => [
                        'required',
                        'string',
                        'max:255',
                    ],
                ],
                [
                    'search.required' => 'Please enter a place to search.',
                ]
            );

            if ($validator->fails()) {
                return redirect()
                    ->route('attractions.index')
                    ->withErrors($validator)
                    ->withInput($request->except('search_submitted'));
            }
        }

        $query = Attraction::with([
            'images',
            'state',
            'preferences',
        ]);

        if ($searchSubmitted) {
            $search = trim($request->input('search'));

            $query->where(function ($q) use ($search) {
                $q->where(
                    'attraction_name',
                    'like',
                    '%' . $search . '%'
                )
                ->orWhere(
                    'location',
                    'like',
                    '%' . $search . '%'
                )
                ->orWhere(
                    'description',
                    'like',
                    '%' . $search . '%'
                );
            });

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

    public function addToWishlist($id)
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
            app(GreenRewardService::class)->awardActivity(Auth::user(), 'save_attraction');
        }

        return redirect()
            ->back()
            ->with(
                'success',
                __('messages.wishlist_added')
            );
    }

    public function removeFromWishlist($id)
    {
        Wishlist::where(
            'user_id',
            Auth::id()
        )
            ->where(
                'attraction_id',
                $id
            )
            ->delete();

        return redirect()
            ->back()
            ->with(
                'success',
                __('messages.wishlist_removed')
            );
    }
}
