<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActiveEffectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->shopItem?->name,
            'type' => $this->shopItem?->type,
            'activated_at' => $this->activated_at?->utc()->toIso8601ZuluString(),
            'expires_at' => $this->expires_at?->utc()->toIso8601ZuluString(),
            'active_flag' => $this->active_flag,
        ];
    }
}
