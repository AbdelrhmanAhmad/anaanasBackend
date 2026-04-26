<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Report metadata --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="rounded-xl bg-white dark:bg-gray-900 p-4 shadow-sm border border-gray-200 dark:border-gray-800">
                <div class="text-xs text-gray-500 mb-1">الحالة</div>
                @php
                    $statusColors = [
                        'pending' => 'bg-amber-100 text-amber-800',
                        'reviewed' => 'bg-blue-100 text-blue-800',
                        'dismissed' => 'bg-gray-100 text-gray-700',
                        'action_taken' => 'bg-rose-100 text-rose-800',
                    ];
                    $cls = $statusColors[$record?->status ?? 'pending'] ?? 'bg-gray-100 text-gray-700';
                @endphp
                <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium {{ $cls }}">
                    {{ $record?->status ?? '—' }}
                </span>
                <div class="mt-3 text-xs text-gray-500">التصنيف</div>
                <div class="text-sm font-medium">{{ $record?->category ?? '—' }}</div>
                <div class="mt-3 text-xs text-gray-500">تاريخ البلاغ</div>
                <div class="text-sm">{{ optional($record?->created_at)->toDateTimeString() }}</div>
            </div>

            <div class="rounded-xl bg-white dark:bg-gray-900 p-4 shadow-sm border border-gray-200 dark:border-gray-800">
                <div class="text-xs text-gray-500 mb-1">المُبلِّغ</div>
                @if($reporter)
                    <div class="flex items-center gap-3">
                        @if(!empty($reporter['avatar']))
                            <img src="{{ $reporter['avatar'] }}" alt="" class="w-10 h-10 rounded-full object-cover" />
                        @endif
                        <div>
                            <div class="font-semibold">{{ $reporter['name'] }}</div>
                            <div class="text-xs text-gray-500">{{ $reporter['email'] ?? '—' }}</div>
                        </div>
                    </div>
                @endif

                <div class="mt-4 text-xs text-gray-500">المُبلَّغ عنه</div>
                @if($reportedUser)
                    <div class="flex items-center gap-3 mt-1">
                        @if(!empty($reportedUser['avatar']))
                            <img src="{{ $reportedUser['avatar'] }}" alt="" class="w-10 h-10 rounded-full object-cover" />
                        @endif
                        <div>
                            <div class="font-semibold">{{ $reportedUser['name'] }}</div>
                            <div class="text-xs text-gray-500">{{ $reportedUser['email'] ?? '—' }}</div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="rounded-xl bg-white dark:bg-gray-900 p-4 shadow-sm border border-gray-200 dark:border-gray-800">
                <div class="text-xs text-gray-500 mb-1">الإعلان</div>
                @if($post)
                    <a href="{{ url('/admin/posts/' . $post['id']) }}" class="text-primary-600 hover:underline font-medium">
                        #{{ $post['id'] }} — {{ $post['title'] }}
                    </a>
                @else
                    <div>—</div>
                @endif

                <div class="mt-3 text-xs text-gray-500">سبب البلاغ</div>
                <p class="text-sm leading-6">{{ $record?->reason ?? '—' }}</p>

                @if(!empty($record?->description))
                    <div class="mt-3 text-xs text-gray-500">تفاصيل إضافية</div>
                    <p class="text-sm leading-6 whitespace-pre-line">{{ $record->description }}</p>
                @endif
            </div>
        </div>

        {{-- Conversation --}}
        <div class="rounded-xl bg-white dark:bg-gray-900 shadow-sm border border-gray-200 dark:border-gray-800">
            <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                <h2 class="font-semibold">محتوى المحادثة ({{ count($messages) }})</h2>
                <span class="text-xs text-gray-500">مرتبة من الأقدم إلى الأحدث</span>
            </div>

            <div class="p-5 space-y-3 max-h-[640px] overflow-y-auto" dir="auto">
                @forelse($messages as $msg)
                    @php $isReporter = (int) $msg['sender_id'] === (int) $record->reporter_id; @endphp
                    <div class="flex {{ $isReporter ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[78%] rounded-2xl px-4 py-2 text-sm
                                    {{ $isReporter ? 'bg-primary-50 text-primary-900' : 'bg-gray-100 text-gray-900' }}">
                            <div class="text-[11px] mb-1 opacity-70 flex items-center gap-2">
                                @if(!empty($msg['sender_avatar']))
                                    <img src="{{ $msg['sender_avatar'] }}" class="w-4 h-4 rounded-full object-cover" alt="" />
                                @endif
                                <span>{{ $msg['sender_name'] }}</span>
                                <span>·</span>
                                <span>{{ $msg['created_at'] }}</span>
                            </div>
                            <div class="whitespace-pre-line">{{ $msg['body'] }}</div>
                            @if(($msg['type'] ?? 'text') !== 'text' && !empty($msg['file_url']))
                                <a href="{{ $msg['file_url'] }}" target="_blank" class="block mt-2 text-xs underline">
                                    عرض الملف
                                </a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-center text-gray-500 py-12">لا توجد رسائل في هذه المحادثة.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-panels::page>
