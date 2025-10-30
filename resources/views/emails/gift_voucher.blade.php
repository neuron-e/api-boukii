<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('gift_voucher.subject', ['school' => $school->name ?? config('app.name')]) }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #1f2933; margin: 0; padding: 0; background-color: #f5f6f8; }
        .container { max-width: 640px; margin: 0 auto; padding: 32px 24px; background-color: #ffffff; }
        .header { text-align: center; margin-bottom: 24px; }
        .header h1 { margin: 0; font-size: 24px; color: #111827; }
        .meta { background-color: #f0f4f8; border-radius: 8px; padding: 16px; margin: 24px 0; }
        .meta-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .meta-row span { font-weight: 600; color: #374151; }
        .voucher-code { font-size: 24px; font-weight: bold; letter-spacing: 2px; color: #111827; }
        .footer { margin-top: 32px; font-size: 12px; color: #6b7280; text-align: center; }
        .btn { display: inline-block; padding: 12px 20px; background-color: #2563eb; color: #ffffff !important; text-decoration: none; border-radius: 6px; margin-top: 16px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>{{ __('gift_voucher.heading') }}</h1>
        <p>{{ __('gift_voucher.greeting', ['name' => $giftVoucher->recipient_name ?? __('gift_voucher.recipient_default')]) }}</p>
        <p>{{ __('gift_voucher.intro', ['school' => $school->name ?? config('app.name')]) }}</p>
    </div>

    <div class="meta">
        <div class="meta-row">
            <span>{{ __('gift_voucher.code_label') }}</span>
            <span class="voucher-code">{{ optional($giftVoucher->voucher)->code ?? $giftVoucher->code }}</span>
        </div>
        <div class="meta-row">
            <span>{{ __('gift_voucher.amount_label') }}</span>
            <span>{{ number_format($giftVoucher->amount, 2) }} {{ $giftVoucher->currency ?? 'CHF' }}</span>
        </div>
        @if($giftVoucher->expires_at)
            <div class="meta-row">
                <span>{{ __('gift_voucher.expires_label') }}</span>
                <span>{{ $giftVoucher->expires_at instanceof \Carbon\Carbon ? $giftVoucher->expires_at->format('d/m/Y') : \Carbon\Carbon::parse($giftVoucher->expires_at)->format('d/m/Y') }}</span>
            </div>
        @endif
    </div>

    @if($giftVoucher->personal_message)
        <p>{{ __('gift_voucher.message_label') }}</p>
        <blockquote>{{ $giftVoucher->personal_message }}</blockquote>
    @endif

    <p>{{ __('gift_voucher.instructions') }}</p>

    @if(config('app.url'))
        <p style="text-align: center;">
            <a class="btn" href="{{ rtrim(config('app.url'), '/') }}">{{ __('gift_voucher.button_label') }}</a>
        </p>
    @endif

    <p>{{ __('gift_voucher.thanks', ['school' => $school->name ?? config('app.name')]) }}</p>

    <div class="footer">
        <p>{{ __('gift_voucher.footer_notice') }}</p>
    </div>
</div>
</body>
</html>
