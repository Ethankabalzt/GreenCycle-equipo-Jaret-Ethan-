<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActiveEffect extends Model
{
    protected $fillable = [
        'tree_id',
        'shop_item_id',
        'activated_at',
        'expires_at',
        'active_flag',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
        'active_flag' => 'boolean',
    ];

    public function tree(): BelongsTo
    {
        return $this->belongsTo(Tree::class);
    }

    public function shopItem(): BelongsTo
    {
        return $this->belongsTo(ShopItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('active_flag', true)
            ->where('expires_at', '>', now());
    }
}
