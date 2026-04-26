<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Country extends Model
{
    use SoftDeletes;
    use HasTranslations;
//    public $incrementing = false;
    public array $translatable = ['name'];
    protected $fillable = [
        'name',
        'flag',
        'is_active',
        'banner',
        'iso2',
        'iso_code',
        'id',

    ];



protected $appends =['flag_full_path'] ;



public function getFlagFullPathAttribute()
{
    return $this->flag ? asset("storage/".$this->flag ) : null;

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





    public function cities(): HasMany
    {
        return $this->hasMany(City::class, 'country_id');
    }
}
