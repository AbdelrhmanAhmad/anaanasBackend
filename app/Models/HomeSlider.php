<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Home banner slide. Managed exclusively from the Filament back-office.
 * The frontend reads it via the public GET /api/home/sliders endpoint.
 */
class HomeSlider extends Model
{
    protected $fillable = [
        'title',
        'image_desktop_ar',
        'image_desktop_en',
        'image_mobile_ar',
        'image_mobile_en',
        'url',
        'open_in_new_tab',
        'country_id',
        'starts_at',
        'ends_at',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'open_in_new_tab' => 'boolean',
        'sort_order' => 'integer',
        'country_id' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /**
     * Ensure clients always receive the resolved CloudFront/S3 URLs along with
     * the raw paths — we never want the frontend to do path resolution.
     */
    protected $appends = [
        'image_desktop_ar_url',
        'image_desktop_en_url',
        'image_mobile_ar_url',
        'image_mobile_en_url',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function getImageDesktopArUrlAttribute(): ?string
    {
        return $this->resolveMediaUrl($this->image_desktop_ar);
    }

    public function getImageDesktopEnUrlAttribute(): ?string
    {
        return $this->resolveMediaUrl($this->image_desktop_en);
    }

    public function getImageMobileArUrlAttribute(): ?string
    {
        return $this->resolveMediaUrl($this->image_mobile_ar);
    }

    public function getImageMobileEnUrlAttribute(): ?string
    {
        return $this->resolveMediaUrl($this->image_mobile_en);
    }

    /**
     * Resolve a stored media path to a public URL.
     * Mirrors User::resolveMediaUrlAttribute to keep behaviour consistent.
     */
    protected function resolveMediaUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }
        try {
            // Filament's FileUpload writes to the S3 disk under the configured directory.
            return Storage::disk('s3')->url($path);
        } catch (\Throwable) {
            try {
                return Storage::disk('public')->url($path);
            } catch (\Throwable) {
                return null;
            }
        }
    }
}
