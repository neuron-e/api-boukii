@php
    $course = $activity['course'] ?? null;
    $dates = $activity['dates'] ?? [];
    $primaryDate = $dates[0] ?? null;
    $firstMonitor = $activity['monitors'][0] ?? null;
    $primaryBookingUser = null;
    foreach ($dates as $date) {
        if (!empty($date['booking_users'])) {
            $primaryBookingUser = $date['booking_users'][0];
            break;
        }
    }
    $participantNames = collect($activity['utilizers'] ?? [])
        ->map(fn($u) => trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')))
        ->filter()
        ->unique()
        ->values();
    $participantLabel = $participantNames->isNotEmpty() ? $participantNames->join(', ') : null;
    $participantCount = max(count($activity['utilizers'] ?? []), 1);
    $meetingPointName = $booking->meeting_point ?? $course->meeting_point ?? null;
    $meetingPointAddress = $booking->meeting_point_address ?? $course->meeting_point_address ?? null;
    $meetingPointInstructions = $booking->meeting_point_instructions ?? $course->meeting_point_instructions ?? null;
    $basePrice = $activity['price_base'] ?? max(($activity['total'] ?? 0) - ($activity['extra_price'] ?? 0), 0);
    $extrasPrice = $activity['extra_price'] ?? 0;
    $totalPrice = $activity['total'] ?? ($activity['price'] ?? 0);
    $forceDatePrice = $forceDatePrice ?? false;
    $degree = $activity['sportLevel'] ?? null;
    if (is_array($degree)) {
        $degreeLabel = $degree['name'] ?? $degree['annotation'] ?? $degree['level'] ?? $degree['league'] ?? null;
    } elseif (is_object($degree)) {
        $degreeLabel = $degree->name ?? $degree->annotation ?? $degree->level ?? $degree->league ?? null;
    } else {
        $degreeLabel = null;
    }
    $showDegree = $course && (int) $course->course_type === 1 && $degreeLabel;
    $bookingUserForQr = $primaryBookingUser;
    if (!$bookingUserForQr) {
        foreach ($dates as $date) {
            if (!empty($date['booking_users'])) {
                $bookingUserForQr = $date['booking_users'][0];
                break;
            }
        }
    }
    $qrToken = $bookingUserForQr ? \App\Services\TeachScanTokenService::makeToken($bookingUserForQr) : null;
    $qrPng = $qrToken ? app(\App\Services\QrCodeService::class)->png($qrToken, 110) : null;
@endphp

<table width="100%" cellpadding="0" cellspacing="0" border="0" class="center-on-narrow">
    <tr>
        <td valign="top" align="center" style="padding-top:20px; padding-bottom:0px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" width="580"
                   style="margin: auto; width:580px" class="email-container">
                <tr>
                    <td align="center" valign="top" style="padding:15px 20px 15px 20px;" bgcolor="#f4f4f4">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                            <tr>
                                <td>
                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                        <tr>
                                            <td align="left" valign="middle"
                                                style="font-size:24px; line-height:29px; padding:0px 0px 15px 0px;">
                                                <font face="Arial, Helvetica, sans-serif"
                                                      style="font-size:24px; line-height:29px; color:#d2d2d2; font-weight:bold;">
                                                    {{ __('emails.bookingCreate.activity') }}</font>
                                            </td>
                                            <td width="50" align="right" valign="middle"
                                                style="font-size:24px; line-height:29px; padding:0px 0px 15px 0px;">
                                                <font face="Arial, Helvetica, sans-serif"
                                                      style="font-size:24px; line-height:29px; color:#d9d9d9; font-weight:bold;">
                                                    {{ $loopIndex + 1 }}</font>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                            <tr>
    <td valign="top" class="left-on-narrow">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td width="100" align="left" style="font-size:16px; line-height:21px; padding:0px 0px;">
                    <font face="Arial, Helvetica, sans-serif" style="font-size:16px; line-height:21px; color:#000000;">
                        {{ __('emails.bookingCreate.type') }}</font>
                </td>
                <td align="left" style="font-size:18px; line-height:23px; padding:0px 0px;">
                    <font face="Arial, Helvetica, sans-serif" style="font-size:18px; line-height:23px; color:#000000; font-weight:bold;">
                        {{ optional($course)->name }}
                    </font>
                </td>
            </tr>
            @if($showDegree)
                <tr>
                    <td width="100" align="left" style="font-size:14px; line-height:19px; padding:0px 0px;">
                        <font face="Arial, Helvetica, sans-serif" style="font-size:14px; line-height:19px; color:#000000;">
                            {{ __('emails.bookingCreate.degree') }}</font>
                    </td>
                    <td align="left" style="font-size:14px; line-height:19px; padding:0px 0px;">
                        <font face="Arial, Helvetica, sans-serif" style="font-size:14px; line-height:19px; color:#000000;">
                            <strong>{{ $degreeLabel }}</strong>
                        </font>
                    </td>
                </tr>
            @endif
            <tr>
                    <td width="100" align="left" style="font-size:14px; line-height:19px; padding:0px 0px;">
                        <font face="Arial, Helvetica, sans-serif" style="font-size:14px; line-height:19px; color:#000000;">
                            {{ __('emails.bookingCreate.date') }}</font>
                    </td>
                    <td align="left" style="font-size:14px; line-height:19px; padding:0px 0px;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                            @foreach ($dates as $date)
                                @php
                                      $perDatePrice = $date['price'] ?? $booking->calculateDatePrice($course, $date, $forceDatePrice);
                                    $perDateExtras = $date['extra_price'] ?? 0;
                                    $monitorName = !empty($date['monitor']) ? $date['monitor']->full_name : null;
                                    $dateFormat = \Carbon\Carbon::parse($date['date'])->format('F d, Y');
                                    $dateParticipants = collect($date['utilizers'] ?? [])
                                        ->map(fn($u) => trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')))
                                        ->filter()
                                        ->join(', ');
                                @endphp
                                <tr>
                                    <td style="font-size:14px; line-height:19px; padding:2px 0;">
                                        <font face="Arial, Helvetica, sans-serif" style="font-size:14px; line-height:19px; color:#000000;">
                                            <strong>{{ $dateFormat }}</strong><br>
                                            {{ $date['startHour'] }} - {{ $date['endHour'] }}<br>
                                            {{ __('emails.bookingCreate.monitor') }} {{ $monitorName ?? 'NDF' }}
                                        </font>
                                    </td>
                                    <td align="right" style="font-size:14px; line-height:19px; padding:2px 0;">
                                        <font face="Arial, Helvetica, sans-serif" style="font-size:14px; line-height:19px; color:#000000; font-weight:bold;">
                                            {{ number_format($perDatePrice, 2, '.', '') }} {{ $booking->currency }}
                                        </font>
                                    </td>
                                </tr>
                                @if($perDateExtras > 0)
                                    <tr>
                                        <td colspan="2" style="padding:0px 0px 2px 0px;">
                                            <font face="Arial, Helvetica, sans-serif" style="font-size:12px; line-height:16px; color:#555;">
                                                <strong>{{ __('emails.bookingCreate.extras') }}</strong>
                                                {{ number_format($perDateExtras, 2, '.', '') }} {{ $booking->currency }}
                                            </font>
                                        </td>
                                    </tr>
                                @endif
                                @endforeach
                            @if(!empty($activity['utilizers']))
                                <tr>
                                    <td colspan="2" style="padding-top:10px;">
                                        <font face="Arial, Helvetica, sans-serif" style="font-size:13px; line-height:18px; color:#555;">
                                            <strong>{{ __('emails.bookingCreate.plural_participants') }}</strong>
                                            {{ collect($activity['utilizers'])->map(fn($u) => trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')))->filter()->join(', ') }}
                                        </font>
                                    </td>
                                </tr>
                            @endif
                        </table>
                    </td>
            </tr>
            @if($meetingPointName || $meetingPointAddress || $meetingPointInstructions)
                <tr>
                    <td width="100" align="left" style="font-size:14px; line-height:19px; padding:0px 0px;">
                        <font face="Arial, Helvetica, sans-serif" style="font-size:14px; line-height:19px; color:#000000;">
                            {{ __('emails.bookingCreate.meeting_point') }}</font>
                    </td>
                    <td align="left" style="font-size:14px; line-height:19px; padding:0px 0px;">
                        <font face="Arial, Helvetica, sans-serif" style="font-size:14px; line-height:19px; color:#000000;">
                            @if($meetingPointName)
                                <strong>{{ $meetingPointName }}</strong><br>
                            @endif
                            @if($meetingPointAddress)
                                {{ __('emails.bookingCreate.meeting_point_address') }} {{ $meetingPointAddress }}<br>
                            @endif
                            @if($meetingPointInstructions)
                                {{ __('emails.bookingCreate.meeting_point_instructions') }} {!! nl2br(e($meetingPointInstructions)) !!}
                            @endif
                        </font>
                    </td>
                </tr>
            @endif
            <tr>
                <td width="100" align="left" style="font-size:14px; line-height:19px; padding:0px 0px;display:block">
                    <font face="Arial, Helvetica, sans-serif" style="font-size:14px; line-height:19px; color:#000000;">
                        {{ __('emails.bookingCreate.participant') }}</font>
                </td>
                    <td align="left" style="font-size:14px; line-height:19px; padding:0px 0px;">
                        <font face="Arial, Helvetica, sans-serif" style="font-size:14px; line-height:19px; color:#000000;">
                            @if($participantLabel)
                                <strong>{{ $participantLabel }}</strong>
                                @if($participantCount > 1)
                                    {{ '(' . $participantCount . ' total)' }}
                                @endif
                            @else
                                <strong>{{ __('emails.bookingCreate.participant_na') }}</strong>
                            @endif
                        </font>
                    </td>
            </tr>
        </table>
    </td>
                                <td valign="top" width="110" class="left-on-narrow" align="center">
                                    @if ($qrToken && $qrPng)
                                        <img src="{{ $message->embedData($qrPng, 'qr-' . ($bookingUserForQr->id ?? '') . '.png', 'image/png') }}" alt="QR Code" style="width: 110px; height: 110px;">
                                    @endif
                                </td>
                            </tr>
                        </table>
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                            <tr>
                                <td style="padding: 10px 0px;">
                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                        <tr>
                                            <td style="width: 100%; border-bottom: 1px solid #d2d2d2;"></td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                            <tr>
                                <td valign="top">
                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0"
                                           width="100%">
                                        <tr>
                                            <td width="100" align="left"
                                                style="font-size:14px; line-height:19px; padding:0px 0px;">
                                                <font face="Arial, Helvetica, sans-serif"
                                                      style="font-size:14px; line-height:19px; color:#000000;">
                                                    {{ __('emails.bookingCreate.price') }}</font>
                                            </td>
                                            <td align="right"
                                                style="font-size:14px; line-height:19px; padding:0px 0px;">
                                                <font face="Arial, Helvetica, sans-serif"
                                                      style="font-size:14px; line-height:19px; color:#000000;">
                                                    {{ $basePrice }} CHF
                                                </font>
                                            </td>
                                        </tr>
                                        @if($extrasPrice > 0)
                                            <tr>
                                                <td width="100" align="left"
                                                    style="font-size:14px; line-height:19px; padding:0px 0px;">
                                                    <font face="Arial, Helvetica, sans-serif"
                                                          style="font-size:14px; line-height:19px; color:#000000;">
                                                        {{ __('emails.bookingCreate.extras') }}</font>
                                                </td>
                                                <td align="right"
                                                    style="font-size:14px; line-height:19px; padding:0px 0px;">
                                                    <font face="Arial, Helvetica, sans-serif"
                                                          style="font-size:14px; line-height:19px; color:#000000;">
                                                        {{ $extrasPrice }} CHF
                                                    </font>
                                                </td>
                                            </tr>
                                        @endif
                                    </table>
                                </td>
                            </tr>
                        </table>
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                            <tr>
                                <td style="padding: 10px 0px;">
                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                        <tr>
                                            <td style="width: 100%; border-bottom: 1px solid #d2d2d2;"></td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                            <tr>
                                <td width="100" align="left"
                                    style="font-size:16px; line-height:21px; font-weight: bold; padding:0px 0px;">
                                    <font face="Arial, Helvetica, sans-serif"
                                          style="font-size:16px; line-height:21px; color:#000000; font-weight: bold;">
                                        {{ __('emails.bookingCreate.total') }}
                                    </font>
                                </td>
                                <td align="right"
                                    style="font-size:16px; line-height:21px; color:#000000; font-weight: bold; padding:0px 0px;">
                                    <font face="Arial, Helvetica, sans-serif"
                                          style="font-size:16px; line-height:21px; color:#000000; font-weight: bold;">
                                        {{ $totalPrice }} CHF
                                    </font>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
