<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientsSchool;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UnsubscribeController extends Controller
{
    public function __invoke(Request $request)
    {
        $email = trim((string) $request->query('email', ''));
        $token = (string) $request->query('token', '');
        $schoolId = (int) $request->query('school_id', 0);
        $locale = $this->resolveLocale($request);

        app()->setLocale($locale);

        $status = 'error';
        $title = trans('unsubscribe.header_error');
        $message = trans('unsubscribe.missing_params');

        if ($email === '' || $token === '') {
            return view('unsubscribe', compact('status', 'title', 'message', 'locale'));
        }

        // If school_id is provided, validate token with school_id included
        if ($schoolId > 0) {
            $expected = md5($email . $schoolId . config('app.key'));
        } else {
            $expected = md5($email . config('app.key'));
        }

        if (!hash_equals($expected, $token)) {
            $message = trans('unsubscribe.invalid_link');
            return view('unsubscribe', compact('status', 'title', 'message', 'locale'));
        }

        $client = Client::where('email', $email)->first();
        if (!$client) {
            $message = trans('unsubscribe.email_not_found', ['email' => e($email)]);
            return view('unsubscribe', compact('status', 'title', 'message', 'locale'));
        }

        try {
            $updated = 0;
            $schoolName = '';

            if ($schoolId > 0) {
                // Unsubscribe from specific school
                $school = School::find($schoolId);
                if ($school) {
                    $schoolName = $school->name;
                    $clientSchool = ClientsSchool::where('client_id', $client->id)
                        ->where('school_id', $schoolId)
                        ->first();

                    if ($clientSchool && $clientSchool->accepts_newsletter) {
                        $clientSchool->accepts_newsletter = false;
                        $clientSchool->save();
                        $updated = 1;

                        // Log the unsubscribe action
                        Log::info("Newsletter unsubscribe: {$email} from school {$schoolName} (ID: {$schoolId})");
                    }
                }
            } else {
                // Legacy: Unsubscribe from all schools
                $clientSchools = ClientsSchool::where('client_id', $client->id)
                    ->where('accepts_newsletter', true)
                    ->get();

                foreach ($clientSchools as $clientSchool) {
                    $clientSchool->accepts_newsletter = false;
                    $clientSchool->save();
                    $updated++;
                }

                // Also update the client table for backwards compatibility
                if ($client->accepts_newsletter ?? false) {
                    $client->accepts_newsletter = false;
                    $client->save();
                }

                Log::info("Newsletter unsubscribe: {$email} from all schools ({$updated} subscriptions)");
            }

            $status = 'success';
            $title = trans('unsubscribe.header_success');
            if ($updated === 0) {
                if ($schoolName) {
                    $message = trans('unsubscribe.already_unsubscribed_school', [
                        'email' => e($email),
                        'school' => e($schoolName)
                    ]);
                } else {
                    $message = trans('unsubscribe.already_unsubscribed', ['email' => e($email)]);
                }
            } else {
                if ($schoolName) {
                    $message = trans('unsubscribe.unsubscribed_school', [
                        'email' => e($email),
                        'school' => e($schoolName)
                    ]);
                } else {
                    $message = trans('unsubscribe.unsubscribed', ['email' => e($email)]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Unsubscribe error for ' . $email . ': ' . $e->getMessage());
            $status = 'error';
            $title = trans('unsubscribe.problem');
            $message = trans('unsubscribe.try_later');
        }

        return view('unsubscribe', compact('status', 'title', 'message', 'locale'));
    }

    private function resolveLocale(Request $request): string
    {
        $supported = ['en', 'es', 'fr', 'de', 'it'];
        $param = (string) $request->query('lang', '');
        if (in_array($param, $supported, true)) {
            return $param;
        }

        $header = (string) $request->header('Accept-Language', '');
        if ($header) {
            foreach (explode(',', $header) as $part) {
                $code = strtolower(substr(trim($part), 0, 2));
                if (in_array($code, $supported, true)) {
                    return $code;
                }
            }
        }

        return config('app.locale', 'en');
    }
}
