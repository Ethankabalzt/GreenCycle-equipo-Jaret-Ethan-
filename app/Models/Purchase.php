<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Purchase extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'shop_item_id',
        'quantity',
        'total_cost',
        'idempotency_key',
        'purchased_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'total_cost' => 'integer',
        'purchased_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shopItem(): BelongsTo
    {
        return $this->belongsTo(ShopItem::class);
    }
}
