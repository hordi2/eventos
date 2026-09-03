<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0; padding:24px; background:#f4f4f2; font-family:Arial, sans-serif; color:#1b1611;">
    <div style="max-width:560px; margin:0 auto; background:#ffffff; padding:32px; border-radius:12px;">
        {!! $bodyHtml !!}

        @if ($unsubscribeUrl)
            <p style="margin-top:32px; padding-top:16px; border-top:1px solid #e3e3e0; font-size:12px; color:#6d655c;">
                Vous recevez cet e-mail parce que vous êtes inscrit auprès de cette organisation.
                <a href="{{ $unsubscribeUrl }}" style="color:#6d655c;">Se désabonner</a>.
            </p>
        @endif
    </div>
</body>
</html>
