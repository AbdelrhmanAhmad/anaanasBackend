<x-mail::message>
# {{ __('email_verification.mail_heading') }}

{{ __('email_verification.mail_intro') }}

<x-mail::panel>
**{{ $code }}**
</x-mail::panel>

{{ __('email_verification.mail_expires', ['minutes' => $expiresMinutes]) }}

{{ __('email_verification.mail_ignore') }}

{{ __('email_verification.mail_regards') }},<br>
{{ config('app.name') }}
</x-mail::message>
