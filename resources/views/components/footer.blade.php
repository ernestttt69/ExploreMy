<footer class="custom-footer">
    <div class="container footer-grid">
        <div class="footer-brand">
            <div class="footer-logo"><img src="{{ asset('images/ExploreMy_icon.jpeg') }}" alt="ExploreMY logo"><strong>ExploreMY</strong></div>
            <p>{{ __('ui.footer.tagline') }}</p>
        </div>
        <div class="footer-column"><h2>{{ __('ui.footer.explore') }}</h2><a href="{{ route('dashboard') }}">{{ __('ui.nav.dashboard') }}</a><a href="{{ route('saved-places.index') }}">{{ __('ui.footer.saved') }}</a><a href="{{ route('transportation') }}">{{ __('ui.footer.transportation') }}</a></div>
        <div class="footer-column"><h2>{{ __('ui.footer.discover') }}</h2><a href="{{ route('about-malaysia') }}">{{ __('ui.footer.about') }}</a><a href="{{ route('profile') }}#preferences">{{ __('ui.footer.preferences') }}</a><a href="{{ route('profile') }}">{{ __('ui.footer.profile') }}</a></div>
        <div class="footer-column"><h2>{{ __('ui.footer.confidence') }}</h2><p>{{ __('ui.footer.confidence_text') }}</p></div>
    </div>
    <div class="container footer-bottom"><span>&copy; {{ date('Y') }} ExploreMY. {{ __('ui.footer.rights') }}</span><span>{{ __('ui.footer.made') }}</span></div>
</footer>
