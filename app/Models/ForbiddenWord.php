<?php

namespace App\Models;

use App\Services\ForbiddenWords\ForbiddenWordsService;
use Illuminate\Database\Eloquent\Model;

class ForbiddenWord extends Model
{
    public const CATEGORIES = [
        'sexual' => 'محتوى جنسي',
        'drugs' => 'مخدرات',
        'medicines' => 'أدوية و حبوب',
        'magic_occult' => 'سحر',
        'organ_trafficking' => 'اتجار أعضاء',
        'weapons' => 'أسلحة',
        'fraud' => 'احتيال',
        'illegal_services' => 'خدمات غير قانونية',
        'violence' => 'عنف',
        'general' => 'عام',
    ];

    protected $fillable = [
        'word',
        'category',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        $flush = static function (): void {
            app(ForbiddenWordsService::class)->flushCache();
        };

        static::saved($flush);
        static::deleted($flush);
    }
}
