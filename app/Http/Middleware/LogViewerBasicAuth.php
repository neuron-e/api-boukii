<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogViewerBasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $username = env('LOG_VIEWER_USER');
        $password = env('LOG_VIEWER_PASSWORD');

        if (!$username || !$password) {
            return response('Log viewer credentials not configured', 403);
        }

        $user = $request->getUser();
        $pass = $request->getPassword();

        if ($user !== $username || $pass !== $password) {
            $headers = ['WWW-Authenticate' => 'Basic realm="Log Viewer"'];
            return response('Unauthorized', 401, $headers);
        }

        return $next($request);
    }
}
