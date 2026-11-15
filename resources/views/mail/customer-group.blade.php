<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f3f5f9;color:#172033;font-family:Arial,Helvetica,sans-serif;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">{{ $preheader }}</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f5f9;padding:28px 12px;">
        <tr><td align="center">
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="width:100%;max-width:600px;background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;">
                <tr><td style="background:#2459e8;padding:22px 28px;color:#ffffff;font-size:18px;font-weight:700;">{{ $siteName }}</td></tr>
                <tr><td style="padding:30px 28px 12px;font-size:23px;line-height:1.3;font-weight:700;color:#172033;">{{ $heading }}</td></tr>
                <tr><td style="padding:8px 28px 30px;font-size:15px;line-height:1.75;color:#39465c;">{!! $bodyHtml !!}</td></tr>
                <tr><td style="border-top:1px solid #e8edf4;padding:20px 28px;color:#667085;font-size:12px;line-height:1.6;">
                    <p style="margin:0 0 8px;">You received this update because email updates are enabled on your account.</p>
                    @if ($unsubscribeUrl)<p style="margin:0;"><a href="{{ $unsubscribeUrl }}" style="color:#2459e8;">Unsubscribe from customer emails</a></p>@endif
                    <p style="margin:10px 0 0;">{{ $siteName }}</p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
