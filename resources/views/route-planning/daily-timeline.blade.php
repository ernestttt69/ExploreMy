@php
    $events = collect();
    foreach ($routeResult['stops'] as $stop) {
        $events->push(['type' => 'visit', 'at' => $stop['visit_start_at'], 'data' => $stop]);
    }
    foreach ($routeResult['transit_legs'] as $leg) {
        $events->push(['type' => 'travel', 'at' => $leg['departure_at'], 'data' => $leg]);
    }
    $eventsByDay = $events->sortBy('at')->groupBy(fn ($event) => \Carbon\CarbonImmutable::parse($event['at'])->toDateString());
    $firstDay = \Carbon\CarbonImmutable::parse($routeResult['trip_start_date'])->startOfDay();
    $lastDay = \Carbon\CarbonImmutable::parse($routeResult['trip_end_date'])->startOfDay();
@endphp

@for($day = $firstDay; $day->lessThanOrEqualTo($lastDay); $day = $day->addDay())
    <section class="transit-leg daily-itinerary" data-itinerary-day="{{ $day->toDateString() }}">
        <div class="transit-leg-heading">
            <div>
                <strong>{{ __('schedule.day', ['number' => $firstDay->diffInDays($day) + 1]) }}</strong>
                <span>{{ $day->locale(app()->getLocale())->translatedFormat('D, d M Y') }}</span>
            </div>
        </div>
        <div class="simple-trip-timeline">
            @forelse($eventsByDay->get($day->toDateString(), collect()) as $event)
                @php($data = $event['data'])
                @if($event['type'] === 'visit')
                    <div class="simple-trip-stop">
                        <time>{{ \Carbon\CarbonImmutable::parse($data['visit_start_at'])->format('g:i A') }}</time>
                        <span class="simple-trip-dot"></span>
                        <div>
                            <strong>{{ $data['name'] }}</strong>
                            @if(empty($data['opening_hours_verified']))
                                <small class="stop-visit-suggestion">{{ __('schedule.hours_unknown') }}</small>
                            @endif
                            @if(!empty($data['opening_conflict']))
                                <small class="stop-visit-suggestion">{{ __('schedule.wait_opening', ['time' => \Carbon\CarbonImmutable::parse($data['opening_conflict']['arrival_at'])->format('d M, g:i A'), 'visit' => \Carbon\CarbonImmutable::parse($data['visit_start_at'])->format('d M, g:i A')]) }}</small>
                            @endif
                            <small class="stop-visit-suggestion">
                                {{ __('route_form.explore_until', ['time' => \Carbon\CarbonImmutable::parse($data['visit_end_at'])->format('g:i A'), 'duration' => $data['suggested_visit_display']]) }}
                            </small>
                        </div>
                    </div>
                @else
                    <div class="simple-trip-transport transport-with-fare">
                        <div class="transport-details">
                        <span>{{ $data['transport_summary'] }}</span>
                        <small>{{ $data['departure_time'] }} &ndash; {{ $data['arrival_time'] }} &middot; {{ $data['duration_display'] }}</small>
                        <small>{{ __('route_form.distance_km', ['distance' => number_format($data['distance'], 2)]) }}</small>
                        @if(!empty($data['navigation_url']))
                            <a class="guidance-button navigation-button" href="{{ $data['navigation_url'] }}" target="_blank" rel="noopener noreferrer">{{ __('route_form.navigate_google_maps') }}</a>
                        @endif
                        </div>
                        @if($data['fare'] !== null)
                            <strong class="transport-fare">{{ $data['fare_currency'] }} {{ number_format($data['fare'], 2) }} @if(!empty($data['fare_is_estimated'])) ({{ __('route_form.fare_estimated') }}) @endif</strong>
                        @endif
                    </div>
                @endif
            @empty
                <p class="itinerary-hint">{{ __('schedule.free_day') }}</p>
            @endforelse
        </div>
    </section>
@endfor
