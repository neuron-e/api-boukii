<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\CourseTranslateRequest;
use App\Models\Course;

/**
 * Class BookingController
 */

class TranslationAPIController extends AppBaseController
{

    public function __construct()
    {

    }

    public function translateCourse($courseId, CourseTranslateRequest $request)
    {
        $deeplApiKey = config('services.deepl.key');
        $deeplApiUrl = config('services.deepl.url');

        if (!$deeplApiKey || !$deeplApiUrl) {
            return $this->sendError('DeepL credentials missing', [], 500);
        }

        $course = Course::find($courseId);
        if (!$course) {
            return $this->sendError('Course not found', [], 404);
        }

        // Multi-tenant guard: ensure course belongs to any school of the authenticated user
        $user = $request->user();
        $userSchoolIds = collect();
        if ($user) {
            try {
                $userSchoolIds = collect($user->schools()->pluck('schools.id')->toArray());
            } catch (\Throwable $e) {
                // ignore and fallback to empty
            }
        }
        // If user has school_ids, enforce membership; if none, skip to avoid false negatives on limited tokens
        $isAdmin = isset($user?->type) && (int)$user->type === 0;
        if (!$isAdmin && $userSchoolIds->isNotEmpty() && !$userSchoolIds->contains((int) $course->school_id)) {
            return $this->sendError('This action is unauthorized.', [], 403);
        }

        $source = $request->only(['name', 'short_description', 'description']);
        $languages = $request->input('languages', ['fr', 'en', 'de', 'es', 'it']);

        $texts = [
            $source['name'],
            $source['short_description'],
            $source['description'],
        ];

        $responses = Http::pool(function ($pool) use ($languages, $deeplApiKey, $deeplApiUrl, $texts) {
            $requests = [];
            foreach ($languages as $lang) {
                $requests[] = $pool->asForm()
                    ->withHeaders(['Authorization' => 'DeepL-Auth-Key ' . $deeplApiKey])
                    ->timeout(10)
                    ->connectTimeout(5)
                    ->post($deeplApiUrl, [
                        'text' => $texts,
                        'target_lang' => strtoupper($lang),
                    ]);
            }
            return $requests;
        });

        $updatedTranslations = $course->translations;
        if (is_string($updatedTranslations)) {
            $decoded = json_decode($updatedTranslations, true);
            $updatedTranslations = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }
        $updatedTranslations = is_array($updatedTranslations) ? $updatedTranslations : [];

        $fallbackQueued = false;

        foreach ($languages as $index => $lang) {
            $langKey = strtolower($lang);
            $resp = $responses[$index] ?? null;
            if (!$resp || !$resp->successful()) {
                Log::channel('integrations')->warning('translateCourse sync failed', [
                    'course_id' => $courseId,
                    'lang' => $lang,
                    'status' => $resp?->status(),
                    'body' => $resp?->json(),
                ]);
                $fallbackQueued = true;
                continue;
            }
            $json = $resp->json();
            $translations = $json['translations'] ?? [];
            $updatedTranslations[$langKey] = [
                'name' => $translations[0]['text'] ?? ($updatedTranslations[$langKey]['name'] ?? $source['name'] ?? ''),
                'short_description' => $translations[1]['text'] ?? ($updatedTranslations[$langKey]['short_description'] ?? $source['short_description'] ?? ''),
                'description' => $translations[2]['text'] ?? ($updatedTranslations[$langKey]['description'] ?? $source['description'] ?? ''),
            ];
        }

        $course->update(['translations' => json_encode($updatedTranslations)]);

        if ($fallbackQueued && config('queue.default') !== 'sync') {
            \App\Jobs\TranslateCourseJob::dispatch(
                $courseId,
                $source,
                $updatedTranslations,
                $languages
            )->onQueue('translations');
        }

        return $this->sendResponse([
            'translations' => $updatedTranslations,
            'fallback_enqueued' => $fallbackQueued && config('queue.default') !== 'sync',
        ], 'Translations updated');
    }

    /**
     * @OA\Post(
     *      path="/translate",
     *      summary="Traduce texto a un idioma especificado",
     *      tags={"Translation"},
     *      description="Envía texto para ser traducido al idioma especificado usando la API de DeepL.",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"text", "target_lang"},
     *              @OA\Property(property="text", type="string", example="Hello World!"),
     *              @OA\Property(property="target_lang", type="string", example="ES")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Operación exitosa",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="translations",
     *                  type="array",
     *                  @OA\Items(
     *                      @OA\Property(property="detected_source_language", type="string", example="EN"),
     *                      @OA\Property(property="text", type="string", example="¡Hola Mundo!")
     *                  )
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=400,
     *          description="Solicitud incorrecta",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="error",
     *                  type="string",
     *                  example="El texto a traducir es obligatorio."
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=500,
     *          description="Error interno del servidor",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="error",
     *                  type="string",
     *                  example="Ocurrió un error al realizar la traducción."
     *              )
     *          )
     *      )
     * )
     */
    public function translate(Request $request)
    {
        // Validar la solicitud entrante
        $request->validate([
            'text' => 'required|string',
            'target_lang' => 'required|string',
        ]);

        // Obtener la clave de API y la URL de la API de DeepL desde el archivo de entorno
        $deeplApiKey = env('DEEPL_API_KEY');
        $deeplApiUrl = env('DEEPL_API_URL');

        try {
            // Realizar la solicitud a la API de DeepL
            $response = Http::withHeaders([
                'Authorization' => 'DeepL-Auth-Key ' . $deeplApiKey,
            ])->post($deeplApiUrl, [
                'text' => [$request->input('text')],  // Convertir el texto a un array
                'target_lang' => $request->input('target_lang'),
            ]);

            // Verificar si la solicitud fue exitosa
            if ($response->successful()) {
                return $this->sendResponse($response->json(), 'Translation retrieved successfully');
            }
            $responseJson = $response->json();
            if (is_null($responseJson)) {
                Log::channel('integrations')->error('Error translate: No response body', ['status' => $response->status()]);
            } else {
                Log::channel('integrations')->error('Error translate', ['response' => $responseJson]);
            }
            return $this->sendError('Error retrieving Translation', 500);
        } catch (\Exception $e) {
            Log::channel('integrations')->error($e->getMessage(), $e->getTrace());
            return $this->sendError('Error retrieving Translation', 500);
        }
    }


}

