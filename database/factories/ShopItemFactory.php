<?php

namespace Database\Factories;

use App\Models\ShopItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopItem>
 */
class ShopItemFactory extends Factory
{
    protected $model = ShopItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'type' => ShopItem::ACCELERATOR,
            'effect_value' => 60,
            'cost' => 100,
            'effect_duration_min' => null,
            'description' => fake()->sentence(),
        ];
    }

    public function accelerator(): static
    {
        return $this->state(fn () => [
            'type' => ShopItem::ACCELERATOR,
            'effect_value' => 60,
            'effect_duration_min' => null,
        ]);
    }

    public function fertilizer(): static
    {
        return $this->state(fn () => [
            'type' => ShopItem::FERTILIZER,
            'effect_value' => 2,
            'effect_duration_min' => null,
        ]);
    }

    public function protector(): static
    {
        return $this->state(fn () => [
            'type' => ShopItem::PROTECTOR,
            'effect_value' => null,
            'effect_duration_min' => 1440,
        ]);
    }
}
