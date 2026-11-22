<?php

namespace Database\Seeders;

use App\Models\ShopItem;
use Illuminate\Database\Seeder;

class ShopItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            [
                'name' => 'Acelerador de crecimiento',
                'type' => ShopItem::ACCELERATOR,
                'effect_value' => 60,
                'cost' => 120,
                'effect_duration_min' => null,
                'description' => 'Reduce en 60 minutos el tiempo de espera del próximo cuidado.',
            ],
            [
                'name' => 'Fertilizante orgánico',
                'type' => ShopItem::FERTILIZER,
                'effect_value' => 2,
                'cost' => 150,
                'effect_duration_min' => null,
                'description' => 'Suma 2 puntos de progreso al árbol elegido.',
            ],
            [
                'name' => 'Protector de raíces',
                'type' => ShopItem::PROTECTOR,
                'effect_value' => null,
                'cost' => 200,
                'effect_duration_min' => 1440,
                'description' => 'Protege el árbol del deterioro durante 24 horas.',
            ],
        ];

        foreach ($items as $item) {
            ShopItem::updateOrCreate(['name' => $item['name']], $item);
        }
    }
}
