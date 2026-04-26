<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Ensure every serialization of the user (including nested relations such
     * as Comment->user or Post->user) carries the resolved S3/CloudFront URLs
     * so the frontend never has to deal with bare storage paths.
     */
    protected $appends = ['avatar_url', 'cover_image_url'];

    // public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'username',
        'email',
        'mobile',
        'password',
        'bio',
        'date_of_birth',
        'avatar',
        'cover_image',
        'allow_team_invites',
        'old_system_password',
        'try_login_in_new_system',
        'is_blocked',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'try_login_in_new_system' => 'boolean',
            'is_blocked' => 'boolean',
        ];
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'user_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'user_id');
    }

    /**
     * Send the password reset notification.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Accessor: fully-qualified avatar URL resolved against the correct disk.
     * Mirrors the logic used in AuthController::resolveMediaUrl.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->resolveMediaUrlAttribute($this->avatar);
    }

    /**
     * Accessor: fully-qualified cover image URL.
     */
    public function getCoverImageUrlAttribute(): ?string
    {
        return $this->resolveMediaUrlAttribute($this->cover_image);
    }

    /**
     * Resolve a stored media path to a public URL:
     * - Full URLs (http/https) pass through untouched.
     * - Paths written by the new S3 flow ("upload/", "avatars/", "covers/") go through the S3 disk.
     * - Legacy paths fall back to the "public" disk for backward compatibility.
     */
    protected function resolveMediaUrlAttribute(?string $path): ?string
    {
        if (!$path) {
            return null;
        }
        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }
        try {
            if (str_starts_with($path, 'upload/') || str_starts_with($path, 'avatars/') || str_starts_with($path, 'covers/')) {
                return Storage::disk('s3')->url($path);
            }
            return Storage::disk('public')->url($path);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
