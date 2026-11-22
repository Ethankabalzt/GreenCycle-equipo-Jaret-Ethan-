<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShopItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'effect_value' => $this->effect_value,
            'cost' => $this->cost,
            'effect_duration_min' => $this->effect_duration_min,
            'description' => $this->description,
        ];
    }
}
