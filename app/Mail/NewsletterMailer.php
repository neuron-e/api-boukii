<?php

namespace App\Mail;

use App\Models\Newsletter;
use App\Models\Client;
use App\Models\Language;
use Illuminate\Mail\Mailable;

class NewsletterMailer extends Mailable
{
    private $newsletter;
    private $client;
    private $schoolData;

    /**
     * Create a new message instance.
     *
     * @param Newsletter $newsletter
     * @param Client $client
     * @param $schoolData
     */
    public function __construct(Newsletter $newsletter, Client $client, $schoolData)
    {
        $this->newsletter = $newsletter;
        $this->client = $client;
        $this->schoolData = $schoolData;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // Apply that user's language - or default
        $defaultLocale = config('app.fallback_locale');
        $oldLocale = \App::getLocale();

        // Determine locale for recipient (same logic as in NewsletterController)
        $this->client->loadMissing('language1', 'language2', 'language3');
        $locale = ($this->client->language1?->code)
            ?? ($this->client->language2?->code)
            ?? ($this->client->language3?->code)
            ?? config('app.locale', 'en');

        \App::setLocale($locale);

        $templateView = 'emails.newsletter';

        $templateData = [
            'content' => $this->newsletter->content,
            'subject' => $this->newsletter->subject,
            'client' => $this->client,
            'newsletter' => $this->newsletter,
            'locale' => $locale,
        ];

        $subject = $this->newsletter->subject;

        // Use the same from configuration as booking emails
        return $this->to($this->client->email, $this->client->first_name . ' ' . $this->client->last_name)
            ->subject($subject)
            ->from('booking@boukii.ch', $this->schoolData->name ?? 'Boukii')
            ->view($templateView)
            ->with($templateData);
    }
}