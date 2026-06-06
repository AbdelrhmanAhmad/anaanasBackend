<?php

namespace App\Services;

use App\Models\Post;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class PostCreationRateLimitService
{
    /**
     * @return array{
     *     can_create: bool,
     *     reason: 'interval'|'hourly'|null,
     *     retry_after_seconds: int,
     *     posts_in_last_hour: int,
     *     hourly_limit: int,
     *     interval_minutes: int,
     *     message: string|null,
     *     next_allowed_at: string|null,
     * }
     */
    public function check(int $userId, ?CarbonInterface $now = null): array
    {
        $now = $now ? Carbon::parse($now) : now();
        $intervalMinutes = max(1, (int) config('post_rate_limit.interval_minutes', 5));
        $hourlyMax = max(1, (int) config('post_rate_limit.hourly_max', 5));
        $hourWindowMinutes = max(1, (int) config('post_rate_limit.hourly_window_minutes', 60));

        $intervalWaitSeconds = 0;
        $hourlyWaitSeconds = 0;
        $reason = null;

        $lastPost = $this->baseQuery($userId)
            ->orderByDesc('created_at')
            ->first(['created_at']);

        if ($lastPost?->created_at) {
            $nextAfterInterval = $lastPost->created_at->copy()->addMinutes($intervalMinutes);
            if ($now->lt($nextAfterInterval)) {
                $intervalWaitSeconds = max(0, $nextAfterInterval->getTimestamp() - $now->getTimestamp());
                $reason = 'interval';
            }
        }

        $windowStart = $now->copy()->subMinutes($hourWindowMinutes);
        $recentPosts = $this->baseQuery($userId)
            ->where('created_at', '>=', $windowStart)
            ->orderBy('created_at')
            ->get(['created_at']);

        $postsInHour = $recentPosts->count();

        if ($postsInHour >= $hourlyMax) {
            $oldestInWindow = $recentPosts->first();
            if ($oldestInWindow?->created_at) {
                $nextAfterHourly = $oldestInWindow->created_at->copy()->addMinutes($hourWindowMinutes);
                if ($now->lt($nextAfterHourly)) {
                    $wait = max(0, $nextAfterHourly->getTimestamp() - $now->getTimestamp());
                    if ($wait > $hourlyWaitSeconds) {
                        $hourlyWaitSeconds = $wait;
                        if ($hourlyWaitSeconds >= $intervalWaitSeconds) {
                            $reason = 'hourly';
                        }
                    }
                }
            }
        }

        $retryAfterSeconds = max($intervalWaitSeconds, $hourlyWaitSeconds);
        $canCreate = $retryAfterSeconds <= 0;

        $payload = [
            'can_create' => $canCreate,
            'reason' => $canCreate ? null : $reason,
            'retry_after_seconds' => max(0, $retryAfterSeconds),
            'posts_in_last_hour' => $postsInHour,
            'hourly_limit' => $hourlyMax,
            'interval_minutes' => $intervalMinutes,
            'message' => null,
            'next_allowed_at' => null,
        ];

        if (! $canCreate) {
            $payload['next_allowed_at'] = $now->copy()->addSeconds($retryAfterSeconds)->toIso8601String();
            $payload['message'] = $this->buildMessage(
                $reason ?? 'interval',
                $retryAfterSeconds,
                $hourlyMax,
                $intervalMinutes,
            );
        }

        return $payload;
    }

    private function baseQuery(int $userId): Builder
    {
        $query = Post::query()->where('user_id', $userId);

        if (Schema::hasColumn('posts', 'post_type')) {
            $query->where(function (Builder $q) {
                $q->whereNull('post_type')->orWhere('post_type', 'listing');
            });
        }

        return $query;
    }

    private function buildMessage(string $reason, int $retryAfterSeconds, int $hourlyMax, int $intervalMinutes): string
    {
        $time = $this->formatWaitTime($retryAfterSeconds);

        if ($reason === 'hourly') {
            return __('post_rate_limit.hourly', [
                'max' => $hourlyMax,
                'time' => $time,
            ]);
        }

        return __('post_rate_limit.interval', [
            'minutes' => $intervalMinutes,
            'time' => $time,
        ]);
    }

    private function formatWaitTime(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $minutes = intdiv($seconds, 60);
        $remaining = $seconds % 60;

        if ($minutes > 0) {
            return __('post_rate_limit.time_minutes_seconds', [
                'minutes' => $minutes,
                'seconds' => str_pad((string) $remaining, 2, '0', STR_PAD_LEFT),
            ]);
        }

        return __('post_rate_limit.time_seconds', ['seconds' => $seconds]);
    }
}
