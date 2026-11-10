<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class ForceHttpsOnPublicTunnel
{
    /**
     * Handle an incoming request.
     *
     * A tunnel terminates TLS and does not always forward X-Forwarded-Proto, so
     * requests arriving through the public rundown URL would otherwise emit
     * http:// asset links that the browser blocks on the https page.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $publicUrl = (string) config('app.rundown_public_url');

        if (str_starts_with($publicUrl, 'https://') && $request->getHost() === parse_url($publicUrl, PHP_URL_HOST)) {
            URL::forceScheme('https');
        }

        return $next($request);
    }
}
