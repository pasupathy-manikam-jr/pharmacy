<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Signed-in staff get their saved language; visitors (login page) get the one picked this session.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $locale = $user instanceof User ? $user->locale : $request->session()->get('locale');

        app()->setLocale(Locales::valid($locale) ? $locale : Locales::DEFAULT);

        return $next($request);
    }
}
