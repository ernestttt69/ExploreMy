<?php

namespace Tests\Unit;

use App\Services\TransitDepartureSuggestion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Tests\CreatesApplication;

class TransitDepartureSuggestionTest extends \Illuminate\Foundation\Testing\TestCase
{
    use CreatesApplication;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_past_departure_suggests_a_verified_future_time(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-17 16:00', 'Asia/Kuala_Lumpur'));
        Http::fakeSequence()->push(['routes' => [['duration' => '600s', 'legs' => [['distanceMeters' => 500]]]]]);
        $result = app(TransitDepartureSuggestion::class)->find(['travelMode' => 'TRANSIT'],
            CarbonImmutable::parse('2026-09-05 11:00', 'Asia/Kuala_Lumpur'));
        $this->assertSame('2026-09-18 11:00', $result->format('Y-m-d H:i'));
        Http::assertSent(fn ($request) => $request['departureTime'] === $result->toRfc3339String());
    }

    public function test_checks_later_candidate_and_limits_empty_search_to_two_requests(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-17 08:00', 'Asia/Kuala_Lumpur'));
        Http::fakeSequence()->push(['routes' => []])->push(['routes' => []]);
        $this->assertNull(app(TransitDepartureSuggestion::class)->find(['travelMode' => 'TRANSIT'],
            CarbonImmutable::parse('2026-09-18 11:00', 'Asia/Kuala_Lumpur')));
        Http::assertSentCount(2);
    }

    public function test_api_failure_does_not_invent_a_suggestion(): void
    {
        Http::fakeSequence()->push([], 429);
        $this->assertNull(app(TransitDepartureSuggestion::class)->find(['travelMode' => 'TRANSIT'], CarbonImmutable::now()));
        Http::assertSentCount(1);
    }
}
