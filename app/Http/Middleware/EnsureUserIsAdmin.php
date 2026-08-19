<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->session()->get('admin_authenticated') !== true) {
            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
