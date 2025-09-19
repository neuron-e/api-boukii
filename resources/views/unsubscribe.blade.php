<!DOCTYPE html>
<html lang="{{ $locale ?? app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? __('unsubscribe.title') }}</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f7fb; margin: 0; padding: 0; }
        .container { max-width: 560px; margin: 40px auto; background: #fff; border-radius: 8px; box-shadow: 0 6px 18px rgba(0,0,0,.06); overflow: hidden; }
        .header { background: #0d6efd; color: #fff; padding: 18px 22px; }
        .header h1 { margin: 0; font-size: 20px; }
        .content { padding: 22px; color: #333; }
        .status { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 12px; margin-bottom: 10px; }
        .status.success { background: #e7f6ec; color: #18794e; border: 1px solid #cdebd7; }
        .status.error { background: #fde8e8; color: #b42318; border: 1px solid #f5c2c0; }
        .footer { padding: 16px 22px; color: #666; font-size: 12px; border-top: 1px solid #eef0f4; }
        a { color: #0d6efd; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
    <meta name="robots" content="noindex,nofollow">
    <meta http-equiv="Cache-Control" content="no-store" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    <link rel="icon" href="data:,">
    <!-- Minimal, no external assets for email-safe landing -->
    <script>/* no scripts */</script>
    <noscript></noscript>
    <meta http-equiv="Content-Security-Policy" content="default-src 'none'; style-src 'unsafe-inline'; img-src data:;">
    <meta name="referrer" content="no-referrer">
    <meta name="color-scheme" content="light only">
    <meta name="supported-color-schemes" content="light">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $title ?? __('unsubscribe.title') }}</h1>
        </div>
        <div class="content">
            <div class="status {{ $status ?? 'error' }}">{{ ($status ?? 'error') === 'success' ? __('unsubscribe.status_success') : __('unsubscribe.status_notice') }}</div>
            <p>{{ $message ?? __('unsubscribe.result') }}</p>
            <p>{!! __('unsubscribe.contact', ['email' => '<a href="mailto:' . e(config('mail.from.address')) . '">' . e(config('mail.from.address')) . '</a>']) !!}</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Boukii. {{ __('unsubscribe.footer_rights') }}
        </div>
    </div>
</body>
</html>
