<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Attribute extends Model
{
    use SoftDeletes;
//    public $incrementing = false;

    use HasTranslations;
    public array $translatable = ['name' , 'slug'];
    protected $fillable = [
        'name',
        'id',
        'input_type',
        'key_name',
        'required',
        'filterable',
        'parent_option_id',
        'multiselect',
        'parent_id',
        'slug',
        'multi_level',
        'section_id',
        'category_id',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'filterable' => 'boolean',
            'multiselect' => 'boolean',
            'multi_level' => 'boolean',
        ];
    }

    public function attributeOptions(): HasMany
    {
        return $this->hasMany(AttributeOption::class, 'attribute_id');
    }




    /**
     * 🔵 علاقة Attribute الأب (للشجرة)
     */
    public function parent()
    {
        return $this->belongsTo(Attribute::class, 'parent_id');
    }

    /**
     * 🔵 علاقة Attributes الأبناء (للشجرة - multi-level)
     */
    public function children()
    {
        return $this->hasMany(Attribute::class, 'parent_id');
    }

    public function parentOption()
    {
        return $this->belongsTo(AttributeOption::class, 'parent_option_id');
    }


    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
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
