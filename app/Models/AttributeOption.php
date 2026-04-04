<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class AttributeOption extends Model
{
    use SoftDeletes;
    use HasTranslations;
//    public $incrementing = false;
//    public $incrementing = false;
    public array $translatable = ['name' ,'slug'];
    protected $fillable = [
        'attribute_id',
        'id',
        'name',
        'slug',
        'section_id',
        'category_id',
    ];

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    /**
     * 🔵 خيارات طفل (اختيارات تابعة parent_option_id)
     */
    public function children()
    {
        return $this->hasMany(Attribute::class, 'parent_option_id');
    }

    public function toArray($pars = true)
    {
        // خُذ الأريي العادي من المودل (بما فيه العلاقات)
        $array = parent::toArray();

        if ($pars){

            // مر على الحقول المترجمه و رجّع قيمة اللغة الحاليه فقط
            foreach ($this->getTranslatableAttributes() as $attribute) {
                $array[$attribute] = $this->getTranslation($attribute, app()->getLocale());
            }
        }

        return $array;
    }

}
