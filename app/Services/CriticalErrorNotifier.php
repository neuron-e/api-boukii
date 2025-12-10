<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CriticalErrorNotifier
{
    private string $recipient;

    public function __construct()
    {
        $this->recipient = config('mail.critical_recipient', 'andf1992@gmail.com');
    }

    public function notify(string $subject, array $context = [], \Throwable $exception = null): void
    {
        $body = $this->buildBody($subject, $context, $exception);

        try {
            Mail::raw($body, function ($message) use ($subject) {
                $message->to($this->recipient)
                    ->subject('[Boukii] Critical booking error: ' . $subject);
            });
            Log::alert('CRITICAL_BOOKING_ERROR_NOTIFIED', [
                'recipient' => $this->recipient,
                'subject' => $subject,
                'context' => $context,
            ]);
        } catch (\Exception $mailException) {
            Log::error('CRITICAL_BOOKING_ERROR_EMAIL_FAILED', [
                'recipient' => $this->recipient,
                'subject' => $subject,
                'error' => $mailException->getMessage(),
            ]);
        }
    }

    private function buildBody(string $subject, array $context, ?\Throwable $exception): string
    {
        $lines = [
            'Critical booking error detected.',
            'Subject: ' . $subject,
            'Context: ' . json_encode($context, JSON_PRETTY_PRINT),
        ];

        if ($exception) {
            $lines[] = 'Exception: ' . $exception->getMessage();
            $lines[] = 'File: ' . $exception->getFile() . ':' . $exception->getLine();
            $lines[] = 'Trace: ' . $exception->getTraceAsString();
        }

        return implode(PHP_EOL . PHP_EOL, $lines);
    }
}
