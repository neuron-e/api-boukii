@extends('mailsv2.newLayout')

@section('body')
    <table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#d9d9d9">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center"
                       class="email-container">
                    <tr>
                        <td class="center-on-narrow" style="padding:30px 0px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center"
                                   width="580" style="margin: auto; width:580px" class="email-container">
                                <tr>
                                    <td class="center-on-narrow" align="left" valign="middle"
                                        style="font-size:24px; line-height:29px;">
                                        <font face="Arial, Helvetica, sans-serif"
                                              style="font-size:24px; line-height:29px; color:#000000; font-weight:bold; text-transform: uppercase;">
                                            {{ __('emails.bookingPay.title') }}</font>
                                    </td>

                                    <td width="50" class="center-on-narrow" align="right" valign="middle"
                                        style="font-size:24px; line-height:29px;">
                                        <font face="Arial, Helvetica, sans-serif"
                                              style="font-size:24px; line-height:29px; color:#ed1b66; font-weight:bold; text-transform: uppercase;">#{{$reference}}</font>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    <center style="width: 100%;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0" class="center-on-narrow">
            <tr>
                <td valign="top" align="center" style="padding-top:20px; padding-bottom:15px;">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" width="580"
                           style="margin: auto; width:580px" class="email-container">
                        <tr>
                            <td class="center-on-narrow">
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center"
                                       width="580" style="margin: auto; width:580px" class="email-container">
                                    <tr>
                                        <td class="left-on-narrow" align="left" valign="middle">
                                            <font face="Arial, Helvetica, sans-serif"
                                                  style="font-size:16px; line-height:21px; color:#000000;">
                                                {{ __('emails.bookingCreate.greeting', ['userName' => $userName]) }}
                                                <br><br>
                                                <font face="Arial, Helvetica, sans-serif"
                                                      style="font-size:16px; line-height:21px; color:#000000;">
                                                    {!! $titleTemplate !!}
                                                </font>
                                                <br><br>
                                                {!! __('emails.bookingPay.reservation_request', ['reference' => $reference, 'amount' => $amount, 'currency' => $currency]) !!}
                                            </font>
                                            <br><br>
                                            <font face="Arial, Helvetica, sans-serif"
                                                  style="font-size:14px; line-height:19px; color:#000000;">
                                                {{ __('emails.bookingPay.qr_note') }}
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
    </center>
    <div style="width: 100%;" *ngIf="type == 'mails.type4' || type == 'mails.type9'">
        <table width="100%" cellpadding="0" cellspacing="0" border="0" class="center-on-narrow">
            <tr>
                <td align="center" style="padding-top:30px; padding-bottom:30px;">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" width="470" style="margin: auto; width:470px" class="email-container">
                        <tr>
                            <td align="center" valign="middle" class="center-on-narrow">
{{--
                                <img src="data:image/png;base64,{{ base64_encode(\QrCode::format('png')->size(110)->generate($actionURL)) }}" alt="QR Code" style="width: 110px; height: 110px;">
--}}
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                    <tr>
                                        <td class="center-on-narrow" align="center" style="font-size:16px; line-height:21px; padding:0px 0px;" >
                                            <img src="data:image/png;base64,{{ base64_encode(\QrCode::format('png')->size(110)->generate($actionURL)) }}" alt="QR Code" style="width: 110px; height: 110px;">
                                        </td>
                                    </tr>
                                </table>
                            </td>
                            <td width="60" align="center" valign="middle" class="center-on-narrow">
                                <font face="Arial, Helvetica, sans-serif" style="font-size:16px; line-height:21px;
                                color:var(--color-dark1);">o</font>
                            </td>
                            <td class="center-on-narrow" align="center" valign="middle" class="center-on-narrow">
                                <div><!--[if mso]>
                                    <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{$actionURL}}" style="height:40px; v-text-anchor:middle; width:180px;" arcsize="125%" stroke="f" fillcolor="#ed1b66">
                                        <w:anchorlock/>
                                        <center>
                                    <![endif]-->
                                    <a href="{{$actionURL}}" target="_blank" rel="noopener" style="background-color:#ed1b66; border-radius:50px; color:#ffffff; display:inline-block; font-family:Arial, Helvetica, sans-serif; font-size:18px; font-weight:bold; line-height:40px; text-align:center; text-decoration:none; width:180px; -webkit-text-size-adjust:none; "> {{ __('emails.bookingPay.pay') }}</a>
                                    <!--[if mso]>
                                    </center>
                                    </v:roundrect>
                                    <![endif]-->
                                </div>
                                <br>
                                <font face="Arial, Helvetica, sans-serif" style="font-size:16px; line-height:21px; color:var(--color-dark1);">{{ __('emails.bookingPay.click') }}</font>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
    @if(count($courses))
        <center style="width: 100%;">
            @foreach ($courses as $course)
                @php
                    $courseModel = $course['course'];
                    $bookingUsers = collect($course['booking_users']);
                    $uniqueSlots = $bookingUsers->map(function ($booking) {
                        return $booking->date . '|' . $booking->hour_start . '|' . $booking->hour_end;
                    })->unique()->values();
                    $participants = $bookingUsers->unique('client_id')->values();
                    $monitors = $bookingUsers->filter(fn ($booking) => $booking->monitor)->unique('monitor_id')->values();
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
                                                                    {{$loop->index + 1}}</font>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>

                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td width="100" align="left"
                                                    style="font-size:14px; line-height:19px; padding:0px 0px;">
                                                    <font face="Arial, Helvetica, sans-serif"
                                                          style="font-size:14px; line-height:19px; color:#000000;">
                                                        {{ __('emails.bookingCreate.name') }}</font>
                                                </td>
                                                <td align="left" style="font-size:16px; line-height:21px; padding:0px 0px;">
                                                    <font face="Arial, Helvetica, sans-serif"
                                                          style="font-size:16px; line-height:21px; color:#000000; font-weight:bold;">
                                                        {{$courseModel->name}}
                                                    </font>
                                                </td>
                                            </tr>
                                            <tr>
                            <td width="100" align="left"
                                style="font-size:14px; line-height:19px; padding:0px 0px;">
                                <font face="Arial, Helvetica, sans-serif"
                                      style="font-size:14px; line-height:19px; color:#000000;">
                                    {{ __('emails.bookingCreate.type') }}</font>
                            </td>
                            <td align="left" style="font-size:14px; line-height:19px; padding:0px 0px;">
                                <font face="Arial, Helvetica, sans-serif"
                                      style="font-size:14px; line-height:19px; color:#000000;">
                                    @if($courseModel->course_type == 1)
                                        {{ __('emails.bookingCreate.collective_courses') }}
                                    @elseif($courseModel->course_type == 2)
                                        {{ __('emails.bookingCreate.private_courses') }}
                                    @endif
                                    @if(optional($courseModel->sport)->name)
                                        - {{ $courseModel->sport->name }}
                                    @endif
                                </font>
                            </td>
                        </tr>
                                            <tr>
                                                <td width="100" align="left"
                                                    style="font-size:14px; line-height:19px; padding:0px 0px;">
                                                    <font face="Arial, Helvetica, sans-serif"
                                                          style="font-size:14px; line-height:19px; color:#000000;">
                                                        {{ __('emails.bookingCreate.date') }}</font>
                                                </td>
                                                <td align="left" style="font-size:14px; line-height:19px; padding:0px 0px;">
                                                    @foreach($uniqueSlots as $slot)
                                                        @php [$slotDate, $slotStart, $slotEnd] = explode('|', $slot); @endphp
                                                        <font face="Arial, Helvetica, sans-serif"
                                                              style="font-size:14px; line-height:19px; color:#000000;">
                                                            {{ \Carbon\Carbon::parse($slotDate)->format('F d, Y') }} - {{$slotStart}} / {{$slotEnd}}
                                                        </font>
                                                        <br>
                                                    @endforeach
                                                </td>
                                            </tr>
                                            @php
                                                $meetingPointName = $booking->meeting_point ?? $courseModel->meeting_point ?? null;
                                                $meetingPointAddress = $booking->meeting_point_address ?? $courseModel->meeting_point_address ?? null;
                                                $meetingPointInstructions = $booking->meeting_point_instructions ?? $courseModel->meeting_point_instructions ?? null;
                                            @endphp
                                            @if($meetingPointName || $meetingPointAddress || $meetingPointInstructions)
                                                <tr>
                                                    <td width="100" align="left"
                                                        style="font-size:14px; line-height:19px; padding:0px 0px;">
                                                        <font face="Arial, Helvetica, sans-serif"
                                                              style="font-size:14px; line-height:19px; color:#000000;">
                                                            {{ __('emails.bookingCreate.meeting_point') }}</font>
                                                    </td>
                                                    <td align="left" style="font-size:14px; line-height:19px; padding:0px 0px;">
                                                        <font face="Arial, Helvetica, sans-serif"
                                                              style="font-size:14px; line-height:19px; color:#000000;">
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
                                                <td width="100" align="left"
                                                    style="font-size:14px; line-height:19px; padding:0px 0px;">
                                                    <font face="Arial, Helvetica, sans-serif"
                                                          style="font-size:14px; line-height:19px; color:#000000;">
                                                        {{ __('emails.bookingCreate.plural_participants') }}</font>
                                                </td>
                                                <td align="left" style="font-size:14px; line-height:19px; padding:0px 0px;">
                                                    @foreach($participants as $participantBooking)
                                                        @php $participant = $participantBooking->client; @endphp
                                                        @if($participant)
                                                            <font face="Arial, Helvetica, sans-serif"
                                                                  style="font-size:14px; line-height:19px; color:#000000;">
                                                                <strong>{{ $participant->full_name }}</strong>
                                                                {{ optional($participant->language1)->code ?? 'N/A' }} -
                                                                {{ collect(config('countries'))->firstWhere('id', $participant->country)['code'] ?? 'N/A' }}
                                                                @if($participant->birth_date)
                                                                    - {{ \Carbon\Carbon::parse($participant->birth_date)->age }} {{ __('emails.bookingCreate.age') }}
                                                                @endif
                                                            </font>
                                                            <br>
                                                        @endif
                                                    @endforeach
                                                </td>
                                            </tr>
                                            <tr>
                                                <td width="100" align="left"
                                                    style="font-size:14px; line-height:19px; padding:0px 0px;">
                                                    <font face="Arial, Helvetica, sans-serif"
                                                          style="font-size:14px; line-height:19px; color:#000000;">
                                                        {{ __('emails.bookingCreate.monitor') }}</font>
                                                </td>
                                                <td align="left" style="font-size:14px; line-height:19px; padding:0px 0px;">
                                                    @if($monitors->isNotEmpty())
                                                        @foreach($monitors as $monitorBooking)
                                                            @php $monitor = $monitorBooking->monitor; @endphp
                                                            @if($monitor)
                                                                <font face="Arial, Helvetica, sans-serif"
                                                                      style="font-size:14px; line-height:19px; color:#000000;">
                                                                    <strong>{{ $monitor->full_name }}</strong>
                                                                    {{ optional($monitor->language1)->code ?? 'N/A' }} -
                                                                    {{ collect(config('countries'))->firstWhere('id', $monitor->country)['code'] ?? 'N/A' }}
                                                                </font>
                                                                <br>
                                                            @endif
                                                        @endforeach
                                                    @else
                                                        <font face="Arial, Helvetica, sans-serif"
                                                              style="font-size:14px; line-height:19px; color:#000000;">
                                                            {{ __('emails.bookingInfo.unknown') }}
                                                        </font>
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            @endforeach
        </center>
    @endif
    @if(!empty($client))
        <center style="width: 100%;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" class="center-on-narrow">
                <tr>
                    <td valign="top" align="center" style="padding-top:20px; padding-bottom:0px;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" width="580"
                               style="margin: auto; width:580px" class="email-container">
                            <tr>
                                <td align="center" valign="top" style="padding:15px 20px 15px 20px;" bgcolor="#f4f4f4">
                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                        <tr>
                                            <td align="left" style="font-size:20px; line-height:25px; padding:0px 0px 10px 0px;">
                                                <font face="Arial, Helvetica, sans-serif" style="font-size:20px; line-height:25px; color:#ed1b66; font-weight: bold;">
                                                    {{ __('emails.bookingPay.client_label') }}
                                                </font>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td align="left" style="font-size:14px; line-height:19px; padding:0px 0px;">
                                                <font face="Arial, Helvetica, sans-serif" style="font-size:14px; line-height:19px; color:#000000;">
                                                    <strong>{{ $client->full_name }}</strong><br>
                                                    {{ $client->email }}<br>
                                                    @if($client->phone || $client->telephone)
                                                        {{ $client->phone ?? $client->telephone }}<br>
                                                    @endif
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
        </center>
    @endif
    <center style="width: 100%;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0" class="center-on-narrow">
            <tr>
                <td valign="top" align="center" style="padding-top:20px; padding-bottom:20px;">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" width="580" style="margin: auto; width:580px"
                           class="email-container">
                        <tr>
                            <td align="center" valign="top" style="padding:15px 20px 15px 20px;" bgcolor="#f4f4f4">
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                    <tr>
                                        <td valign="top">
                                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                <tr>
                                                    <td align="left" style="font-size:20px; line-height:25px; padding:0px 0px 15px 0px;" >
                                                        <font face="Arial, Helvetica, sans-serif" style="font-size:20px; line-height:25px; color:#ed1b66; font-weight: bold;">
                                                            {{ __('emails.bookingCreate.summary') }}</font>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                        <td width="100" align="right" style="font-size:20px; line-height:25px; padding:0px 0px;" >
                                            <font face="Arial, Helvetica, sans-serif" style="font-size:20px; line-height:25px; color:#ed1b66; font-weight: bold;">#{{$reference}}</font>
                                        </td>
                                    </tr>
                                </table>

                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                    @foreach($courses as $course)
                                        <tr>
                                            <td align="left" style="font-size:14px; line-height:19px; padding:0px 0px;" >
                                                <font face="Arial, Helvetica, sans-serif" style="font-size:14px; line-height:19px; color:#222222">
                                                    {{ __('emails.bookingCreate.activity') }} {{$loop->index + 1}}</font>
                                            </td>
                                            <td width="200" align="right" style="font-size:14px; line-height:19px; padding:0px 0px;" >
                                                <font face="Arial, Helvetica, sans-serif" style="font-size:14px; line-height:19px; color:#222222;">
                                                    {{$course['booking_users'][0]->price}} {{$currency}}</font>
                                            </td>
                                        </tr>
                                    @endforeach

                                    @if($booking->has_bookii_care)
                                        <tr>
                                            <td align="left" style="font-size:14px; line-height:19px; padding:0px 0px;" >
                                                <font face="Arial, Helvetica, sans-serif" style="font-size:14px; line-height:19px; color:#222222;">
                                                    {{-- BOUKII CARE DESACTIVADO -                                                     {{ __('emails.bookingCreate.boukii_care') }}</font> --}}
                                            </td>
                                            <td width="200" align="right" style="font-size:14px; line-height:19px; padding:0px 0px;" >
                                                <font face="Arial, Helvetica, sans-serif" style="font-size:14px; line-height:19px; color:#222222;">
                                                    {{-- BOUKII CARE DESACTIVADO -                                                     {{$booking->price_boukii_care}}</font> --}}
                                            </td>
                                        </tr>
                                    @endif
                                    @if($booking->has_cancellation_insurance)
                                        <tr>
                                            <td align="left" style="font-size:14px; line-height:19px; padding:0px 0px;" >
                                                <font face="Arial, Helvetica, sans-serif" style="font-size:14px; line-height:19px; color:#222222;">
                                                    {{ __('emails.bookingCreate.cancellation_option') }}</font>
                                            </td>
                                            <td width="200" align="right" style="font-size:14px; line-height:19px; padding:0px 0px;" >
                                                <font face="Arial, Helvetica, sans-serif" style="font-size:14px; line-height:19px; color:#222222;">
                                                    {{$booking->price_cancellation_insurance}}</font>
                                            </td>
                                        </tr>
                                    @endif
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
                                        <td align="left" style="font-size:14px; line-height:19px; padding:0px 0px;" >
                                            <font face="Arial, Helvetica, sans-serif" style="font-size:14px; line-height:19px; color:#222222;">Subtotal</font>
                                        </td>
                                        <td width="200" align="right" style="font-size:14px; line-height:19px; padding:0px 0px;" >
                                            <font face="Arial, Helvetica, sans-serif" style="font-size:14px; line-height:19px; color:#222222;">
                                                {{$booking->price_total - $booking->price_tva}} {{$booking->currency}}</font>
                                        </td>
                                    </tr>
                                    @if($booking->has_tva)
                                        <tr>
                                            <td align="left" style="font-size:14px; line-height:19px; padding:0px 0px;" >
                                                <font face="Arial, Helvetica, sans-serif" style="font-size:14px; line-height:19px; color:#222222;">TVA</font>
                                            </td>
                                            <td width="200" align="right" style="font-size:14px; line-height:19px; padding:0px 0px;" >
                                                <font face="Arial, Helvetica, sans-serif" style="font-size:14px; line-height:19px; color:#222222;">{{$booking->price_tva}}
                                                    {{$booking->currency}}</font>
                                            </td>
                                        </tr>
                                    @endif
                                    @if($booking->paid_total > 0)
                                        <tr>
                                            <td align="left" style="font-size:14px; line-height:19px; padding:0px 0px;" >
                                                <font face="Arial, Helvetica, sans-serif" style="font-size:14px; line-height:19px; color:#222222;">{{ __('emails.bookingPay.paid') }}</font>
                                            </td>
                                            <td width="200" align="right" style="font-size:14px; line-height:19px; padding:0px 0px;" >
                                                <font face="Arial, Helvetica, sans-serif" style="font-size:14px; line-height:19px; color:#222222;">{{$booking->paid_total}}
                                                    {{$booking->currency}}</font>
                                            </td>
                                        </tr>
                                    @endif
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
                                        <td align="left" style="font-size:16px; line-height:21px; font-weight: bold; padding:0px 0px;" >
                                            <font face="Arial, Helvetica, sans-serif" style="font-size:16px; line-height:21px; color:#222222; font-weight: bold;">Total</font>
                                        </td>
                                        <td width="200" align="right" style="font-size:16px; line-height:21px; color:#222222; font-weight: bold; padding:0px 0px;" >
                                            <font face="Arial, Helvetica, sans-serif" style="font-size:16px; line-height:21px; color:#222222; font-weight: bold;">{{$amount}}
                                                {{$booking->currency}}</font>
                                        </td>
                                    </tr>
                                </table>

                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        <p>
            {!! $bodyTemplate !!}
        </p>
    </center>
@endsection
