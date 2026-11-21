<?php

namespace Tests\Feature\Api\V1;

use App\Integrations\Exceptions\IntegrationException;
use App\Integrations\Google\GoogleAuthClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_login_and_logout_with_sanctum(): void
    {
        $registerResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'Duda Leal',
            'email' => 'duda@fintwin.test',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'device_name' => 'motorola-edge',
        ]);

        $registerResponse->assertCreated()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.is_new_user', true)
            ->assertJsonPath('data.user.email', 'duda@fintwin.test')
            ->assertJsonPath('data.user.has_google_linked', false);

        $this->assertDatabaseHas('user_financial_profiles', [
            'user_id' => $registerResponse->json('data.user.id'),
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'duda@fintwin.test',
            'password' => 'Secret123!',
        ]);

        $loginResponse->assertOk()
            ->assertJsonPath('data.is_new_user', false);

        $token = $loginResponse->json('data.token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'duda@fintwin.test');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_google_login_creates_new_user_and_links_subsequent_logins_without_duplication(): void
    {
        $googleMock = Mockery::mock(GoogleAuthClient::class);
        $googleMock->shouldReceive('verifyIdToken')
            ->twice()
            ->with('google-id-token-abc')
            ->andReturn([
                'google_id' => 'sub-999',
                'email' => 'duda.google@fintwin.test',
                'name' => 'Duda Google',
                'avatar_url' => 'https://example.com/photo.jpg',
            ]);

        $this->app->instance(GoogleAuthClient::class, $googleMock);

        $firstResponse = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'google-id-token-abc',
            'device_name' => 'motorola-g84',
        ]);

        $firstResponse->assertOk()
            ->assertJsonPath('data.is_new_user', true)
            ->assertJsonPath('data.user.email', 'duda.google@fintwin.test')
            ->assertJsonPath('data.user.has_google_linked', true)
            ->assertJsonPath('data.user.avatar_url', 'https://example.com/photo.jpg');

        $this->assertDatabaseHas('user_financial_profiles', [
            'user_id' => $firstResponse->json('data.user.id'),
        ]);

        // Password login on a Google-only account (null password) must fail validation cleanly.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'duda.google@fintwin.test',
            'password' => 'anything',
        ])->assertUnprocessable();

        $secondResponse = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'google-id-token-abc',
        ]);

        $secondResponse->assertOk()
            ->assertJsonPath('data.is_new_user', false)
            ->assertJsonPath('data.user.id', $firstResponse->json('data.user.id'));

        $this->assertDatabaseCount('users', 1);
    }

    public function test_google_login_links_existing_email_user_and_rejects_invalid_token(): void
    {
        $existing = User::factory()->create([
            'email' => 'linked@fintwin.test',
            'google_id' => null,
        ]);

        $googleMock = Mockery::mock(GoogleAuthClient::class);
        $googleMock->shouldReceive('verifyIdToken')
            ->once()
            ->with('valid-token')
            ->andReturn([
                'google_id' => 'sub-linked-1',
                'email' => 'linked@fintwin.test',
                'name' => 'Linked User',
                'avatar_url' => 'https://example.com/a.png',
            ]);
        $googleMock->shouldReceive('verifyIdToken')
            ->once()
            ->with('bad-token')
            ->andThrow(new IntegrationException('google', 'Invalid Google ID token.', 401));

        $this->app->instance(GoogleAuthClient::class, $googleMock);

        $this->postJson('/api/v1/auth/google', ['id_token' => 'valid-token'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $existing->id)
            ->assertJsonPath('data.user.has_google_linked', true);

        $this->postJson('/api/v1/auth/google', ['id_token' => 'bad-token'])
            ->assertUnauthorized();
    }
}
