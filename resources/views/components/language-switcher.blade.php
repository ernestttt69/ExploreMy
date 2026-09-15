<form method="POST" action="{{ route('language.update') }}" class="nav-language-switcher" aria-label="{{ __('ui.profile.language') }}">
    @csrf
    @foreach(['zh' => '中文', 'ms' => 'Melayu', 'en' => 'English'] as $locale => $label)
        <button type="submit" name="language" value="{{ $locale }}" lang="{{ $locale }}" aria-pressed="{{ app()->getLocale() === $locale ? 'true' : 'false' }}">{{ $label }}</button>
    @endforeach
</form>
@once
<style>
.nav-language-switcher{display:flex;align-items:center;gap:3px;flex-wrap:wrap;margin:0}
.nav-language-switcher button{border:1px solid transparent;background:transparent;color:#45665b;border-radius:7px;padding:5px 7px;font-size:12px;white-space:nowrap}
.nav-language-switcher button[aria-pressed="true"]{background:#e7f5ee;border-color:#b9d7ca;color:#126b49;font-weight:700}
.nav-language-switcher button:focus-visible{outline:2px solid #126b49;outline-offset:2px}
@media(max-width:600px){.top-header-bar>.d-flex{flex-wrap:wrap;gap:10px}.top-header-bar>.d-flex>.d-flex{flex-wrap:wrap;gap:8px!important}.nav-language-switcher button{padding:4px}}
</style>
@endonce
