<?php

namespace App\Http\Middleware;

use Closure;

class ResponseMiddleware
{
    public function __construct()
    {

    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        $response = $next($request);

        $response->headers->add([
           'X-Powered-Server' => phpversion() . 'p/' . swoole_version() . 's'
        ]);

        return $response;
    }
}
