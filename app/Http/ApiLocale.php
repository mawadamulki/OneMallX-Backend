<?php

namespace App\Http;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class ApiLocale
{
    /**
     * Set application locale for API requests.
     */
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->query('lang');

        if (! $locale) {
            $locale = $request->header('Accept-Language');
        }

        $available = ['en', 'ar'];

        if (! in_array($locale, $available, true)) {
            $locale = config('app.locale');
        }

        App::setLocale($locale);

        return $next($request);
    }
}

