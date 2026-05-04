<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('app.email_subscription_rejected_title') }}</title>
</head>
<body>
    <p>{{ __('app.email_hello_name', ['name' => $applicantName]) }}</p>
    @if($isStoreAccount)
        <p>{{ __('app.email_subscription_rejected_body_store') }}</p>
    @else
        <p>{{ __('app.email_subscription_rejected_body_service') }}</p>
    @endif
    @if(!empty($rejectionReason))
        <p><strong>{{ __('app.email_rejection_reason_label') }}</strong></p>
        <p>{{ $rejectionReason }}</p>
    @endif
    @if(!empty($supportUrl))
        <p><a href="{{ $supportUrl }}">{{ __('app.email_support_link_label') }}</a></p>
    @endif
</body>
</html>
