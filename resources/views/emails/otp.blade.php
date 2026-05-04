<!DOCTYPE html>
<html>
<head>
    <title>{{ __($subjectTranslationKey) }}</title>
</head>
<body>
    <p>{{ __('app.email_otp_hello') }}</p>
    <p>
        @if($isPasswordReset)
            {{ __('app.email_password_reset_code_label') }}
        @else
            {{ __('app.email_otp_code_label') }}
        @endif
        <strong>{{ $otp }}</strong>
    </p>
    <p>{{ __('app.email_otp_expires_minutes', ['minutes' => $expiresInMinutes]) }}</p>
</body>
</html>
