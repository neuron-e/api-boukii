<!DOCTYPE html>
<html lang="{{ $locale ?? app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-bottom: 3px solid #007bff;
        }
        .content {
            padding: 20px;
            background-color: #fff;
        }
        .unsubscribe-block {
            margin-top: 24px;
            padding: 12px 14px;
            background: #f6f7f9;
            border-left: 3px solid #9aa4b2;
            color: #555;
            font-size: 13px;
        }
        .unsubscribe-block a {
            color: #007bff;
            text-decoration: none;
        }
        .unsubscribe-block a:hover { text-decoration: underline; }
        .footer {
            background-color: #f8f9fa;
            padding: 15px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #dee2e6;
        }
        .logo {
            max-width: 150px;
            margin-bottom: 10px;
        }
        h1 {
            color: #007bff;
            margin: 0;
        }
        .unsubscribe {
            margin-top: 15px;
        }
        .unsubscribe a {
            color: #666;
            text-decoration: none;
        }
        .unsubscribe a:hover {
            text-decoration: underline;
        }
    </style>
    </head>
<body>
    <div class="header">
        <h1>{{ $subject }}</h1>
    </div>

    <div class="content">
        <p>{{ __('emails.newsletter.greeting', ['firstName' => $client->first_name]) }}</p>

        {!! $content !!}

        <div class="unsubscribe-block">
            {{ __('emails.newsletter.unsubscribe_intro') }}
            <a href="{{ url('/unsubscribe?email=' . $client->email . '&token=' . md5($client->email . $newsletter->school_id . config('app.key')) . '&school_id=' . $newsletter->school_id . '&lang=' . ($locale ?? app()->getLocale())) }}">
                {{ __('emails.newsletter.unsubscribe_link') }}
            </a>.
        </div>

        <p>{{ __('emails.newsletter.thanks') }}</p>
        <p>{{ __('emails.newsletter.regards') }}<br>{{ __('emails.newsletter.team') }}</p>
    </div>

    <div class="footer">
        <p>&copy; {{ date('Y') }} Boukii. {{ __('emails.newsletter.footer_rights') }}</p>
        <div class="unsubscribe">
            <a href="{{ url('/unsubscribe?email=' . $client->email . '&token=' . md5($client->email . $newsletter->school_id . config('app.key')) . '&school_id=' . $newsletter->school_id . '&lang=' . ($locale ?? app()->getLocale())) }}">
                {{ __('emails.newsletter.footer_unsubscribe') }}
            </a>
        </div>
    </div>
</body>
</html>

