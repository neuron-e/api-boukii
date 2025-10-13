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
                                            {{ __('emails.bookingInfoChange.title') }}</font>
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
                                                {{ __('emails.bookingCreate.reservation_thanks') }}
                                                <br><br>
                                                {!! __('emails.bookingInfoChange.reservation_request', ['reference' => $reference]) !!}
                                            </font>
                                            <br><br>
                                            <font face="Arial, Helvetica, sans-serif"
                                                  style="font-size:14px; line-height:19px; color:#000000;">
                                                {{ __('emails.bookingCreate.qr_note') }}
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
    <center style="width: 100%;">
        @foreach ($courses as $course)
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
                                                <table role="presentation" cellspacing="0" cellpadding="0" border="0"
                                                       width="100%">
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
                                            <td valign="top" class="left-on-narrow">
                                                <table role="presentation" cellspacing="0" cellpadding="0" border="0"
                                                       width="100%">
                                                    <tr>
                                                        <td width="100" align="left"
                                                            style="font-size:16px; line-height:21px; padding:0px 0px;">
                                                            <font face="Arial, Helvetica, sans-serif"
                                                                  style="font-size:16px; line-height:21px; color:#000000;">
                                                                {{ __('emails.bookingCreate.type') }}</font>
                                                        </td>
                                                        <td align="left"
                                                            style="font-size:18px; line-height:23px; padding:0px 0px;">
                                                            <font face="Arial, Helvetica, sans-serif"
                                                                  style="font-size:18px; line-height:23px; color:#000000; font-weight:bold;">
                                                                {{$course['course']->name}}
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
                                                        <td align="left"
                                                            style="font-size:14px; line-height:19px; padding:0px 0px;">
                                                            <font face="Arial, Helvetica, sans-serif"
                                                                  style="font-size:14px; line-height:19px; color:#000000;">
                                                                {{$course['course']->name}}
                                                                @if($course['course']['course_type'] == 1)
                                                                    {{ __('emails.bookingCreate.collective_courses') }}
                                                                @endif
                                                                @if($course['course']['course_type'] == 2)
                                                                    {{ __('emails.bookingCreate.private_courses') }}
                                                                @endif
                                                                {{$course['course']['sport']->name}}
                                                            </font>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td width="100" align="left"
                                                            style="font-size:14px; line-height:19px; padding:0px 0px;display: block">
                                                            <font face="Arial, Helvetica, sans-serif"
                                                                  style="font-size:14px; line-height:19px; color:#000000;">
                                                                {{ __('emails.bookingCreate.date') }}</font>
                                                        </td>
                                                        <td align="left" style="font-size:14px; line-height:19px; padding:0px 0px;">
                                        @php
                                                                $uniqueBookings = []; // Array para almacenar combinaciones únicas
                                                            @endphp

                                                            @foreach($course['booking_users'] as $booking)
                                                                @php
                                                                    // Crear una clave única basada en la combinación de date, hour_start y hour_end
                                                                    $key = $booking->date . '|' . $booking->hour_start . '|' . $booking->hour_end;
                                                                @endphp

                                                                @if(!in_array($key, $uniqueBookings))
                                                                    @php
                                                                        // Si la combinación no está en el array, agregarla
                                                                        $uniqueBookings[] = $key;
                                                                    @endphp
                                                                    <font face="Arial, Helvetica, sans-serif" style="font-size:14px; line-height:19px; color:#000000;">
                                                                        {{ \Carbon\Carbon::parse($booking->date)->format('F d, Y') }} - {{$booking->hour_start}} / {{$booking->hour_end}}
                                                                    </font>
                                                                    <br>
                                                                @endif
                                                            @endforeach
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td width="100" align="left"
                                                            style="font-size:14px; line-height:19px; padding:0px 0px;display: block">
                                                            <font face="Arial, Helvetica, sans-serif"
                                                                  style="font-size:14px; line-height:19px; color:#000000;">
                                                                {{ __('emails.bookingCreate.participant') }}</font>
                                                        </td>
                                                        <td align="left"
                                                            style="font-size:14px; line-height:19px; padding:0px 0px;">
                                                            <font face="Arial, Helvetica, sans-serif"
                                                                  style="font-size:14px; line-height:19px; color:#000000;">
                                                                <strong>{{$course['booking_users'][0]->client->full_name}}</strong>
                                                                {{$course['booking_users'][0]->client->language1->code ?? 'NDF'}} -
                                                                {{ collect(config('countries'))->firstWhere('id', $course['booking_users'][0]->client->country)['code'] ?? 'NDF' }} -
                                                                {{\Carbon\Carbon::parse($course['booking_users'][0]->client->birth_date)->age}}  {{ __('emails.bookingCreate.age') }}</font>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td width="100" align="left"
                                                            style="font-size:14px; line-height:19px; padding:0px 0px; display: block">
                                                            <font face="Arial, Helvetica, sans-serif"
                                                                  style="font-size:14px; line-height:19px; color:#000000;">
                                                                {{ __('emails.bookingCreate.monitor') }}</font>
                                                        </td>
                                                        <td align="left" style="font-size:14px; line-height:19px; padding:0px 0px;">
                                                            @if (count($course['booking_users']) > 0)
                                                                @php
                                                                    $uniqueMonitors = []; // Array para almacenar los monitores únicos
                                                                    $firstBooking = null;

                                                                    foreach ($course['booking_users'] as $booking) {
                                                                        if ($booking->monitor && !in_array($booking->monitor->id, $uniqueMonitors)) {
                                                                            $uniqueMonitors[] = $booking->monitor->id;
                                                                            if (!$firstBooking) {
                                                                                $firstBooking = $booking; // Asignar la primera reserva con un monitor único
                                                                            }
                                                                        }
                                                                    }

                                                                    $remainingCount = count($uniqueMonitors) - 1; // Restar el primero que ya se muestra
                                                                @endphp

                                                                @if ($firstBooking && $firstBooking->monitor)
                                                                    <font face="Arial, Helvetica, sans-serif" style="font-size:14px; line-height:19px; color:#000000;">
                                                                        <strong>{{ $firstBooking->monitor->full_name }}</strong>
                                                                        {{ $firstBooking->monitor->language1->code ?? 'NDF' }} -
                                                                        {{ collect(config('countries'))->firstWhere('id', $firstBooking->monitor->country)['code'] ?? 'NDF' }} -
                                                                        {{ \Carbon\Carbon::parse($firstBooking->monitor->birth_date)->age }}  {{ __('emails.bookingCreate.age') }}
                                                                    </font>
                                                                @else
                                                                    <font face="Arial, Helvetica, sans-serif" style="font-size:14px; line-height:19px; color:#000000;">
                                                                        <strong>NDF</strong>
                                                                    </font>
                                                                @endif

                                                                @if ($remainingCount > 0)
                                                                    <font face="Arial, Helvetica, sans-serif" style="font-size:14px; line-height:19px; color:#000000;">
                                                                        +{{ $remainingCount }} más
                                                                    </font>
                                                                @endif
                                                            @else
                                                                <font face="Arial, Helvetica, sans-serif" style="font-size:14px; line-height:19px; color:#000000;">
                                                                    <strong>NDF</strong>
                                                                </font>
                                                            @endif
                                                        </td>

                                                    </tr>
                                                </table>
                                            </td>
                                            <td valign="top" width="110" class="left-on-narrow" align="center">
                                                @php
                                                    // Generar QR code con los IDs de booking_users para esta actividad
                                                    $bookingUserIds = collect($course['booking_users'])->pluck('id')->implode(',');
                                                @endphp
                                                <img src="data:image/png;base64,{{ base64_encode(\QrCode::format('png')->size(110)->generate($bookingUserIds)) }}" alt="QR Code" style="width: 110px; height: 110px;">
                                            </td>
                                        </tr>
                                    </table>

                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                        <tr>
                                            <td style="padding: 10px 0px;">
                                                <table role="presentation" cellspacing="0" cellpadding="0" border="0"
                                                       width="100%">
                                                    <tr>
                                                        <td style="width: 100%; border-bottom: 1px solid #d2d2d2;"></td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>

                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                        <tr>
                                            <td>
                                                <table role="presentation" cellspacing="0" cellpadding="0" border="0"
                                                       width="100%">
                                                    <tr>
                                                        <td align="left" valign="middle"
                                                            style="font-size:24px; line-height:29px; padding-bottom:10px;">
                                                            <font face="Arial, Helvetica, sans-serif"
                                                                  style="font-size:24px; line-height:29px; color:#d2d2d2; font-weight:bold;">
                                                                {{ __('emails.bookingCreate.price') }}</font>
                                                        </td>
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
                                                                @php
                                                                    $totalPrice = $course['booking_users'][0]->price;
                                                                    $totalExtrasPrice = 0;

                                                                    // Iterar sobre los booking_users
                                                                    foreach($course['booking_users'] as $bookingUser) {
                                                                        // Iterar sobre los bookingUserExtras y sumar los precios de courseExtra
                                                                        foreach($bookingUser->bookingUserExtras as $bookingUserExtra) {
                                                                            $totalExtrasPrice += $bookingUserExtra->courseExtra->price;
                                                                        }
                                                                    }

                                                                    // Restar el total de los extras del precio inicial
                                                                    $basePrice = $totalPrice - $totalExtrasPrice;

                                                                @endphp
                                                                {{$basePrice}} CHF
                                                            </font>
                                                        </td>
                                                    </tr>
                                                    @if($totalExtrasPrice > 0)
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
                                                                    {{$totalExtrasPrice}} CHF</font>
                                                            </td>
                                                        </tr>
                                                    @endif
                                                </table>

                                                <table role="presentation" cellspacing="0" cellpadding="0" border="0"
                                                       width="100%">
                                                    <tr>
                                                        <td style="padding: 10px 0px;">
                                                            <table role="presentation" cellspacing="0" cellpadding="0"
                                                                   border="0" width="100%">
                                                                <tr>
                                                                    <td style="width: 100%; border-bottom: 1px solid #d2d2d2;"></td>
                                                                </tr>
                                                            </table>
                                                        </td>
                                                    </tr>
                                                </table>

                                                <table role="presentation" cellspacing="0" cellpadding="0" border="0"
                                                       width="100%">
                                                    <tr>
                                                        <td width="100" align="left"
                                                            style="font-size:16px; line-height:21px; font-weight: bold; padding:0px 0px;">
                                                            <font face="Arial, Helvetica, sans-serif"
                                                                  style="font-size:16px; line-height:21px; color:#000000; font-weight: bold;">
                                                                {{ __('emails.bookingCreate.total') }}</font>
                                                        </td>
                                                        <td align="right"
                                                            style="font-size:16px; line-height:21px; color:#000000; font-weight: bold; padding:0px 0px;">
                                                            <font face="Arial, Helvetica, sans-serif"
                                                                  style="font-size:16px; line-height:21px; color:#000000; font-weight: bold;">
                                                                {{$totalPrice}} CHF</font>
                                                        </td>
                                                    </tr>
                                                </table>

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
                                            <font face="Arial, Helvetica, sans-serif" style="font-size:20px; line-height:25px; color:#ed1b66; font-weight: bold;">#{{$booking->id}}</font>
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
                                                    {{$course['booking_users'][0]->price}} {{$booking->currency}}</font>
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
                                            <font face="Arial, Helvetica, sans-serif" style="font-size:16px; line-height:21px; color:#222222; font-weight: bold;">{{$booking->price_total}}
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
