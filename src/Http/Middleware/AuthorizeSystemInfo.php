<?php

namespace Jturazzi\SystemInfo\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeSystemInfo
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('system-info.token');

        // The route stays disabled until a token is configured.
        abort_if($expected === '', 404);

        $given = (string) $request->bearerToken();

        abort_unless($given !== '' && hash_equals($expected, $given), 401);

        return $next($request);
    }
}
