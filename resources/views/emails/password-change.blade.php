<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Password Change Authentication</title>
    <style>
        body { background:#f7f7f8; font-family:Arial, Helvetica, sans-serif; color:#111827; margin:0; padding:0; }
        .container { max-width:560px; margin:24px auto; background:#ffffff; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; }
        .header { text-align:center; padding:20px 20px 0 20px; }
        .logo { width:96px; height:96px; border-radius:50%; object-fit:contain; }
        .content { padding:24px 24px 8px 24px; }
        h1 { margin:0 0 12px 0; font-size:22px; color:#dc2626; }
        p { margin:8px 0; line-height:1.5; }
        .code { margin:16px auto; text-align:center; font-size:28px; letter-spacing:6px; font-weight:700; color:#111827; background:#f9fafb; border:2px solid #dc2626; border-radius:10px; padding:14px 0; width:80%; }
        .footer { padding:0 24px 24px 24px; color:#6b7280; font-size:12px; }
        .warning { background:#fef2f2; border-left:4px solid #dc2626; padding:12px; margin:16px 0; border-radius:4px; }
    </style>
  </head>
  <body>
    <div class="container">
      <div class="header">
        @if(!empty($logoUrl))
            <img class="logo" src="{{ asset('logo-seal.png') }}" alt="Cabuyao Cityhall" />
        @endif
      </div>
      <div class="content">
        <h1>Password Change Request</h1>
        <p>Hello {{ $name ?? 'User' }},</p>
        <p>
          We received a request to change your password for your Cabuyao Cityhall Web Kios account.
          Please use the authentication code below to proceed with the password change:
        </p>
        <div class="code">{{ $code }}</div>
        <div class="warning">
          <strong>Important:</strong> This code will expire in 10 minutes. If you did not request a password change, please ignore this email and consider securing your account.
        </div>
        <p>For security purposes, never share this code with anyone. Our team will never ask for this code.</p>
        <p class="footer">Thank you,<br/>The MIS Team<br/>Cabuyao Cityhall</p>
      </div>
    </div>
  </body>
</html>
