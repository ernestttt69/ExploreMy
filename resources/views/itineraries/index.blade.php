<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('itinerary.my_trips') }} - ExploreMY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/itinerary.css') }}">
</head>
<body class="itinerary-body">

@include('components.navbar')
<x-page-back :href="route('dashboard')" :label="__('ui.profile.back')" />

<main class="itinerary-page trip-index-page">
    <div class="container">
        <section class="page-heading">
            <div>
                <span class="eyebrow">{{ __('itinerary.trip_planning') }}</span>
                <h1>{{ __('itinerary.planned_trips') }}</h1>
                <p>{{ __('itinerary.intro') }}</p>
            </div>
            <button type="button" class="button button-primary" onclick="document.getElementById('trip-dialog').showModal()">
                <span aria-hidden="true">+</span> {{ __('itinerary.create') }}
            </button>
        </section>

        @if(session('status'))
            <div class="notice notice-success" role="status">{{ session('status') }}</div>
        @endif

        @if($errors->any())
            <div class="notice notice-error" role="alert">
                <strong>{{ __('itinerary.create_failed') }}</strong>
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($trips->isEmpty())
            <section class="empty-trips card-surface">
                <div class="empty-illustration" aria-hidden="true">✦</div>
                <h2>{{ __('itinerary.empty_title') }}</h2>
                <p>{{ __('itinerary.empty_text') }}</p>
                <a href="{{ route('explore') }}" class="button button-primary">{{ __('itinerary.generate_now') }}</a>
            </section>
        @else
            <section class="trip-grid" aria-label="Your planned trips">
                @foreach($trips as $trip)
                    <article class="trip-card card-surface">
                        <div class="trip-card-icon" aria-hidden="true">◉</div>
                        <div class="trip-card-content">
                            <p class="trip-date">
                                {{ $trip->start_date?->translatedFormat('d M Y') ?? __('itinerary.dates_pending') }}
                                @if($trip->start_date && $trip->end_date && ! $trip->end_date->isSameDay($trip->start_date))
                                    - {{ $trip->end_date->translatedFormat('d M Y') }}
                                @endif
                            </p>
                            <h2>{{ $trip->title }}</h2>
                            <p class="trip-destination">{{ $trip->destination ?: __('itinerary.malaysia') }}</p>
                            <div class="trip-metrics">
                                <span>{{ trans_choice('itinerary.items', $trip->items->count(), ['count' => $trip->items->count()]) }}</span>
                                <span class="carbon-chip">{{ __('itinerary.estimated') }} {{ number_format($trip->total_carbon_kg, 1) }} kg CO<sub>2</sub>e</span>
                            </div>
                            <a href="{{ route('itineraries.show', $trip) }}" class="button button-outline">{{ __('itinerary.view') }} <span aria-hidden="true">→</span></a>
                        </div>
                    </article>
                @endforeach
            </section>
        @endif
    </div>
</main>

<dialog class="app-dialog" id="trip-dialog" aria-labelledby="trip-dialog-title" data-reopen-trip="{{ $errors->any() ? 'true' : 'false' }}">
    <form method="POST" action="{{ route('itineraries.store') }}" class="dialog-form" data-ajax-crud>
        @csrf
        <div class="dialog-heading">
            <div>
                <span class="eyebrow">{{ __('itinerary.new_trip') }}</span>
                <h2 id="trip-dialog-title">{{ __('itinerary.create_title') }}</h2>
            </div>
            <button type="button" class="icon-button" aria-label="{{ __('itinerary.close') }}" onclick="this.closest('dialog').close()">×</button>
        </div>
        <label>
            {{ __('itinerary.trip_name') }}
            <input name="title" required maxlength="120" value="{{ old('title') }}" placeholder="{{ __('itinerary.trip_name_example') }}">
        </label>
        <label>
            {{ __('itinerary.destination') }}
            <input name="destination" maxlength="160" value="{{ old('destination') }}" placeholder="{{ __('itinerary.destination_example') }}">
        </label>
        <div class="form-grid">
            <label>
                {{ __('itinerary.start_date') }}
                <input type="date" name="start_date" id="trip-start-date" min="{{ today()->toDateString() }}" value="{{ old('start_date') }}">
            </label>
            <label>
                {{ __('itinerary.end_date') }}
                <input type="date" name="end_date" id="trip-end-date" min="{{ today()->toDateString() }}" value="{{ old('end_date') }}">
            </label>
        </div>
        <label>
            {{ __('itinerary.notes') }} <span class="muted">({{ __('itinerary.optional') }})</span>
            <textarea name="description" rows="3" maxlength="1000" placeholder="{{ __('itinerary.notes_example') }}">{{ old('description') }}</textarea>
        </label>
        <div class="dialog-actions">
            <button type="button" class="button button-quiet" onclick="this.closest('dialog').close()">{{ __('itinerary.cancel') }}</button>
            <button class="button button-primary" type="submit">{{ __('itinerary.create_title') }}</button>
        </div>
    </form>
</dialog>

@include('components.footer')

<script>
const tripStartDate = document.getElementById('trip-start-date');
const tripEndDate = document.getElementById('trip-end-date');

function updateTripEndDateLimit() {
    if (!tripStartDate || !tripEndDate) return;

    tripEndDate.min = tripStartDate.value || '{{ today()->toDateString() }}';

    if (tripEndDate.value && tripEndDate.value < tripEndDate.min) {
        tripEndDate.value = tripEndDate.min;
    }
}

tripStartDate?.addEventListener('change', updateTripEndDateLimit);
updateTripEndDateLimit();

const tripDialog = document.getElementById('trip-dialog');
const shouldReopenTripDialog = tripDialog?.dataset.reopenTrip === 'true';
if (shouldReopenTripDialog) {
    tripDialog?.showModal();
}
</script>

</body>
</html>
