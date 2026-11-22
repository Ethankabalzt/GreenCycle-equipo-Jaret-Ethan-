<?php

namespace Tests\Feature;

use App\Models\Tree;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TreeCareTest extends TestCase
{
    use RefreshDatabase;

    // 1. Cuidado exitoso
    public function test_cuidado_exitoso(): void
    {
        $user = User::factory()->create();
        $tree = Tree::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/trees/{$tree->id}/care")
            ->assertOk()
            ->assertJsonPath('tree.progress', 1)
            ->assertJsonPath('tree.health', 70)
            ->assertJsonStructure([
                'message',
                'tree' => ['id', 'level', 'health', 'progress', 'status', 'next_care_at'],
            ]);

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            $response->json('tree.next_care_at'),
        );
        $this->assertNotNull($tree->fresh()->next_care_at);
        $this->assertDatabaseCount('cares', 1);
    }

    // 2. Bloqueo por cooldown
    public function test_bloqueo_por_cooldown(): void
    {
        $user = User::factory()->create();
        $tree = Tree::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/trees/{$tree->id}/care")->assertOk();

        $tree->refresh();
        $stateAfterCare = $tree->only(['progress', 'health', 'level', 'last_cared_at', 'next_care_at']);

        $this->postJson("/api/trees/{$tree->id}/care")
            ->assertStatus(409)
            ->assertJsonStructure(['error', 'next_care_at']);

        $tree->refresh();
        $this->assertEquals($stateAfterCare['progress'], $tree->progress);
        $this->assertEquals($stateAfterCare['health'], $tree->health);
        $this->assertEquals($stateAfterCare['level'], $tree->level);
        $this->assertEquals($stateAfterCare['last_cared_at']?->toIso8601String(), $tree->last_cared_at?->toIso8601String());
        $this->assertEquals($stateAfterCare['next_care_at']?->toIso8601String(), $tree->next_care_at?->toIso8601String());
        $this->assertDatabaseCount('cares', 1);
    }

    // 3. Ascenso de nivel tras 5 cuidados válidos
    public function test_ascenso_de_nivel(): void
    {
        $user = User::factory()->create();
        $tree = Tree::factory()->for($user)->create();
        Sanctum::actingAs($user);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson("/api/trees/{$tree->id}/care")->assertOk();
            $this->travel(61)->minutes(); // salta el cooldown
        }

        $tree->refresh();
        $this->assertEquals(1, $tree->level);
        $this->assertEquals(0, $tree->progress);
    }

    // 4. Evolución a MATURE
    public function test_evolucion_a_mature(): void
    {
        $user = User::factory()->create();
        $tree = Tree::factory()->for($user)->create([
            'level' => 9,
            'progress' => 4,
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/trees/{$tree->id}/care")
            ->assertOk()
            ->assertJsonPath('tree.status', 'MATURE')
            ->assertJsonPath('tree.level', 10);

        $this->assertEquals('MATURE', $tree->fresh()->status);
    }

    // 5. Protección de recursos cruzados
    public function test_proteccion_de_recursos_cruzados(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $tree = Tree::factory()->for($owner)->create();
        Sanctum::actingAs($intruder);

        $this->postJson("/api/trees/{$tree->id}/care")->assertForbidden();

        $this->assertEquals(0, $tree->fresh()->progress);
    }

    // Extra recomendado: estado terminal
    public function test_arbol_muerto_no_recibe_cuidados(): void
    {
        $user = User::factory()->create();
        $tree = Tree::factory()->for($user)->create(['status' => Tree::DEAD]);
        Sanctum::actingAs($user);

        $this->postJson("/api/trees/{$tree->id}/care")->assertStatus(409);
    }

    public function test_arbol_cosechado_no_recibe_cuidados(): void
    {
        $user = User::factory()->create();
        $tree = Tree::factory()->for($user)->create(['status' => Tree::HARVESTED]);
        Sanctum::actingAs($user);

        $this->postJson("/api/trees/{$tree->id}/care")->assertStatus(409);
    }

    public function test_la_salud_no_supera_100(): void
    {
        $user = User::factory()->create();
        $tree = Tree::factory()->for($user)->create(['health' => 95]);
        Sanctum::actingAs($user);

        $this->postJson("/api/trees/{$tree->id}/care")
            ->assertOk()
            ->assertJsonPath('tree.health', 100);

        $this->assertEquals(100, $tree->fresh()->health);
    }
}
