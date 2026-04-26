<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Translatable\HasTranslations;

class Section extends Model
{
    use HasTranslations;
    protected $fillable = ['id','slug','name','icon','image','is_active','sort_order'];
//    public $incrementing = false;
    public array $translatable = ['name'];
    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function attribute(): HasOne
    {
        return $this->hasOne(Attribute::class, 'section_id');
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


        if (isset($array["icon"]) && $array["icon"]){
            $array["icon_full_path"] =  $array["icon"] ? \Storage::disk('s3')->url($array["icon"]):null;
        }

        if (isset($array["image"]) && $array["image"]){
            $array["image_full_path"] =  $array["image"] ? \Storage::disk('s3')->url($array["image"]):null;
        }


        return $array;
    }

//  php artisan make:filament-resource Section --generate
}
