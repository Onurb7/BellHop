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
                            We hope you enjoyed your stay with us at {{ $booking->room->roomType->name }}.
                            Let us know how it was — your feedback helps us improve and maintain a
                            high level of service for every guest who stays with us.
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-bottom:20px;">
                            <a href="{{ $reviewUrl }}" style="display:inline-block; background-color:#a17e3e; color:#ffffff; padding:12px 24px; border-radius:6px; text-decoration:none; font-size:14px;">Leave a review</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size:13px; line-height:1.6; color:#6b5330;">
                            It only takes a minute, and a written review is entirely optional —
                            a star rating alone is just as welcome.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
