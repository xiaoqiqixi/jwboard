<?php

namespace App\Http\Middleware;

use Closure;

class CORS
{
    public function handle($request, Closure $next)
    {
        $origin = $request->header('origin');
        $allowedOrigins = array_filter(array_map(function ($url) {
            $parts = parse_url($url);
            if (!$parts || empty($parts['scheme']) || empty($parts['host'])) return null;
            return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        }, [config('v2board.app_url')]));

        if (!$origin || !in_array($origin, $allowedOrigins, true)) {
            return $next($request);
        }

        $response = $request->isMethod('OPTIONS') ? response('', 204) : $next($request);
        $response->header('Access-Control-Allow-Origin', $origin);
        $response->header('Access-Control-Allow-Methods', 'GET,POST,OPTIONS,HEAD');
        $response->header('Access-Control-Allow-Headers', 'Origin,Content-Type,Accept,Authorization,X-Request-With');
        $response->header('Access-Control-Max-Age', 10080);
        $response->header('Vary', 'Origin');

        return $response;
    }
}
