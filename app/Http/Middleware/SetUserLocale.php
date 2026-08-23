<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Carbon;

class SetUserLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = auth()->user()->preferred_language ?? session('locale', config('app.locale'));
        if (! in_array($locale, ['en', 'ms', 'zh'], true)) {
            $locale = 'en';
        }
        App::setLocale($locale);
        Carbon::setLocale($locale === 'zh' ? 'zh_CN' : $locale);
        session(['locale' => $locale]);

        return $next($request);
    }
}
