<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     * 
     * Añade headers de seguridad importantes para proteger la aplicación
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Prevenir clickjacking
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Prevenir MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Activar XSS protection del navegador
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Forzar HTTPS en producción
        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Content Security Policy - más permisiva para Log Viewer
        if ($request->is('logs') || $request->is('logs/*')) {
            // CSP relajada para Log Viewer (necesita CDNs externos)
            $csp = "default-src 'self'; " .
                   "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://js.payrexx.com https://code.jquery.com https://maxcdn.bootstrapcdn.com https://cdn.datatables.net https://use.fontawesome.com; " .
                   "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://maxcdn.bootstrapcdn.com https://cdn.datatables.net; " .
                   "img-src 'self' data: https:; " .
                   "font-src 'self' https://fonts.gstatic.com https://use.fontawesome.com; " .
                   "connect-src 'self' https://api.payrexx.com;";
        } else {
            // CSP básica para el resto de la aplicación
            $csp = "default-src 'self'; " .
                   "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://js.payrexx.com; " .
                   "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
                   "img-src 'self' data: https:; " .
                   "font-src 'self' https://fonts.gstatic.com; " .
                   "connect-src 'self' https://api.payrexx.com;";
        }

        $response->headers->set('Content-Security-Policy', $csp);

        // Referrer Policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Feature Policy / Permissions Policy
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        return $response;
    }
}