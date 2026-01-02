<?php

namespace App\Mail;

use App\Models\GiftVoucher;
use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GiftVoucherDeliveredMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public GiftVoucher $giftVoucher;
    public School $school;
    /** @var string */
    public $locale;

    /**
     * Create a new message instance.
     */
    public function __construct(GiftVoucher $giftVoucher, School $school, string $locale = 'en')
    {
        $this->giftVoucher = $giftVoucher;
        $this->school = $school;
        $this->locale = $locale;
    }

    /**
     * Build the message.
     */
    public function build(): static
    {
        $previousLocale = app()->getLocale();
        app()->setLocale($this->locale);

        $subject = __('gift_voucher.subject', [
            'school' => $this->school->name ?? config('app.name')
        ]);

        $mail = $this->subject($subject)
            ->view('emails.gift_voucher')
            ->with([
                'giftVoucher' => $this->giftVoucher,
                'school' => $this->school,
                'locale' => $this->locale,
            ]);

        app()->setLocale($previousLocale);

        return $mail;
    }
}
