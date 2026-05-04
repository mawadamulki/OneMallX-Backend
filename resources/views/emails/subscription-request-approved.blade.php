<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('app.email_subscription_approved_title') }}</title>
</head>
<body>
    <p>{{ __('app.email_hello_name', ['name' => $applicantName]) }}</p>
    @if($isStoreAccount)
        <p>{{ __('app.email_subscription_approved_body_store') }}</p>
    @else
        <p>{{ __('app.email_subscription_approved_body_service') }}</p>
    @endif
    <p>{{ __('app.email_subscription_approved_login_hint') }}</p>
    @if(!empty($loginUrl))
        <p><a href="{{ $loginUrl }}">{{ __('app.email_login_link_label') }}</a></p>
    @endif
</body>
</html>
