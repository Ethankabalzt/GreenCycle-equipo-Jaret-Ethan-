<?php

namespace Tests\Feature;

use App\Models\SeedType;
use App\Models\ShopItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogo_de_la_tienda(): void
    {
        ShopItem::factory()->accelerator()->create(['name' => 'Acelerador']);
        ShopItem::factory()->protector()->create(['name' => 'Protector']);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/shop/items')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['name' => 'Acelerador']);
    }

    public function test_catalogo_de_semillas(): void
    {
        SeedType::factory()->count(2)->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/seed-types')
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_compra_exitosa_descuenta_monedas_y_agrega_al_inventario(): void
    {
        $user = User::factory()->create(['coins' => 500]);
        $item = ShopItem::factory()->accelerator()->create(['cost' => 100]);
        Sanctum::actingAs($user);

        $this->postJson('/api/shop/purchases', [
            'shop_item_id' => $item->id,
            'quantity' => 2,
        ])
            ->assertCreated()
            ->assertJsonPath('coins', 300)
            ->assertJsonPath('purchase.total_cost', 200)
            ->assertJsonPath('inventory.quantity', 2);

        $this->assertEquals(300, $user->fresh()->coins);
        $this->assertDatabaseHas('inventory_items', [
            'user_id' => $user->id,
            'shop_item_id' => $item->id,
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('purchases', [
            'user_id' => $user->id,
            'shop_item_id' => $item->id,
            'quantity' => 2,
            'total_cost' => 200,
        ]);
    }

    public function test_las_compras_sucesivas_acumulan_inventario(): void
    {
        $user = User::factory()->create(['coins' => 500]);
        $item = ShopItem::factory()->fertilizer()->create(['cost' => 100]);
        Sanctum::actingAs($user);

        $this->postJson('/api/shop/purchases', [
            'shop_item_id' => $item->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->postJson('/api/shop/purchases', [
            'shop_item_id' => $item->id,
            'quantity' => 2,
        ])
            ->assertCreated()
            ->assertJsonPath('inventory.quantity', 3);

        $this->assertEquals(200, $user->fresh()->coins);
        $this->assertDatabaseCount('inventory_items', 1);
        $this->assertDatabaseCount('purchases', 2);
    }

    public function test_no_se_puede_comprar_sin_monedas_suficientes(): void
    {
        $user = User::factory()->create(['coins' => 50]);
        $item = ShopItem::factory()->accelerator()->create(['cost' => 100]);
        Sanctum::actingAs($user);

        $this->postJson('/api/shop/purchases', [
            'shop_item_id' => $item->id,
            'quantity' => 1,
        ])
            ->assertStatus(409)
            ->assertJsonPath('coins', 50)
            ->assertJsonPath('required', 100);

        $this->assertEquals(50, $user->fresh()->coins);
        $this->assertDatabaseCount('inventory_items', 0);
        $this->assertDatabaseCount('purchases', 0);
    }

    public function test_la_cantidad_debe_ser_valida(): void
    {
        $user = User::factory()->create(['coins' => 500]);
        $item = ShopItem::factory()->accelerator()->create(['cost' => 100]);
        Sanctum::actingAs($user);

        $this->postJson('/api/shop/purchases', [
            'shop_item_id' => $item->id,
            'quantity' => 0,
        ])->assertUnprocessable();

        $this->postJson('/api/shop/purchases', [
            'shop_item_id' => $item->id,
            'quantity' => 11,
        ])->assertUnprocessable();
    }
}
