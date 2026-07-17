<!DOCTYPE html>
<html>
<body style="margin:0; padding:0; background-color:#faf8f3; font-family: Georgia, 'Times New Roman', serif; color:#1b1b18;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#faf8f3; padding:32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border:1px solid #e9d4a0; border-radius:8px; padding:32px;">
                    <tr>
                        <td style="font-size:20px; padding-bottom:16px;">🛎️ Bellhop</td>
                    </tr>
                    <tr>
                        <td style="font-size:16px; padding-bottom:16px;">Dear {{ $booking->guest->name }},</td>
                    </tr>
                    <tr>
                        <td style="font-size:15px; line-height:1.6; padding-bottom:20px;">
                            We tried to charge the card on file for the remaining balance on your
                            upcoming stay, but the charge didn't go through. No further attempts
                            will be made automatically — please pay manually using the link below.
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#fbf6ec; border-radius:6px; padding:16px 20px; font-size:14px; line-height:1.8;">
                            <strong>Room:</strong> {{ $booking->room->roomType->name }} — {{ $booking->room->number }}<br>
                            <strong>Check-in:</strong> {{ $booking->check_in->format('l, F j, Y') }}<br>
                            <strong>Balance due:</strong> ${{ number_format($balanceDueCents / 100, 2) }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-top:24px;">
                            <a href="{{ $payUrl }}" style="display:inline-block; background-color:#a17e3e; color:#ffffff; padding:12px 24px; border-radius:6px; text-decoration:none; font-size:14px;">Pay now</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size:14px; line-height:1.6; padding-top:24px; color:#6b5330;">
                            If you have any questions, please get in touch with the front desk.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
