<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; color: #1a1a1a; line-height: 1.6;">
    <p>Hi {{ $name }},</p>

    <p>Welcome to Ledgerly!</p>

    <p>Use the verification code below:</p>

    <p style="font-size: 32px; font-weight: bold; letter-spacing: 6px; margin: 24px 0;">
        {{ $code }}
    </p>

    <p style="color: #555; font-size: 14px;">This code expires in 10 minutes.</p>

    <p style="color: #777; font-size: 13px;">
        If you didn't create this account, you can safely ignore this email.
    </p>

    <p>— The Ledgerly Team</p>
</body>
</html>
