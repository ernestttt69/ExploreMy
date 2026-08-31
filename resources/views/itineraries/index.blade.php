<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Trips - ExploreMY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/itinerary.css') }}">
</head>
<body class="itinerary-body">

@include('components.navbar')

<main class="itinerary-page trip-index-page">
    <div class="container">
        <section class="page-heading">
            <div>
                <span class="eyebrow">Trip planning</span>
                <h1>My planned trips</h1>
                <p>Build a clear day-by-day plan and see the impact of every travel choice.</p>
            </div>
            <button type="button" class="button button-primary" onclick="document.getElementById('trip-dialog').showModal()">
                <span aria-hidden="true">+</span> Create new itinerary
            </button>
        </section>

        @if(session('status'))
            <div class="notice notice-success" role="status">{{ session('status') }}</div>
        @endif

        @if($errors->any())
            <div class="notice notice-error" role="alert">
                <strong>Your itinerary was not created.</strong>
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
                <h2>Your next sustainable journey starts here.</h2>
                <p>Create a trip to organise transport, stays, and activities in one carbon-aware itinerary.</p>
                <button type="button" class="button button-primary" onclick="document.getElementById('trip-dialog').showModal()">Create your first itinerary</button>
            </section>
        @else
            <section class="trip-grid" aria-label="Your planned trips">
                @foreach($trips as $trip)
                    <article class="trip-card card-surface">
                        <div class="trip-card-icon" aria-hidden="true">◉</div>
                        <div class="trip-card-content">
                            <p class="trip-date">
                                {{ $trip->start_date?->format('d M Y') ?? 'Dates to be confirmed' }}
                                @if($trip->start_date && $trip->end_date && ! $trip->end_date->isSameDay($trip->start_date))
                                    - {{ $trip->end_date->format('d M Y') }}
                                @endif
                            </p>
                            <h2>{{ $trip->title }}</h2>
                            <p class="trip-destination">{{ $trip->destination ?: 'Malaysia' }}</p>
                            <div class="trip-metrics">
                                <span>{{ $trip->items->count() }} {{ Str::plural('item', $trip->items->count()) }}</span>
                                <span class="carbon-chip">Estimated: {{ number_format($trip->total_carbon_kg, 1) }} kg CO<sub>2</sub>e</span>
                            </div>
                            <a href="{{ route('itineraries.show', $trip) }}" class="button button-outline">View itinerary <span aria-hidden="true">→</span></a>
                        </div>
                    </article>
                @endforeach
            </section>
        @endif
    </div>
</main>

<dialog class="app-dialog" id="trip-dialog" aria-labelledby="trip-dialog-title">
    <form method="POST" action="{{ route('itineraries.store') }}" class="dialog-form">
        @csrf
        <div class="dialog-heading">
            <div>
                <span class="eyebrow">New trip</span>
                <h2 id="trip-dialog-title">Create an itinerary</h2>
            </div>
            <button type="button" class="icon-button" aria-label="Close" onclick="this.closest('dialog').close()">×</button>
        </div>
        <label>
            Trip name
            <input name="title" required maxlength="120" value="{{ old('title') }}" placeholder="e.g. Penang Eco-Tour">
        </label>
        <label>
            Primary destination
            <input name="destination" maxlength="160" value="{{ old('destination') }}" placeholder="e.g. Penang, Malaysia">
        </label>
        <div class="form-grid">
            <label>
                Start date
                <input type="date" name="start_date" id="trip-start-date" min="{{ today()->toDateString() }}" value="{{ old('start_date') }}">
            </label>
            <label>
                End date
                <input type="date" name="end_date" id="trip-end-date" min="{{ today()->toDateString() }}" value="{{ old('end_date') }}">
            </label>
        </div>
        <label>
            Notes for this trip <span class="muted">(optional)</span>
            <textarea name="description" rows="3" maxlength="1000" placeholder="A short intention or travel note.">{{ old('description') }}</textarea>
        </label>
        <div class="dialog-actions">
            <button type="button" class="button button-quiet" onclick="this.closest('dialog').close()">Cancel</button>
            <button class="button button-primary" type="submit">Create itinerary</button>
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

@if($errors->any())
document.getElementById('trip-dialog').showModal();
@endif
</script>

</body>
</html>
