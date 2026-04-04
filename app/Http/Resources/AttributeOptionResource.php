<?php

namespace App\Http\Resources;

use App\Models\AttributeOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AttributeOption */
class AttributeOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
//            'created_at' => $this->created_at,
//            'updated_at' => $this->updated_at,
            'children_count' => $this->children_count,

            'attribute' => new AttributeResource($this->whenLoaded('attribute')),
            'children' => AttributeResource::collection($this->whenLoaded('children')),
        ];
    }
}
