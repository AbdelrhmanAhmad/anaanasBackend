<?php

namespace App\Services;

use App\Models\CategoryFollow;
use App\Models\Country;
use App\Models\Post;
use App\Models\SectionFollow;
use App\Models\User;
use App\Models\UserNotification;
use App\Mail\PendingPostReviewMail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class PostModerationService
{
    public function initialStatusForUser(?User $user): string
    {
        if ($user && $user->auto_approve_posts) {
            return Post::STATUS_ACTIVE;
        }

        return Post::STATUS_PENDING_REVIEW;
    }

    public function isPubliclyVisible(Post $post, ?int $viewerUserId = null): bool
    {
        if ($post->status === Post::STATUS_ACTIVE) {
            return true;
        }

        if ($viewerUserId === null) {
            return false;
        }

        return (int) $post->user_id === (int) $viewerUserId;
    }

    /** @param  Builder<Post>  $query */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('status', Post::STATUS_ACTIVE);
    }

    public function approve(Post $post): Post
    {
        $updates = ['status' => Post::STATUS_ACTIVE];
        if (Schema::hasColumn('posts', 'publish_date') && empty($post->publish_date)) {
            $updates['publish_date'] = now();
        }

        $post->update($updates);
        $post->refresh();
        $post->loadMissing(['user', 'section', 'category']);

        $this->afterPublish($post);

        $this->notifyAuthor($post, approved: true);

        return $post;
    }

    public function reject(Post $post, ?string $adminNotes = null): Post
    {
        $post->update(['status' => Post::STATUS_REJECTED]);
        $this->notifyAuthor($post, approved: false, adminNotes: $adminNotes);

        return $post->refresh();
    }

    public function approveAllPendingForUser(User $user): int
    {
        $posts = Post::query()
            ->where('user_id', (int) $user->id)
            ->whereIn('status', Post::pendingReviewStatuses())
            ->get();

        foreach ($posts as $post) {
            $this->approve($post);
        }

        return $posts->count();
    }

    public function afterPublish(Post $post): void
    {
        $authorId = (int) $post->user_id;
        $this->notifyFollowersAboutNewPost($post, $authorId);
        $this->broadcastPostCreated($post);
    }

    public function notifyAdminsOfPendingReview(Post $post): void
    {
        if (! in_array($post->status, Post::pendingReviewStatuses(), true)) {
            return;
        }

        $post->loadMissing(['user', 'section', 'category']);

        $emails = app(PlatformSettingsService::class)->contactNotificationEmails();
        if ($emails === []) {
            return;
        }

        foreach ($emails as $email) {
            try {
                Mail::to($email)->send(new PendingPostReviewMail($post));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    protected function notifyAuthor(Post $post, bool $approved, ?string $adminNotes = null): void
    {
        try {
            UserNotification::query()->create([
                'user_id' => (int) $post->user_id,
                'type' => $approved ? 'post.approved' : 'post.rejected',
                'title_ar' => $approved
                    ? __('post_moderation.approved_title', [], 'ar')
                    : __('post_moderation.rejected_title', [], 'ar'),
                'title_en' => $approved
                    ? __('post_moderation.approved_title', [], 'en')
                    : __('post_moderation.rejected_title', [], 'en'),
                'body_ar' => $approved
                    ? __('post_moderation.approved_body', ['title' => (string) $post->title], 'ar')
                    : ($adminNotes ?: __('post_moderation.rejected_body', ['title' => (string) $post->title], 'ar')),
                'body_en' => $approved
                    ? __('post_moderation.approved_body', ['title' => (string) $post->title], 'en')
                    : ($adminNotes ?: __('post_moderation.rejected_body', ['title' => (string) $post->title], 'en')),
                'url' => '/post/' . (int) $post->id,
                'data' => [
                    'post_id' => (int) $post->id,
                    'status' => $approved ? Post::STATUS_ACTIVE : Post::STATUS_REJECTED,
                ],
                'is_read' => false,
            ]);
        } catch (\Throwable) {
            // non-critical
        }
    }

    protected function notifyFollowersAboutNewPost(Post $post, int $authorId): void
    {
        $sectionFollowerIds = SectionFollow::query()
            ->where('section_id', (int) $post->section_id)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $categoryFollowerIds = [];
        if ($post->category_id) {
            $categoryFollowerIds = CategoryFollow::query()
                ->where('category_id', (int) $post->category_id)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $targets = collect(array_merge($sectionFollowerIds, $categoryFollowerIds))
            ->filter(fn ($id) => $id > 0 && $id !== $authorId)
            ->unique()
            ->values();

        if ($targets->isEmpty()) {
            return;
        }

        $sectionName = $post->section?->name ?? 'القسم';
        $title = (string) $post->title;
        foreach ($targets as $userId) {
            UserNotification::query()->create([
                'user_id' => (int) $userId,
                'type' => 'follow.new_post',
                'title_ar' => 'إعلان جديد في قسم تتابعه',
                'title_en' => 'New listing in a followed section',
                'body_ar' => $title !== '' ? mb_substr($title, 0, 180) : ('قسم: ' . $sectionName),
                'body_en' => $title !== '' ? mb_substr($title, 0, 180) : ('Section: ' . $sectionName),
                'url' => '/post/' . (int) $post->id,
                'data' => [
                    'post_id' => (int) $post->id,
                    'section_id' => (int) $post->section_id,
                    'category_id' => (int) ($post->category_id ?? 0),
                ],
                'is_read' => false,
            ]);
        }
    }

    protected function broadcastPostCreated(Post $post): void
    {
        try {
            $countryCode = '';
            if (! empty($post->country_id)) {
                $country = Country::find((int) $post->country_id);
                if ($country) {
                    $countryCode = strtolower((string) ($country->iso2 ?: $country->iso_code ?: ''));
                }
            }

            $authorName = $post->user?->name;
            RealtimeBroadcaster::publishToCountry($countryCode, 'post.created', [
                'post_id' => (int) $post->id,
                'section_id' => (int) ($post->section_id ?? 0),
                'category_id' => (int) ($post->category_id ?? 0),
                'country_id' => (int) ($post->country_id ?? 0),
                'country_code' => $countryCode ?: null,
                'title' => (string) ($post->title ?? ''),
                'author_id' => (int) ($post->user_id ?? 0),
                'author_name' => $authorName,
                'created_at' => optional($post->created_at)->toIso8601String(),
                'url' => '/post/' . (int) $post->id,
            ]);
        } catch (\Throwable) {
            // best-effort
        }
    }
}
