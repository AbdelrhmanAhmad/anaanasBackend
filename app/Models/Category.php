<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Category extends Model
{

    use HasTranslations;
//    public $incrementing = false;

    public array $translatable = ['name'];
    protected $fillable = [ 'id','section_id','slug','name','icon','image','is_active','sort_order'];
    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(Attribute::class, 'category_id');
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
//



}
