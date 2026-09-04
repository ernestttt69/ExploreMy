<?php

namespace App\Services;

use App\Models\Attraction;
use App\Models\State;
use Illuminate\Database\QueryException;

class AttractionContextService
{
    private const MAX_RESULTS = 5;

    /**
     * Retrieve a small, relevant set of ExploreMY records for the latest question.
     */
    public function retrieve(array $messages): array
    {
        $question = $this->latestUserMessage($messages);
        $terms = $this->searchTerms($question);

        try {
            $location = $this->requestedLocation($question);
            if ($location !== null) {
                $locationTerms = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($location), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $terms = array_values(array_diff($terms, $locationTerms));
            }

            if ($terms === [] && $location === null) {
                return [];
            }

            $candidates = Attraction::query()
                ->with(['state:state_id,state_name', 'preferences:preference_categories.preference_id,category_name'])
                ->when($location !== null, function ($query) use ($location): void {
                    $like = '%'.$location.'%';
                    $query->where(function ($locationQuery) use ($like): void {
                        $locationQuery->where('location', 'like', $like)
                            ->orWhereHas('state', fn ($state) => $state->where('state_name', 'like', $like));
                    });
                })
                ->when($terms !== [], function ($query) use ($terms): void {
                    $query->where(function ($termQuery) use ($terms): void {
                        foreach ($terms as $term) {
                            $like = '%'.$term.'%';
                            $termQuery->orWhere('attraction_name', 'like', $like)
                                ->orWhere('category', 'like', $like)
                                ->orWhere('description', 'like', $like)
                                ->orWhereHas('preferences', fn ($preference) => $preference->where('category_name', 'like', $like));
                        }
                    });
                })
                ->limit(100)
                ->get();
        } catch (QueryException $exception) {
            report($exception);

            return [];
        }

        return $candidates
            ->sortByDesc(fn (Attraction $attraction): int => $this->relevanceScore($attraction, $terms))
            ->take(self::MAX_RESULTS)
            ->map(fn (Attraction $attraction): array => $this->toContextRecord($attraction))
            ->values()
            ->all();
    }

    private function latestUserMessage(array $messages): string
    {
        foreach (array_reverse($messages) as $message) {
            if (($message['role'] ?? null) === 'user') {
                return trim((string) ($message['content'] ?? ''));
            }
        }

        return '';
    }

    private function searchTerms(string $question): array
    {
        $stopWords = [
            'about', 'and', 'are', 'attraction', 'attractions', 'best', 'can', 'could', 'for', 'from', 'good', 'help',
            'how', 'in', 'is', 'me', 'morning', 'of', 'place', 'places', 'recommend', 'show',
            'the', 'there', 'to', 'travel', 'visit', 'what', 'where', 'which', 'with', 'you',
            'yang', 'dan', 'di', 'ke', 'tempat', 'boleh', 'saya', '推荐', '景点', '地方', '哪里',
            '什么', '可以', '请问', '马来西亚',
        ];

        $parts = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($question), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return collect($parts)
            ->filter(fn (string $term): bool => mb_strlen($term) >= 2 && ! in_array($term, $stopWords, true))
            ->unique()
            ->take(8)
            ->values()
            ->all();
    }

    private function requestedLocation(string $question): ?string
    {
        $normalized = mb_strtolower($question);

        if (preg_match('/\bkl\b/i', $question) === 1) {
            return 'Kuala Lumpur';
        }

        return State::query()
            ->pluck('state_name')
            ->first(fn (string $state): bool => str_contains($normalized, mb_strtolower($state)));
    }

    private function relevanceScore(Attraction $attraction, array $terms): int
    {
        $name = mb_strtolower((string) $attraction->attraction_name);
        $state = mb_strtolower((string) optional($attraction->state)->state_name);
        $location = mb_strtolower((string) $attraction->location);
        $description = mb_strtolower((string) $attraction->description);
        $categories = mb_strtolower($attraction->preferences->pluck('category_name')->implode(' '));

        return collect($terms)->sum(function (string $term) use ($name, $state, $location, $description, $categories): int {
            return (str_contains($name, $term) ? 10 : 0)
                + (str_contains($state, $term) ? 6 : 0)
                + (str_contains($location, $term) ? 4 : 0)
                + (str_contains($categories, $term) ? 3 : 0)
                + (str_contains($description, $term) ? 1 : 0);
        });
    }

    private function toContextRecord(Attraction $attraction): array
    {
        return [
            'name' => $attraction->attraction_name,
            'state' => optional($attraction->state)->state_name,
            'categories' => $attraction->preferences->pluck('category_name')->values()->all(),
            'description' => $attraction->description ?: null,
            'address' => $attraction->location,
            'operating_hours' => $attraction->operating_hours ?: null,
            'entrance_fee' => $attraction->entrance_fee ?: null,
            'budget_level' => $attraction->budget_level ?: null,
            'nearby_transport' => $attraction->nearby_transport ?: null,
            'rating' => $attraction->rating !== null ? (float) $attraction->rating : null,
            'google_place_id' => $attraction->place_id,
        ];
    }
}
