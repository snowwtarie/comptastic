<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_registers_a_new_user_and_creates_default_settings(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Marius',
            'email' => 'marius@example.com',
            'password' => 'password123',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'marius@example.com',
        ]);

        $this->assertNotNull(User::first()->settings);
    }

    public function test_it_logs_a_user_in_and_starts_a_session(): void
    {
        $user = User::factory()->create([
            'email' => 'marius@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->withHeader('Referer', 'http://localhost:5173')->postJson('/api/login', [
            'email' => 'marius@example.com',
            'password' => 'password123',
        ]);

        $response->assertNoContent();
        $this->assertAuthenticatedAs($user);
    }

    public function test_it_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'marius@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'marius@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
    }

    public function test_it_logs_out_and_invalidates_the_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->withHeader('Referer', 'http://localhost:5173')->postJson('/api/logout');

        $response->assertNoContent();
        $this->assertGuest();
    }

    public function test_it_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/user');

        $response->assertOk();
        $this->assertSame($user->email, $response->json('data.email'));
    }
}
