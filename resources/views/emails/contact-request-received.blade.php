<x-mail::message>
# طلب تواصل جديد

**#{{ $contactRequest->id }}** — {{ $contactRequest->subject }}

**الاسم:** {{ $contactRequest->name }}

**البريد:** {{ $contactRequest->email }}

@if($contactRequest->user_id)
**المستخدم:** #{{ $contactRequest->user_id }}
@endif

**الرسالة:**

{{ $contactRequest->message }}

<x-mail::button :url="$panelUrl">
عرض في لوحة التحكم
</x-mail::button>

شكراً،<br>
{{ config('app.name') }}
</x-mail::message>
