<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchMalaysiaPlacesRequest;
use App\Services\MalaysiaPlaceService;
use Illuminate\Http\JsonResponse;

class MalaysiaPlaceController extends Controller
{
    public function __construct(private MalaysiaPlaceService $places)
    {
    }

    public function search(SearchMalaysiaPlacesRequest $request): JsonResponse
    {
        $places = $this->places->search(
            $request->validated('query'),
            (int) $request->validated('limit', 8)
        );

        return response()->json([
            'places' => $places->map(fn ($place) => [
                'place_id' => $place->getKey(),
                'name' => $place->name,
                'display_name' => $place->display_name,
                'latitude' => $place->latitude,
                'longitude' => $place->longitude,
                'timezone' => $place->timezone,
            ])->values(),
        ]);
    }
}
