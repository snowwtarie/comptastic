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

        // Sanctum's EnsureFrontendRequestsAreStateful middleware only attaches
        // session middleware for requests whose Referer/Origin matches a
        // configured stateful domain (SANCTUM_STATEFUL_DOMAINS). Without this
        // header the request isn't treated as stateful and session assertions
        // below would fail with "Session store not set on request."
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

    public function test_it_throttles_repeated_login_attempts(): void
    {
        User::factory()->create([
            'email' => 'marius@example.com',
            'password' => Hash::make('password123'),
        ]);

        $payload = [
            'email' => 'marius@example.com',
            'password' => 'wrong-password',
        ];

        // The throttle:6,1 middleware allows 6 attempts per minute. The
        // rate limiter keys unauthenticated requests by IP (see
        // Illuminate\Routing\Middleware\ThrottleRequests::resolveRequestSignature),
        // so 6 requests from the same test client all count toward the same
        // bucket. Each of these still fails validation normally (422).
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/login', $payload);
            $response->assertStatus(422);
        }

        // The 7th attempt within the same window should be throttled.
        $response = $this->postJson('/api/login', $payload);
        $response->assertStatus(429);
    }

    public function test_it_logs_out_and_invalidates_the_session(): void
    {
        $user = User::factory()->create();

        // See the comment on the login test above: Sanctum only treats this
        // request as stateful (and thus session-backed) when the Referer
        // matches a configured stateful domain.
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

    public function test_it_updates_the_authenticated_users_profile(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);

        $response = $this->actingAs($user)->patchJson('/api/profile', [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);

        $response->assertOk();
        $this->assertSame('New Name', $response->json('data.name'));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name', 'email' => 'new@example.com']);
    }

    public function test_it_rejects_a_profile_email_already_used_by_another_user(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->actingAs($user)->patchJson('/api/profile', [
            'name' => $user->name,
            'email' => 'taken@example.com',
        ]);

        $response->assertStatus(422);
    }

    public function test_it_updates_the_password_when_current_password_is_correct(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $response = $this->actingAs($user)->putJson('/api/password', [
            'current_password' => 'old-password',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertNoContent();
        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));
    }

    public function test_it_rejects_a_password_change_with_the_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $response = $this->actingAs($user)->putJson('/api/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertStatus(422);
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }
}
