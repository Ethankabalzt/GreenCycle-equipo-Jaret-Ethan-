<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_usuario_puede_registrarse(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'test@example.com')
            ->assertJsonStructure(['message', 'user' => ['id', 'name', 'email', 'coins'], 'token']);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_un_usuario_puede_iniciar_y_cerrar_sesion(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $login->assertOk()
            ->assertJsonPath('user.email', 'login@example.com')
            ->assertJsonStructure(['message', 'user', 'token']);

        $token = $login->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_un_usuario_no_autenticado_no_puede_acceder_a_rutas_protegidas(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
        $this->getJson('/api/trees')->assertUnauthorized();
    }
}
