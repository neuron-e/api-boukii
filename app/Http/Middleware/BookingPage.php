<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

use App\Models\School;

class BookingPage
{

    /**
     * Check if request contains the "slug" of an active School, or goodbye.
     *
     * @param Request $request
     */
    public function handle(Request $request, Closure $next)
    {
        // Allow CORS preflight requests to pass with proper headers
        if ($request->isMethod('OPTIONS')) {
            $origin = $request->headers->get('Origin', '*');
            $reqHeaders = $request->headers->get('Access-Control-Request-Headers',
                'Accept, Authorization, Content-Type, X-Requested-With, slug, X-School-Slug, X-CSRF-TOKEN, X-Socket-ID'
            );

            return response()->noContent(204)->withHeaders([
                'Access-Control-Allow-Origin' => $origin,
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => $reqHeaders,
                'Access-Control-Max-Age' => '86400',
            ]);
        }

        // Support slug through multiple headers or query param
        $slug = trim(
            $request->headers->get(
                'slug',
                $request->headers->get('X-School-Slug', $request->query('slug', ''))
            )
        );

        $maybeSchool = (strlen($slug) > 0) ? School::where('slug', $slug)->where('Active', 1)->first() : null;

        if(!$maybeSchool) {
            return response( 'Wrong slug, no school', 404);
        }

        $request->attributes->set('school', $maybeSchool);

        return $next($request);

    }

}
