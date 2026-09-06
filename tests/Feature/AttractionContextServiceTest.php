<?php

namespace Tests\Feature;

use App\Models\Attraction;
use App\Models\State;
use App\Services\AttractionContextService;
use Tests\TestCase;

class AttractionContextServiceTest extends TestCase
{
    public function test_it_retrieves_relevant_attraction_records_for_the_latest_question(): void
    {
        $johor = State::query()->create(['state_name' => 'Johor']);

        Attraction::query()->create([
            'place_id' => 'google-johor-zoo',
            'state_id' => $johor->state_id,
            'attraction_name' => 'Johor Zoo',
            'description' => 'A family attraction with animals.',
            'location' => 'Johor Bahru, Johor',
            'operating_hours' => 'Monday: 9 AM–6 PM',
            'entrance_fee' => 'Price unavailable',
            'budget_level' => 'Price unavailable',
            'rating' => 4.1,
        ]);

        Attraction::query()->create([
            'place_id' => 'google-melaka-museum',
            'state_id' => $johor->state_id,
            'attraction_name' => 'History Museum',
            'description' => 'A museum.',
            'location' => 'Melaka',
            'entrance_fee' => 'Price unavailable',
            'budget_level' => 'Price unavailable',
        ]);

        $results = app(AttractionContextService::class)->retrieve([
            ['role' => 'user', 'content' => 'What is the Johor Zoo rating and address?'],
        ]);

        $this->assertNotEmpty($results);
        $this->assertLessThanOrEqual(5, count($results));
        $this->assertSame('Johor Zoo', $results[0]['name']);
        $this->assertSame('Johor', $results[0]['state']);
        $this->assertSame('Johor Bahru, Johor', $results[0]['address']);
        $this->assertSame(4.1, $results[0]['rating']);
    }

    public function test_it_does_not_search_the_database_for_a_greeting(): void
    {
        $this->assertSame([], app(AttractionContextService::class)->retrieve([
            ['role' => 'user', 'content' => 'Good morning'],
        ]));
    }

    public function test_partial_names_stay_unchanged_and_typos_use_location_scoped_suggestions(): void
    {
        $johor = State::query()->create(['state_name' => 'Johor']);
        $penang = State::query()->create(['state_name' => 'Penang']);
        foreach ([$johor, $penang] as $state) {
            Attraction::query()->create([
                'place_id' => 'alibaba-'.$state->state_id,
                'state_id' => $state->state_id,
                'attraction_name' => 'Alibaba Cafe',
                'location' => $state->state_name,
            ]);
        }
        $service = app(AttractionContextService::class);
        $partial = $service->retrieve([['role' => 'user', 'content' => 'Where is ali in Johor?']]);
        $this->assertCount(1, $partial);
        $this->assertSame('Alibaba Cafe', $partial[0]['name']);
        $this->assertArrayNotHasKey('match_type', $partial[0]);

        $fuzzy = $service->retrieve([['role' => 'user', 'content' => 'Where is alibba in Johor?']]);
        $this->assertCount(1, $fuzzy);
        $this->assertSame('Johor', $fuzzy[0]['state']);
        $this->assertSame('possible_name_match', $fuzzy[0]['match_type']);
        $this->assertSame([], $service->retrieve([['role' => 'user', 'content' => 'zzzzzzzz']]));
    }

    public function test_it_treats_kuala_lumpur_as_a_location_filter(): void
    {
        $kualaLumpur = State::query()->create(['state_name' => 'Kuala Lumpur']);

        Attraction::query()->create([
            'place_id' => 'google-kl-tower',
            'state_id' => $kualaLumpur->state_id,
            'attraction_name' => 'KL Tower',
            'description' => 'An observation tower.',
            'location' => 'Kuala Lumpur',
        ]);

        $results = app(AttractionContextService::class)->retrieve([
            ['role' => 'user', 'content' => 'Recommend attractions in Kuala Lumpur'],
        ]);

        $this->assertNotEmpty($results);
        $this->assertSame('KL Tower', $results[0]['name']);
    }
}
