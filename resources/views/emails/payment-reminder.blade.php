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
                            There's an outstanding balance on your reservation with us.
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#fbf6ec; border-radius:6px; padding:16px 20px; font-size:14px; line-height:1.8;">
                            <strong>Room:</strong> {{ $booking->room->roomType->name }} — {{ $booking->room->number }}<br>
                            <strong>Check-in:</strong> {{ $booking->check_in->format('l, F j, Y') }}<br>
                            <strong>Check-out:</strong> {{ $booking->check_out->format('l, F j, Y') }}<br>
                            <strong>Balance due:</strong> ${{ number_format($balanceDueCents / 100, 2) }}
                        </td>
                    </tr>
                    @if($willAutoCancel)
                    <tr>
                        <td style="font-size:14px; line-height:1.6; padding-top:24px; color:#a33b1e;">
                            <strong>Please note:</strong> if this balance isn't settled within the
                            next 24 hours, your reservation will be automatically cancelled and the
                            room released.
                        </td>
                    </tr>
                    @endif
                    <tr>
                        <td style="font-size:14px; line-height:1.6; padding-top:24px; color:#6b5330;">
                            Please settle this balance with the front desk at your earliest
                            convenience. Reach out if you have any questions.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
