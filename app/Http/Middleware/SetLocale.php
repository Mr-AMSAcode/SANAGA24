<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Locales the site actually ships translations for. Kept here (rather
     * than scanning lang/ at runtime) so an unexpected session value can
     * never activate a locale with no translation files behind it.
     */
    public const SUPPORTED = ['en', 'fr'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale', config('app.locale'));

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = config('app.locale');
        }

        App::setLocale($locale);

        // Carbon has its own locale independent of the app's — without
        // this, ->isoFormat() dates (used in the nav) would stay English
        // even after switching the rest of the site to French.
        Carbon::setLocale($locale);

        return $next($request);
    }
}
