<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Email Verification</title>
    <style>
        body { background:#f7f7f8; font-family:Arial, Helvetica, sans-serif; color:#111827; margin:0; padding:0; }
        .container { max-width:560px; margin:24px auto; background:#ffffff; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; }
        .header { text-align:center; padding:20px 20px 0 20px; }
        .logo { width:96px; height:96px; border-radius:50%; object-fit:contain; }
        .content { padding:24px 24px 8px 24px; }
        h1 { margin:0 0 12px 0; font-size:22px; color:#b91c1c; }
        p { margin:8px 0; line-height:1.5; }
        .code { margin:16px auto; text-align:center; font-size:28px; letter-spacing:6px; font-weight:700; color:#111827; background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:14px 0; width:80%; }
        .footer { padding:0 24px 24px 24px; color:#6b7280; font-size:12px; }
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
        <h1>Account Verification</h1>
        <p>Hello {{ $name ?? 'User' }},</p>
        <p>
          We received a request to verify your account with Cabuyao Cityhall Web Kios.
          Please use the verification code below to continue:
        </p>
        <div class="code">{{ $code }}</div>
        <p>This code will expire in 10 minutes. If you did not request this, please ignore this email.</p>
        <p class="footer">Thank you,<br/>The MIS Team</p>
      </div>
    </div>
  </body>
</html>


