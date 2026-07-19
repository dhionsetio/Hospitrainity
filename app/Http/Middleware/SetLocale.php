<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the user's preferred UI locale for the current request.
 *
 * The preference is stored in the session (see the `locale.switch` route) and
 * falls back to the application default (config/app.php `locale`). Only locales
 * that ship with a matching lang/{locale}.json file are honoured so an
 * arbitrary session value can never force an unsupported locale.
 */
class SetLocale
{
    /**
     * Locales the application ships translations for.
     *
     * @var array<int, string>
     */
    public const SUPPORTED = ['en', 'id'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) $request->session()->get('locale', config('app.locale'));

        if (in_array($locale, self::SUPPORTED, true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
