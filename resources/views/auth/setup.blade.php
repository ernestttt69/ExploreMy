<!DOCTYPE html><html lang="{{ app()->getLocale() }}"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>ExploreMY</title>
<link rel="stylesheet" href="{{ asset('css/dashboard.css') }}"><link rel="stylesheet" href="{{ asset('css/profile.css') }}">
<style>.setup-page{max-width:720px;margin:40px auto;padding:24px}.setup-page form{display:grid;gap:24px}.setup-page h1{font-size:30px}.setup-page p{line-height:1.7}.setup-page button{padding:14px;background:#20583f;border:0;border-radius:10px;color:white;font-size:16px}.setup-page select{font-size:16px;padding:10px}</style></head><body>
<main class="setup-page"><h1>{{ __('setup.title') }}</h1><p>{{ __('setup.intro') }}</p>
@if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form method="POST" action="{{ route('setup.store') }}">@csrf
<label class="select-setting"><span>{{ __('ui.profile.language') }} / Language</span><select name="preferred_language" required>@foreach(['zh' => '中文', 'ms' => 'Melayu', 'en' => 'English'] as $code => $label)<option value="{{ $code }}" @selected(old('preferred_language', auth()->user()->preferred_language) === $code)>{{ $label }}</option>@endforeach</select></label>
<section><h2>{{ __('ui.profile.place_preferences') }}</h2><p>{{ __('setup.optional') }}</p><div class="simple-preferences">@foreach($categories as $category)<label class="simple-preference"><input type="checkbox" name="preferences[]" value="{{ $category->preference_id }}" @checked(in_array($category->preference_id, old('preferences', [])))><strong>{{ $category->localized_name }}</strong></label>@endforeach</div></section>
<p>{{ __('setup.change_later') }}</p><button type="submit">{{ __('setup.finish') }}</button>
</form></main></body></html>
