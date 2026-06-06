<x-mail::message>
# إعلان جديد بانتظار المراجعة

**#{{ $post->id }}** — {{ $post->title }}

@if($post->user)
**المعلن:** {{ $post->user->name }} (#{{ $post->user_id }})

@if($post->user->email)
**البريد:** {{ $post->user->email }}
@endif

@if($post->user->mobile)
**الجوال:** {{ $post->user->mobile }}
@endif
@else
**المعلن:** #{{ $post->user_id }}
@endif

@if($post->section)
**القسم:** {{ is_array($post->section->name) ? ($post->section->name['ar'] ?? $post->section->name['en'] ?? '') : $post->section->name }}
@endif

@if($post->category)
**التصنيف:** {{ is_array($post->category->name) ? ($post->category->name['ar'] ?? $post->category->name['en'] ?? '') : $post->category->name }}
@endif

@if($post->price !== null && $post->price !== '')
**السعر:** {{ $post->price }}
@endif

<x-mail::button :url="$reviewUrl">
مراجعة الإعلانات
</x-mail::button>

<x-mail::button :url="$viewUrl">
عرض تفاصيل الإعلان
</x-mail::button>

شكراً،<br>
{{ config('app.name') }}
</x-mail::message>
