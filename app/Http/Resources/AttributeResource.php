<?php

namespace App\Http\Resources;

use App\Models\Attribute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Attribute */
class AttributeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'input_type' => $this->input_type,
            'key_name' => $this->key_name,
            'required' => $this->required,
            'filterable' => $this->filterable,
            'multiselect' => $this->multiselect,
            'multi_level' => $this->multi_level,
            'section_id' => $this->section_id,
            'category_id' => $this->category_id,
            'sort' => $this->sort,
            'slug' => $this->slug,
//            'created_at' => $this->created_at,
//            'updated_at' => $this->updated_at,
            'attribute_options_count' => $this->attribute_options_count,
            'children_count' => $this->children_count,

            'attributeOptions' => AttributeOptionResource::collection($this->whenLoaded('attributeOptions')),
            'children' => AttributeResource::collection($this->whenLoaded('children')),
            'parent' => new AttributeResource($this->whenLoaded('parent')),
        ];
    }
}
