<!-- Footer -->
<table width="100%" cellpadding="0" cellspacing="0" border="0" class="center-on-narrow">
    <tr>
        <td align="center" style="border-top: 1px solid #222222;">

            <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" class="email-container">
                <tr>
                    <td class="center-on-narrow" style="padding:20px 0px;">

                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" width="580" style="margin: auto; width:580px" class="email-container">

                            <tr>
                                <td width="220" class="center-on-narrow" align="left" valign="top" style="font-size:24px; line-height:29px; padding:0px 0px;" >
                                    <img src="{{$schoolLogo}}" width="200" height="200" alt="" border="0"
                                         style="display: block;  height: auto; max-width: 200px; max-height:200px; font-family:Arial, Helvetica, sans-serif; font-size:12px; color:#152a69; line-height:20px; vertical-align:bottom">
                                </td>
                                <td width="20" class="center-on-narrow" valign="middle" style="border-left:1px solid #222222;">&nbsp;</td>
                                <td class="center-on-narrow">
                                    <font face="Arial, Helvetica, sans-serif" style="font-size:12px; line-height:17px; color:#222222;">
                                        {{ __('emails.footer.automatic_email') }}
                                    </font>
                                    <font face="Arial, Helvetica, sans-serif" style="font-size:12px; line-height:17px; color:#222222;">
                                        {{ __('emails.footer.contact_school', ['schoolName' => $schoolName]) }}
                                    </font>
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin: 10px 0px!important;">
                                        <tr>
                                            <td width="150" class="center-on-narrow" align="left" valign="middle" style="font-size:20px; line-height:25px; color:#222222; padding:0px 0px;" >
                                                <font face="Arial, Helvetica, sans-serif" style="font-size:20px; line-height:25px; color:#222222; font-weight:bold;">{{$schoolPhone}}</font>
                                            </td>
                                            <td width="20" class="center-on-narrow" align="left" valign="middle" style="border-left:1px solid #222222;">&nbsp;</td>
                                            <td class="center-on-narrow" align="left" valign="middle" style="font-size:15px; line-height:20px; color:#222222;" >
                                                <a href="mailto:{{$schoolEmail}}" target="_blank" style="color:#222222;"><font face="Arial, Helvetica, sans-serif" style="font-size:15px; line-height:20px; color:#222222;">{{$schoolEmail}}</font></a>
                                            </td>
                                        </tr>
                                    </table>
                                    @if(!empty($schoolConditionsURL))
                                        <a href="{{$schoolConditionsURL}}" target="_blank" style="color:#222222; text-decoration:none;">
                                            <font face="Arial, Helvetica, sans-serif" style="font-size:12px; line-height:17px; color:#222222;">
                                                {{ __('emails.footer.school_conditions') }}
                                            </font>
                                        </a>
                                    @else
                                        <font face="Arial, Helvetica, sans-serif" style="font-size:12px; line-height:17px; color:#222222;">
                                            {{ __('emails.footer.school_conditions') }}
                                        </font>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#ed1b66" style="height: 18px;">
                <tr>
                    <td style="height: 18px;">&nbsp;</td>
                </tr>
            </table>

            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" align="center" class="email-container">
                <tr>
                    <td class="center-on-narrow" align="center" style="padding: 20px 0px;">
                        <font face="Arial, Helvetica, sans-serif" style="font-size:12px; line-height:17px; color:#222222;">{{ __('emails.footer.copyright') }}</font>
                    </td>
                </tr>
            </table>

        </td>
    </tr>
</table>
