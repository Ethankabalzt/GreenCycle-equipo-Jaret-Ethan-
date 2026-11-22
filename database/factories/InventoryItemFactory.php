<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\ShopItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'shop_item_id' => ShopItem::factory(),
            'quantity' => 1,
        ];
    }
}
