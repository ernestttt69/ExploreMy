<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>@yield('title', 'ExploreMY')</title>

    {{-- Bootstrap CSS --}}
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    {{-- Your common website CSS --}}
    <link
        rel="stylesheet"
        href="{{ asset('css/dashboard.css') }}"
    >

    @stack('styles')
</head>

<body>
    @include('components.navbar')
    @if(request()->routeIs('route.*'))
        <x-page-back :href="route('saved-places.index')" :label="__('route_form.back_to_saved_places')" />
    @else
        <x-page-back :href="route('dashboard')" :label="__('ui.profile.back')" />
    @endif

    <main>
        @yield('content')
    </main>

    @include('components.footer')

    {{-- Bootstrap JavaScript --}}
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    ></script>

    @stack('scripts')
</body>
</html>
