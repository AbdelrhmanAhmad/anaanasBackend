<x-filament-panels::page>
    <link rel="stylesheet" href="{{ asset('css/filament/chat-report-view.css') }}?v=1" />

    @php
        /** @var \App\Filament\Resources\ChatReports\Pages\ViewChatReport $this */
        $report = $this->getRecord();
        $status = (string) ($report->status ?? 'pending');
        $statusClass = in_array($status, ['pending', 'reviewed', 'dismissed', 'action_taken'], true)
            ? $status
            : 'pending';
    @endphp

    <div class="crv-root" dir="rtl" lang="ar">
        <div class="crv-grid">
            {{-- بطاقة الحالة --}}
            <div class="crv-card">
                <div class="crv-label">الحالة</div>
                <span class="crv-badge crv-badge--{{ $statusClass }}">
                    {{ $this->statusLabel($status) }}
                </span>

                <div class="crv-spacer"></div>
                <div class="crv-label">التصنيف</div>
                <div class="crv-value">{{ $this->categoryLabel($report->category) }}</div>

                <div class="crv-spacer"></div>
                <div class="crv-label">تاريخ البلاغ</div>
                <div class="crv-value">{{ optional($report->created_at)->format('Y-m-d H:i') ?? '—' }}</div>

                @if($report->reviewed_at)
                    <div class="crv-spacer"></div>
                    <div class="crv-label">تاريخ المراجعة</div>
                    <div class="crv-value">{{ $report->reviewed_at->format('Y-m-d H:i') }}</div>
                @endif
            </div>

            {{-- الأطراف --}}
            <div class="crv-card">
                <div class="crv-label">المُبلِّغ</div>
                @if($reporter)
                    <div class="crv-user">
                        @if(!empty($reporter['avatar']))
                            <img src="{{ $reporter['avatar'] }}" alt="" class="crv-avatar" />
                        @else
                            <span class="crv-avatar crv-avatar--placeholder" aria-hidden>👤</span>
                        @endif
                        <div>
                            <div class="crv-user-name">{{ $reporter['name'] }}</div>
                            <div class="crv-user-meta">#{{ $reporter['id'] }} · {{ $reporter['email'] ?? '—' }}</div>
                        </div>
                    </div>
                @else
                    <div class="crv-text">—</div>
                @endif

                <div class="crv-divider"></div>

                <div class="crv-label">المُبلَّغ عنه</div>
                @if($reportedUser)
                    <div class="crv-user">
                        @if(!empty($reportedUser['avatar']))
                            <img src="{{ $reportedUser['avatar'] }}" alt="" class="crv-avatar" />
                        @else
                            <span class="crv-avatar crv-avatar--placeholder" aria-hidden>👤</span>
                        @endif
                        <div>
                            <div class="crv-user-name">{{ $reportedUser['name'] }}</div>
                            <div class="crv-user-meta">#{{ $reportedUser['id'] }} · {{ $reportedUser['email'] ?? '—' }}</div>
                        </div>
                    </div>
                @else
                    <div class="crv-text">—</div>
                @endif
            </div>

            {{-- الإعلان والسبب --}}
            <div class="crv-card">
                <div class="crv-label">الإعلان</div>
                @if($post)
                    <a href="{{ $post['admin_url'] }}" class="crv-link" target="_blank" rel="noopener">
                        #{{ $post['id'] }} — {{ \Illuminate\Support\Str::limit($post['title'] ?? '', 60) }}
                    </a>
                @else
                    <div class="crv-text">—</div>
                @endif

                <div class="crv-spacer"></div>
                <div class="crv-label">سبب البلاغ</div>
                <p class="crv-text">{{ $report->reason ?? '—' }}</p>

                @if(!empty($report->description))
                    <div class="crv-spacer"></div>
                    <div class="crv-label">تفاصيل إضافية</div>
                    <p class="crv-text">{{ $report->description }}</p>
                @endif

                @if(!empty($report->admin_notes))
                    <div class="crv-spacer"></div>
                    <div class="crv-label">ملاحظات الإدارة</div>
                    <p class="crv-text">{{ $report->admin_notes }}</p>
                @endif
            </div>
        </div>

        {{-- المحادثة --}}
        <div class="crv-chat-panel">
            <div class="crv-chat-header">
                <h2 class="crv-chat-title">محتوى المحادثة ({{ count($messages) }})</h2>
                <span class="crv-chat-hint">من الأقدم إلى الأحدث</span>
            </div>

            <div class="crv-chat-body" dir="auto">
                @forelse($messages as $msg)
                    <div class="crv-msg-row {{ !empty($msg['is_reporter']) ? 'crv-msg-row--reporter' : 'crv-msg-row--other' }}">
                        <div class="crv-bubble {{ !empty($msg['is_reporter']) ? 'crv-bubble--reporter' : 'crv-bubble--other' }}">
                            <div class="crv-bubble-meta">
                                @if(!empty($msg['sender_avatar']))
                                    <img src="{{ $msg['sender_avatar'] }}" class="crv-bubble-avatar" alt="" />
                                @endif
                                <span>{{ $msg['sender_name'] }}</span>
                                <span>·</span>
                                <span>{{ $msg['created_at'] }}</span>
                            </div>
                            <div>{{ $msg['body'] }}</div>
                            @if(($msg['type'] ?? 'text') !== 'text' && !empty($msg['file_url']))
                                <a href="{{ $msg['file_url'] }}" target="_blank" rel="noopener" class="crv-file-link">
                                    عرض الملف
                                </a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="crv-empty">لا توجد رسائل في هذه المحادثة.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-panels::page>
