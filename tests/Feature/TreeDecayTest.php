<?php

namespace Tests\Feature;

use App\Models\ActiveEffect;
use App\Models\ShopItem;
use App\Models\Tree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreeDecayTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_hay_deterioro_antes_de_24_horas(): void
    {
        $tree = Tree::factory()->create([
            'health' => 50,
            'planted_at' => now(),
        ]);

        $this->travel(23)->hours();
        $this->artisan('trees:apply-decay')->assertSuccessful();

        $this->assertEquals(50, $tree->fresh()->health);
    }

    public function test_el_deterioro_quita_salud_y_avanza_el_checkpoint(): void
    {
        $tree = Tree::factory()->create([
            'health' => 50,
            'planted_at' => now(),
        ]);

        $this->travel(25)->hours();
        $this->artisan('trees:apply-decay')->assertSuccessful();

        $tree->refresh();
        $this->assertEquals(40, $tree->health);
        $this->assertNotNull($tree->last_decay_at);

        // Ejecutar de nuevo sin avanzar el tiempo no vuelve a castigar.
        $this->artisan('trees:apply-decay')->assertSuccessful();
        $this->assertEquals(40, $tree->fresh()->health);
    }

    public function test_aplica_un_deterioro_por_cada_24_horas(): void
    {
        $tree = Tree::factory()->create([
            'health' => 50,
            'planted_at' => now(),
        ]);

        $this->travel(49)->hours();
        $this->artisan('trees:apply-decay')->assertSuccessful();

        $this->assertEquals(30, $tree->fresh()->health);
    }

    public function test_el_arbol_muere_cuando_la_salud_llega_a_cero(): void
    {
        $tree = Tree::factory()->create([
            'health' => 10,
            'planted_at' => now(),
        ]);

        $this->travel(25)->hours();
        $this->artisan('trees:apply-decay')->assertSuccessful();

        $tree->refresh();
        $this->assertEquals(0, $tree->health);
        $this->assertEquals(Tree::DEAD, $tree->status);
        $this->assertNull($tree->next_care_at);
    }

    public function test_un_protector_activo_evita_el_deterioro(): void
    {
        $tree = Tree::factory()->create([
            'health' => 50,
            'planted_at' => now(),
        ]);
        $protector = ShopItem::factory()->protector()->create();

        ActiveEffect::create([
            'tree_id' => $tree->id,
            'shop_item_id' => $protector->id,
            'activated_at' => now(),
            'expires_at' => now()->addDays(3),
            'active_flag' => true,
        ]);

        $this->travel(25)->hours();
        $this->artisan('trees:apply-decay')->assertSuccessful();

        $this->assertEquals(50, $tree->fresh()->health);
    }

    public function test_los_efectos_vencidos_se_liberan(): void
    {
        $tree = Tree::factory()->create();
        $protector = ShopItem::factory()->protector()->create();

        $effect = ActiveEffect::create([
            'tree_id' => $tree->id,
            'shop_item_id' => $protector->id,
            'activated_at' => now()->subDay(),
            'expires_at' => now()->subMinute(),
            'active_flag' => true,
        ]);

        $this->artisan('effects:release-expired')->assertSuccessful();

        $this->assertFalse($effect->fresh()->active_flag);
    }
}
