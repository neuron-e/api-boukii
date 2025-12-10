<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use App\Support\LocaleHelper;

class RecoverPassword extends Mailable
{
    private $user;

    /**
     * Create a new message instance.
     *
     * @param User $user
     */
    public function __construct($user)
    {
        $this->user = $user;
    }


    public function build()
    {
        $oldLocale = \App::getLocale();
        $userLocale = LocaleHelper::resolve(null, $this->user);
        \App::setLocale($userLocale);

        $templateView = 'mails.recoverPassword'; // Load the view file directly
        $footerView = 'mails.footer'; // Load the view file directly

        $templateData = [
            'userName' => trim($this->user->first_name . ' ' . $this->user->last_name),
            'actionURL' => env('APP_RESETPASSWORD_URL') . '/' . $this->user->recover_token .'?user='. $this->user->id ,
            'footerView' => $footerView,

            // SCHOOL DATA - none
            'schoolName' => '',
            'schoolLogo' => '',
            'schoolEmail' => '',
            'schoolConditionsURL' => '',
        ];

        $subject = __('emails.recoverPassword.subject');
/*        \App::setLocale($oldLocale);*/

        return $this->to($this->user->email)
            ->subject($subject)
            ->view($templateView)->with($templateData);
    }
}
