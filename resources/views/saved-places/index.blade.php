<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Places - ExploreMY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}"><link rel="stylesheet" href="{{ asset('css/saved-places.css') }}">
</head>
<body>
@include('components.navbar')
<main class="container saved-places-page">
    <div class="saved-places-heading">
        <div><p class="page-kicker">Your travel collection</p><h1>Saved Places</h1><p class="page-description">{{ $savedPlaces->count() }} {{ Str::plural('place', $savedPlaces->count()) }} saved for your next Malaysian adventure.</p></div>
        <a href="{{ route('profile') }}" class="btn-back">&larr; Back to Profile</a>
    </div>
    @if(session('success'))<div class="saved-alert">{{ session('success') }}</div>@endif

    @if($savedPlaces->isNotEmpty())
        <div class="saved-grid">
            @foreach($savedPlaces as $place)
                <article class="place-card">
                    <div class="place-card-visual">@if($place->image_path)<img src="{{ $place->image_path }}" alt="{{ $place->attraction_name }}">@else<strong>{{ Str::upper(Str::substr($place->attraction_name,0,2)) }}</strong>@endif<span class="state-mark">{{ $place->state->state_name ?? 'Malaysia' }}</span><span class="rating">&#9733; {{ number_format($place->rating,1) }}</span></div>
                    <div class="place-content">
                        <div class="place-card-top">
                            <div class="category-list">@foreach($place->categories as $category)<span class="place-category">{{ $category }}</span>@endforeach</div>
                            <button type="button" class="saved-heart" data-remove-url="{{ route('saved-places.destroy',$place) }}" data-place-name="{{ $place->attraction_name }}" aria-label="Remove {{ $place->attraction_name }} from saved places" title="Saved — click to remove">&hearts;</button>
                        </div>
                        <h2>{{ $place->attraction_name }}</h2>
                        <p class="place-location"><span>&#9679;</span> {{ $place->location }}</p>
                        <p class="place-description">{{ $place->description }}</p>
                        <dl class="place-meta"><div><dt>Entrance fee</dt><dd>{{ $place->entrance_fee }}</dd></div><div><dt>Budget</dt><dd>{{ $place->budget_level }}</dd></div></dl>
                        <details class="hours"><summary>View operating hours <span>&darr;</span></summary><pre>{{ $place->operating_hours ?: 'Operating hours unavailable' }}</pre></details>
                    </div>
                </article>
            @endforeach
        </div>
    @else
        <div class="empty-state"><div class="empty-heart">&hearts;</div><h2>No saved places yet</h2><p>Attractions you save will appear here.</p></div>
    @endif
</main>

<dialog class="remove-dialog" id="removeSavedDialog">
    <form method="POST" id="removeSavedForm">@csrf @method('DELETE')
        <button type="button" class="remove-dialog-close" data-close-dialog aria-label="Close">&times;</button>
        <span class="remove-heart" aria-hidden="true">&hearts;</span>
        <h2>Remove saved place?</h2>
        <p>Are you sure you want to remove <strong id="removePlaceName"></strong> from your saved places?</p>
        <small>The attraction will remain available. Only your saved connection will be removed.</small>
        <div class="remove-dialog-actions"><button type="button" data-close-dialog>Keep saved</button><button type="submit">Remove place</button></div>
    </form>
</dialog>

@include('components.footer')
<script>
const removeDialog=document.getElementById('removeSavedDialog');
const removeForm=document.getElementById('removeSavedForm');
document.querySelectorAll('[data-remove-url]').forEach(button=>button.addEventListener('click',()=>{
    removeForm.action=button.dataset.removeUrl;
    document.getElementById('removePlaceName').textContent=button.dataset.placeName;
    removeDialog.showModal();
}));
document.querySelectorAll('[data-close-dialog]').forEach(button=>button.addEventListener('click',()=>removeDialog.close()));
removeDialog.addEventListener('click',event=>{if(event.target===removeDialog)removeDialog.close()});
</script>
</body></html>
