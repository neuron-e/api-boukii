<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

use App\Models\Language;

class UserRequired
{

    private function validator($user_type = null)
    {

        $validator = Validator::make([
            'user_type' => $user_type
        ], [
            'user_type' => ['nullable'],
        ]);

        $validator->after(function($validator) use ($user_type) {

            if($validator->errors()->isEmpty()) {

                $authUser = request()->user('sanctum') ?? Auth::user();
                if (!$authUser) {
                    return $validator->errors()->add('auth', 'Unauthorized');
                }

                // Validate user type
                if (!is_null($user_type)) {
                    $currentType = $authUser->type ?? null;
                    $allowed = array_filter(array_map('trim', is_array($user_type) ? $user_type : explode(',', (string) $user_type)));
                    $allowed = array_values(array_unique(array_merge($allowed, array_map('strval', $allowed))));
                    if ($currentType === null || !in_array((string) $currentType, $allowed, true)) {
                        return $validator->errors()->add('auth', 'Unauthorized');
                    }
                }

            }

        });

        if($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => null,
                'code' => 'unauthorized'
            ], 401)->throwResponse();
        }

        return $validator;

    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, $user_type = null)
    {
        $this->validator($user_type);

        // Set current user's language for messages
        $defaultLocale = config('app.fallback_locale');
        $myUser = $request->user('sanctum') ?? Auth::user();
        if ($myUser) {
            $userLang = ($myUser->language1_id) ? Language::find($myUser->language1_id) : null;
            $userLocale = $userLang ? $userLang->code : $defaultLocale;
            \App::setLocale($userLocale);
        } else {
            \App::setLocale($defaultLocale);
        }

        return $next($request);
    }
}
