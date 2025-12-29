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
                                            {{ __('emails.bookingCancel.title') }}</font>
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
                                                {!! __('emails.bookingCancel.reservation_cancel', ['reference' => $reference]) !!}
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
    @php
        $activities = $groupedActivities ?? $courses;
    @endphp

    @foreach ($activities as $activity)
        @include('mailsv2.partials.activity-card', ['activity' => $activity, 'loopIndex' => $loop->index, 'booking' => $booking, 'forceDatePrice' => true])
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
                                    @foreach($activities as $activity)
                                        <tr>
                                            <td align="left" style="font-size:14px; line-height:19px; padding:0px 0px;" >
                                                <font face="Arial, Helvetica, sans-serif" style="font-size:14px; line-height:19px; color:#222222">
                                                    {{ __('emails.bookingCreate.activity') }} {{$loop->index + 1}}</font>
                                            </td>
                                            <td width="200" align="right" style="font-size:14px; line-height:19px; padding:0px 0px;" >
                                                <font face="Arial, Helvetica, sans-serif" style="font-size:14px; line-height:19px; color:#222222;">
                                                    {{$activity['total'] ?? ($activity['price'] ?? 0)}} {{$booking->currency}}</font>
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


