<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;color:#182230;">
    <div style="padding:28px 12px;">
        <table role="presentation" cellspacing="0" cellpadding="0" width="100%" style="max-width:620px;margin:0 auto;background:#ffffff;border:1px solid #e4e8ef;border-radius:14px;">
            <tr>
                <td style="padding:26px 28px;background:#153e75;color:#ffffff;border-radius:14px 14px 0 0;">
                    <p style="margin:0 0 8px;font-size:12px;letter-spacing:1.5px;text-transform:uppercase;opacity:.85;">{{ $applicationName }}</p>
                    <h1 style="margin:0;font-size:22px;line-height:1.35;">{{ $title }}</h1>
                </td>
            </tr>
            <tr>
                <td style="padding:28px;">
                    <p style="margin:0 0 22px;font-size:16px;line-height:1.65;">Bonjour,</p>
                    <p style="margin:0 0 26px;font-size:15px;line-height:1.7;color:#344054;">{{ $notificationBody }}</p>
                    @if ($url)
                        <p style="margin:0 0 26px;">
                            <a href="{{ $url }}" style="display:inline-block;padding:12px 18px;background:#153e75;border-radius:8px;color:#ffffff;text-decoration:none;font-weight:700;">Consulter PROXIWORK</a>
                        </p>
                    @endif
                    <p style="margin:0;font-size:13px;line-height:1.6;color:#667085;">Pour votre sécurité, connectez-vous à PROXIWORK directement si vous avez un doute sur ce message. Ne communiquez jamais votre mot de passe ou un code de connexion par e-mail.</p>
                </td>
            </tr>
            <tr>
                <td style="padding:18px 28px;border-top:1px solid #e4e8ef;font-size:12px;line-height:1.5;color:#667085;">
                    Ce message est une notification automatique de {{ $applicationName }}. Merci de ne pas répondre à cet e-mail.
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
