<?php

namespace Database\Seeders;

use App\Models\SeedType;
use App\Models\Tree;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SeedTypeSeeder::class,
            ShopItemSeeder::class,
        ]);

        $password = env('DEMO_PASSWORD');

        if (! $password) {
            $this->command?->warn(
                'DEMO_PASSWORD no está definida: se omite la creación de la cuenta demo.'
            );

            return;
        }

        $user = User::firstOrCreate(
            ['email' => 'demo@greencycle.test'],
            ['name' => 'Demo', 'password' => $password]
        );

        if ($user->wasRecentlyCreated || $user->coins < 500) {
            $user->coins = 500;
            $user->save();
        }

        if ($user->trees()->count() > 0) {
            return;
        }

        $seedType = SeedType::orderBy('id')->firstOrFail();

        $user->trees()->create([
            'seed_type_id' => $seedType->id,
            'level' => 4,
            'health' => 80,
            'progress' => 4,
            'status' => Tree::ACTIVE,
            'planted_at' => now()->subDays(2),
            'last_cared_at' => now()->subHours(2),
            'last_decay_at' => now(),
            'next_care_at' => null,
        ]);

        $user->trees()->create([
            'seed_type_id' => $seedType->id,
            'level' => Tree::MAX_LEVEL,
            'health' => 100,
            'progress' => 0,
            'status' => Tree::MATURE,
            'planted_at' => now()->subDays(30),
            'last_cared_at' => now()->subDay(),
            'last_decay_at' => now(),
            'next_care_at' => null,
        ]);

        // Evita que el árbol maduro de ejemplo muera por deterioro mientras se prueba.
        $this->command?->info('Cuenta demo creada con 500 monedas y dos árboles.');
    }
}
