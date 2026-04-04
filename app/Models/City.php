<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class City extends Model
{
    use SoftDeletes;
    use HasTranslations;
//    public $incrementing = false;

    public array $translatable = ['name'];
    protected $fillable = [
        'country_id',
        'name',
        'is_active',
        'banner',
        'id',

    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
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
