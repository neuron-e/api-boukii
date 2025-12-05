<?php

namespace App\Jobs;

use App\Models\Course;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TranslateCourseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private int $courseId,
        private array $source,
        private array $existingTranslations,
        private array $languages
    ) {
    }

    public function handle(): void
    {
        $deeplApiKey = config('services.deepl.key');
        $deeplApiUrl = config('services.deepl.url');

        if (!$deeplApiKey || !$deeplApiUrl) {
            Log::warning('TranslateCourseJob skipped: missing credentials', [
                'course_id' => $this->courseId,
            ]);
            return;
        }

        $texts = [
            $this->source['name'] ?? '',
            $this->source['short_description'] ?? '',
            $this->source['description'] ?? '',
        ];

        $responses = Http::pool(function ($pool) use ($texts, $deeplApiKey, $deeplApiUrl) {
            $requests = [];
            foreach ($this->languages as $lang) {
                $requests[] = $pool->asForm()
                    ->withHeaders(['Authorization' => 'DeepL-Auth-Key ' . $deeplApiKey])
                    ->timeout(4)
                    ->connectTimeout(2)
                    ->post($deeplApiUrl, [
                        'text' => $texts,
                        'target_lang' => strtoupper($lang),
                    ]);
            }
            return $requests;
        });

        $merged = $this->existingTranslations;

        foreach ($this->languages as $index => $lang) {
            $langKey = strtolower($lang);
            $resp = $responses[$index] ?? null;
            if (!$resp || !$resp->successful()) {
                Log::warning('TranslateCourseJob failed', [
                    'course_id' => $this->courseId,
                    'lang' => $lang,
                    'status' => $resp?->status(),
                    'body' => $resp?->json(),
                ]);
                continue;
            }
            $json = $resp->json();
            $translations = $json['translations'] ?? [];
            $merged[$langKey] = [
                'name' => $translations[0]['text'] ?? ($merged[$langKey]['name'] ?? $this->source['name'] ?? ''),
                'short_description' => $translations[1]['text'] ?? ($merged[$langKey]['short_description'] ?? $this->source['short_description'] ?? ''),
                'description' => $translations[2]['text'] ?? ($merged[$langKey]['description'] ?? $this->source['description'] ?? ''),
            ];
        }

        try {
            $course = Course::find($this->courseId);
            if ($course) {
                $course->update(['translations' => json_encode($merged)]);
            }
        } catch (\Throwable $e) {
            Log::error('TranslateCourseJob update failed', [
                'course_id' => $this->courseId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
