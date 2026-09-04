@props(['href', 'label' => 'Back'])

<a href="{{ $href }}" class="page-back-control" aria-label="{{ $label }}">
    <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M9 7 4 12l5 5M5 12h9a6 6 0 0 1 6 6" />
    </svg>
    <span>{{ $label }}</span>
</a>
