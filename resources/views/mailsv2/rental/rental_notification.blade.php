@extends('mailsv2.newLayout')

@section('body')
@php
    $type        = $notificationType ?? 'confirmation';
    $headerColor = match($type) {
        'overdue'    => '#ef4444',
        'returned'   => '#22c55e',
        'reminder'   => '#f59e0b',
        'damage'     => '#dc2626',
        'cancelled'  => '#6b7280',
        default      => '#ea580c',
    };
    $headerText = __('emails.rental.header_' . $type);
    $introText  = __('emails.rental.intro_' . $type);
    $clientName = trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''));
@endphp

{{-- ── Header banner ─────────────────────────────────────────────────────── --}}
<table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#d9d9d9">
    <tr>
        <td align="center">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" class="email-container">
                <tr>
                    <td class="center-on-narrow" style="padding:30px 0px;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center"
                               width="580" style="margin:auto;width:580px" class="email-container">
                            <tr>
                                <td class="center-on-narrow" align="left" valign="middle">
                                    <font face="Arial, Helvetica, sans-serif"
                                          style="font-size:24px;line-height:29px;color:#000000;font-weight:bold;text-transform:uppercase;">
                                        {{ $headerText }}</font>
                                </td>
                                <td width="60" class="center-on-narrow" align="right" valign="middle">
                                    <font face="Arial, Helvetica, sans-serif"
                                          style="font-size:24px;line-height:29px;color:{{ $headerColor }};font-weight:bold;">
                                        #{{ $reservation->id }}</font>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- ── Body ───────────────────────────────────────────────────────────────── --}}
<center style="width:100%;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" class="center-on-narrow">
        <tr>
            <td valign="top" align="center" style="padding-top:20px;padding-bottom:20px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center"
                       width="580" style="margin:auto;width:580px" class="email-container">

                    {{-- Greeting --}}
                    <tr>
                        <td align="left" valign="top" style="padding:15px 20px;" bgcolor="#f4f4f4">
                            <font face="Arial, Helvetica, sans-serif" style="font-size:16px;color:#ea580c;font-weight:bold;">
                                {{ $school->name ?? '' }}
                            </font>
                            <br/>
                            <font face="Arial, Helvetica, sans-serif" style="font-size:14px;color:#374151;">
                                {!! __('emails.rental.greeting', ['name' => $clientName]) !!}
                            </font>
                            @if (!empty($introText))
                            <br/><br/>
                            <font face="Arial, Helvetica, sans-serif" style="font-size:14px;color:#374151;">
                                {{ $introText }}
                            </font>
                            @endif
                        </td>
                    </tr>

                    {{-- Section: Reservation Details --}}
                    <tr>
                        <td align="left" valign="top" style="padding:15px 20px 0 20px;">
                            <font face="Arial, Helvetica, sans-serif" style="font-size:18px;color:#ea580c;font-weight:bold;">
                                {{ __('emails.rental.section_details') }}
                            </font>
                        </td>
                    </tr>
                    <tr>
                        <td align="left" valign="top" style="padding:8px 20px;" bgcolor="#fff7ed">
                            <table width="100%" cellpadding="4" cellspacing="0" border="0">
                                <tr>
                                    <td style="font-size:13px;color:#6b7280;width:160px;">
                                        {{ __('emails.rental.label_reference') }}
                                    </td>
                                    <td style="font-size:13px;color:#111827;font-weight:bold;">
                                        #{{ $reservation->id }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-size:13px;color:#6b7280;">
                                        {{ __('emails.rental.label_period') }}
                                    </td>
                                    <td style="font-size:13px;color:#111827;">
                                        {{ $reservation->start_date }} → {{ $reservation->end_date }}
                                    </td>
                                </tr>
                                @if ($pickupPoint)
                                <tr>
                                    <td style="font-size:13px;color:#6b7280;">
                                        {{ __('emails.rental.label_pickup') }}
                                    </td>
                                    <td style="font-size:13px;color:#111827;">
                                        {{ $pickupPoint->name }}
                                        @if (!empty($pickupPoint->address))
                                            <br/><span style="color:#6b7280;">{{ $pickupPoint->address }}</span>
                                        @endif
                                    </td>
                                </tr>
                                @endif
                                <tr>
                                    <td style="font-size:13px;color:#6b7280;">
                                        {{ __('emails.rental.label_status') }}
                                    </td>
                                    <td style="font-size:13px;color:{{ $headerColor }};font-weight:bold;text-transform:uppercase;">
                                        {{ $reservation->status }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Section: Equipment lines --}}
                    @if (!empty($lines))
                    <tr>
                        <td align="left" valign="top" style="padding:15px 20px 0 20px;">
                            <font face="Arial, Helvetica, sans-serif" style="font-size:18px;color:#ea580c;font-weight:bold;">
                                {{ __('emails.rental.section_equipment') }}
                            </font>
                        </td>
                    </tr>
                    <tr>
                        <td align="left" valign="top" style="padding:8px 20px;">
                            <table width="100%" cellpadding="4" cellspacing="0" border="0" bgcolor="#f4f4f4">
                                <tr style="border-bottom:1px solid #e5e7eb;">
                                    <td style="font-size:12px;font-weight:bold;color:#6b7280;padding:6px 8px;">
                                        {{ __('emails.rental.col_item') }}
                                    </td>
                                    <td style="font-size:12px;font-weight:bold;color:#6b7280;padding:6px 8px;text-align:center;">
                                        {{ __('emails.rental.col_qty') }}
                                    </td>
                                    <td style="font-size:12px;font-weight:bold;color:#6b7280;padding:6px 8px;text-align:right;">
                                        {{ __('emails.rental.col_total') }}
                                    </td>
                                </tr>
                                @foreach ($lines as $line)
                                <tr>
                                    <td style="font-size:13px;color:#111827;padding:6px 8px;">
                                        {{ $line->item_name ?? ('Item #' . ($line->item_id ?? '?')) }}
                                    </td>
                                    <td style="font-size:13px;color:#374151;text-align:center;padding:6px 8px;">
                                        {{ $line->quantity ?? 1 }}
                                    </td>
                                    <td style="font-size:13px;color:#374151;text-align:right;padding:6px 8px;">
                                        {{ number_format((float)($line->line_total ?? 0), 2) }}
                                        {{ $reservation->currency ?? 'CHF' }}
                                    </td>
                                </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>
                    @endif

                    {{-- Total --}}
                    <tr>
                        <td align="right" valign="top" style="padding:12px 20px;" bgcolor="#f4f4f4">
                            <font face="Arial, Helvetica, sans-serif"
                                  style="font-size:18px;color:#111827;font-weight:bold;">
                                {{ __('emails.rental.label_total') }}:
                                {{ number_format((float)($reservation->total ?? 0), 2) }}
                                {{ $reservation->currency ?? 'CHF' }}
                            </font>
                        </td>
                    </tr>

                    {{-- Damage details (damage type only) --}}
                    @if (!empty($damageContext))
                    <tr>
                        <td align="left" valign="top" style="padding:12px 20px;" bgcolor="#fef2f2">
                            <font face="Arial, Helvetica, sans-serif"
                                  style="font-size:14px;color:#dc2626;font-weight:bold;">
                                {{ __('emails.rental.label_damage_cost') }}:
                                {{ number_format((float)($damageContext['damage_cost'] ?? 0), 2) }}
                                {{ $reservation->currency ?? 'CHF' }}
                            </font>
                            @if (!empty($damageContext['description']))
                            <br/>
                            <font face="Arial, Helvetica, sans-serif" style="font-size:13px;color:#374151;">
                                {{ $damageContext['description'] }}
                            </font>
                            @endif
                        </td>
                    </tr>
                    @endif

                    {{-- Notes --}}
                    @if (!empty($reservation->notes))
                    <tr>
                        <td align="left" valign="top"
                            style="padding:12px 20px;font-size:13px;color:#6b7280;font-style:italic;">
                            <strong>{{ __('emails.rental.label_notes') }}:</strong>
                            {{ $reservation->notes }}
                        </td>
                    </tr>
                    @endif

                </table>
            </td>
        </tr>
    </table>
</center>

@include('mailsv2.newFooter')
@endsection
