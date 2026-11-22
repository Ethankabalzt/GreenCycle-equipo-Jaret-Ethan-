<?php

namespace Tests\Feature;

use App\Models\SeedType;
use App\Models\Tree;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HarvestTest extends TestCase
{
    use RefreshDatabase;

    public function test_cosecha_exitosa_otorga_monedas(): void
    {
        $user = User::factory()->create(['coins' => 0]);
        $seedType = SeedType::factory()->create(['harvest_coins' => 150]);
        $tree = Tree::factory()->for($user)->create([
            'seed_type_id' => $seedType->id,
            'level' => Tree::MAX_LEVEL,
            'status' => Tree::MATURE,
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/trees/{$tree->id}/harvest")
            ->assertOk()
            ->assertJsonPath('reward', 150)
            ->assertJsonPath('coins', 150)
            ->assertJsonPath('tree.status', Tree::HARVESTED);

        $tree->refresh();
        $this->assertEquals(150, $user->fresh()->coins);
        $this->assertEquals(Tree::HARVESTED, $tree->status);
        $this->assertNotNull($tree->harvested_at);
    }

    public function test_no_se_puede_cosechar_dos_veces(): void
    {
        $user = User::factory()->create(['coins' => 0]);
        $tree = Tree::factory()->for($user)->create([
            'level' => Tree::MAX_LEVEL,
            'status' => Tree::MATURE,
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/trees/{$tree->id}/harvest")->assertOk();

        $this->postJson("/api/trees/{$tree->id}/harvest")
            ->assertStatus(409)
            ->assertJsonPath('error', 'El árbol no está listo para cosechar.');

        $this->assertEquals(100, $user->fresh()->coins);
    }

    public function test_un_arbol_no_maduro_no_se_puede_cosechar(): void
    {
        $user = User::factory()->create(['coins' => 0]);
        $tree = Tree::factory()->for($user)->create([
            'level' => 3,
            'status' => Tree::ACTIVE,
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/trees/{$tree->id}/harvest")->assertStatus(409);

        $this->assertEquals(0, $user->fresh()->coins);
        $this->assertEquals(Tree::ACTIVE, $tree->fresh()->status);
    }

    public function test_no_se_puede_cosechar_el_arbol_de_otra_persona(): void
    {
        $owner = User::factory()->create(['coins' => 0]);
        $intruder = User::factory()->create(['coins' => 0]);
        $tree = Tree::factory()->for($owner)->create([
            'level' => Tree::MAX_LEVEL,
            'status' => Tree::MATURE,
        ]);
        Sanctum::actingAs($intruder);

        $this->postJson("/api/trees/{$tree->id}/harvest")->assertForbidden();

        $this->assertEquals(0, $intruder->fresh()->coins);
        $this->assertEquals(Tree::MATURE, $tree->fresh()->status);
    }

    public function test_un_usuario_no_autenticado_no_puede_cosechar(): void
    {
        $tree = Tree::factory()->create([
            'level' => Tree::MAX_LEVEL,
            'status' => Tree::MATURE,
        ]);

        $this->postJson("/api/trees/{$tree->id}/harvest")->assertUnauthorized();
    }
}
