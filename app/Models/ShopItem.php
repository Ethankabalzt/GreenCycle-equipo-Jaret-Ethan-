<?php

namespace App\Models;

use Database\Factories\ShopItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopItem extends Model
{
    /** @use HasFactory<ShopItemFactory> */
    use HasFactory;

    public const ACCELERATOR = 'ACCELERATOR';

    public const FERTILIZER = 'FERTILIZER';

    public const PROTECTOR = 'PROTECTOR';

    protected $fillable = [
        'name',
        'type',
        'effect_value',
        'cost',
        'effect_duration_min',
        'description',
    ];

    protected $casts = [
        'effect_value' => 'integer',
        'cost' => 'integer',
        'effect_duration_min' => 'integer',
    ];

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function activeEffects(): HasMany
    {
        return $this->hasMany(ActiveEffect::class);
    }
}
