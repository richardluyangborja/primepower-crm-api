<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Your login verification code</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1a1a1a; line-height: 1.6;">
    <p>Hi {{ $userName }},</p>
    <p>Your PrimePower CRM verification code is:</p>
    <p style="font-size: 32px; font-weight: bold; letter-spacing: 8px;">{{ $code }}</p>
    <p>This code expires in {{ $expiresInMinutes }} minutes. If you did not try to log in, you can safely ignore this email.</p>
    <p>— PrimePower Manpower Services</p>
</body>
</html>
