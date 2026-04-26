<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class PostImages extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'post_id',
        'image',
    ];

    protected $appends = [
        'image_full_url',
    ];

    /**
     * Resolve the public URL for the post image.
     *
     * Mirrors the smart resolution used in `User::resolveMediaUrlAttribute`:
     *  - Full URLs pass through untouched.
     *  - "upload/" paths (current S3 storage layout) go through the S3 disk
     *    so the configured `AWS_URL` (CloudFront in production) is honoured.
     *  - Anything else falls back to the public disk for legacy compatibility.
     *
     * The previous implementation always called `Storage::disk('s3')->url(...)`
     * which can return a `localhost` URL when the S3 disk URL is misconfigured
     * or the config cache is stale; the explicit branch makes the failure
     * mode obvious and gives us a predictable fallback.
     */
    public function getImageFullUrlAttribute(): ?string
    {
        $path = $this->image;
        if (!$path) {
            return null;
        }
        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }
        try {
            if (str_starts_with($path, 'upload/')
                || str_starts_with($path, 'posts/')
                || str_starts_with($path, 'photos/')) {
                return Storage::disk('s3')->url($path);
            }
            return Storage::disk('public')->url($path);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
