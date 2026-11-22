<?php

namespace Tests\Feature;

use App\Models\ActiveEffect;
use App\Models\InventoryItem;
use App\Models\ShopItem;
use App\Models\Tree;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_listado_de_inventario(): void
    {
        $user = User::factory()->create();
        $item = ShopItem::factory()->accelerator()->create();
        InventoryItem::factory()->for($user)->create([
            'shop_item_id' => $item->id,
            'quantity' => 3,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/inventory')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.quantity', 3)
            ->assertJsonPath('0.shop_item.id', $item->id);
    }

    public function test_el_acelerador_elimina_el_cooldown(): void
    {
        $user = User::factory()->create();
        $tree = Tree::factory()->for($user)->create([
            'next_care_at' => now()->addMinutes(60),
        ]);
        $item = ShopItem::factory()->accelerator()->create(['effect_value' => 60]);
        $inventory = InventoryItem::factory()->for($user)->create([
            'shop_item_id' => $item->id,
            'quantity' => 1,
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/inventory/{$inventory->id}/use", [
            'tree_id' => $tree->id,
        ])
            ->assertOk()
            ->assertJsonPath('tree.id', $tree->id)
            ->assertJsonPath('inventory', null);

        $this->assertNull($tree->fresh()->next_care_at);
        $this->assertDatabaseCount('inventory_items', 0);

        $this->postJson("/api/trees/{$tree->id}/care")->assertOk();
    }

    public function test_el_acelerador_no_se_gasta_si_el_arbol_ya_esta_listo(): void
    {
        $user = User::factory()->create();
        $tree = Tree::factory()->for($user)->create(['next_care_at' => null]);
        $item = ShopItem::factory()->accelerator()->create();
        $inventory = InventoryItem::factory()->for($user)->create([
            'shop_item_id' => $item->id,
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/inventory/{$inventory->id}/use", [
            'tree_id' => $tree->id,
        ])->assertStatus(409);

        $this->assertEquals(1, $inventory->fresh()->quantity);
    }

    public function test_el_fertilizante_suma_progreso_y_sube_de_nivel(): void
    {
        $user = User::factory()->create();
        $tree = Tree::factory()->for($user)->create([
            'level' => 0,
            'progress' => 4,
        ]);
        $item = ShopItem::factory()->fertilizer()->create(['effect_value' => 2]);
        $inventory = InventoryItem::factory()->for($user)->create([
            'shop_item_id' => $item->id,
            'quantity' => 2,
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/inventory/{$inventory->id}/use", [
            'tree_id' => $tree->id,
        ])
            ->assertOk()
            ->assertJsonPath('tree.level', 1)
            ->assertJsonPath('tree.progress', 1)
            ->assertJsonPath('inventory.quantity', 1);
    }

    public function test_el_protector_crea_un_efecto_activo(): void
    {
        $user = User::factory()->create();
        $tree = Tree::factory()->for($user)->create();
        $item = ShopItem::factory()->protector()->create(['effect_duration_min' => 1440]);
        $inventory = InventoryItem::factory()->for($user)->create([
            'shop_item_id' => $item->id,
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/inventory/{$inventory->id}/use", [
            'tree_id' => $tree->id,
        ])->assertOk();

        $this->assertDatabaseHas('active_effects', [
            'tree_id' => $tree->id,
            'shop_item_id' => $item->id,
            'active_flag' => true,
        ]);
        $this->assertTrue($tree->fresh()->hasActiveProtector());
    }

    public function test_no_se_puede_usar_un_item_de_otra_persona(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $tree = Tree::factory()->for($intruder)->create();
        $inventory = InventoryItem::factory()->for($owner)->create();
        Sanctum::actingAs($intruder);

        $this->postJson("/api/inventory/{$inventory->id}/use", [
            'tree_id' => $tree->id,
        ])->assertForbidden();

        $this->assertEquals(1, $inventory->fresh()->quantity);
    }

    public function test_no_se_puede_aplicar_un_item_sobre_el_arbol_de_otra_persona(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $tree = Tree::factory()->for($owner)->create();
        $inventory = InventoryItem::factory()->for($intruder)->create();
        Sanctum::actingAs($intruder);

        $this->postJson("/api/inventory/{$inventory->id}/use", [
            'tree_id' => $tree->id,
        ])->assertForbidden();

        $this->assertEquals(1, $inventory->fresh()->quantity);
    }

    public function test_no_se_puede_aplicar_un_item_a_un_arbol_maduro(): void
    {
        $user = User::factory()->create();
        $tree = Tree::factory()->for($user)->create([
            'level' => Tree::MAX_LEVEL,
            'status' => Tree::MATURE,
        ]);
        $inventory = InventoryItem::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/inventory/{$inventory->id}/use", [
            'tree_id' => $tree->id,
        ])->assertStatus(409);

        $this->assertEquals(1, $inventory->fresh()->quantity);
        $this->assertDatabaseCount('active_effects', 0);
    }

    public function test_el_protector_evita_un_segundo_protector_simultaneo(): void
    {
        $user = User::factory()->create();
        $tree = Tree::factory()->for($user)->create();
        $item = ShopItem::factory()->protector()->create();
        $inventory = InventoryItem::factory()->for($user)->create([
            'shop_item_id' => $item->id,
            'quantity' => 2,
        ]);
        ActiveEffect::create([
            'tree_id' => $tree->id,
            'shop_item_id' => $item->id,
            'activated_at' => now(),
            'expires_at' => now()->addDay(),
            'active_flag' => true,
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/inventory/{$inventory->id}/use", [
            'tree_id' => $tree->id,
        ])->assertStatus(409);

        $this->assertEquals(2, $inventory->fresh()->quantity);
    }
}
