<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\PointOfSale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // POST /api/register
    // =========================================================================

    #[Test]
    public function register_creates_a_new_user_and_returns_a_token()
    {
        $response = $this->postJson('/api/register', [
            'name'     => 'Alice Dupont',
            'email'    => 'alice@igp.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['message', 'token', 'user'])
                 ->assertJsonPath('user.email', 'alice@igp.com')
                 ->assertJsonPath('user.name', 'Alice Dupont');

        $this->assertDatabaseHas('users', ['email' => 'alice@igp.com']);
        $this->assertNotEmpty($response->json('token'));
    }

    #[Test]
    public function register_stores_a_hashed_password()
    {
        $this->postJson('/api/register', [
            'name'     => 'Alice Dupont',
            'email'    => 'alice@igp.com',
            'password' => 'secret123',
        ]);

        $user = User::where('email', 'alice@igp.com')->first();
        $this->assertTrue(Hash::check('secret123', $user->password));
    }

    #[Test]
    public function register_returns_422_when_email_is_already_taken()
    {
        User::factory()->create(['email' => 'alice@igp.com']);

        $response = $this->postJson('/api/register', [
            'name'     => 'Alice Dupont',
            'email'    => 'alice@igp.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('message', 'La validation a échoué')
                 ->assertJsonStructure(['errors' => ['email']]);
    }

    #[Test]
    #[DataProvider('missingRegisterFieldsProvider')]
    public function register_returns_422_when_required_fields_are_missing(array $payload)
    {
        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(422);
    }

    public static function missingRegisterFieldsProvider(): array
    {
        return [
            'no name'     => [['email' => 'a@igp.com', 'password' => 'secret123']],
            'no email'    => [['name' => 'Alice', 'password' => 'secret123']],
            'no password' => [['name' => 'Alice', 'email' => 'a@igp.com']],
        ];
    }

    #[Test]
    public function register_returns_422_when_password_is_too_short()
    {
        $response = $this->postJson('/api/register', [
            'name'     => 'Alice',
            'email'    => 'alice@igp.com',
            'password' => 'short',
        ]);

        $response->assertStatus(422)
                 ->assertJsonStructure(['errors' => ['password']]);
    }

    // =========================================================================
    // POST /api/login
    // =========================================================================

    #[Test]
    public function login_returns_a_token_for_valid_credentials()
    {
        $pos = PointOfSale::factory()->create();
        User::factory()->create([
            'email'            => 'bob@igp.com',
            'password'         => Hash::make('password123'),
            'point_of_sale_id' => $pos->id,
        ]);

        $response = $this->postJson('/api/login', [
            'email'    => 'bob@igp.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['message', 'token', 'user'])
                 ->assertJsonPath('user.email', 'bob@igp.com');

        $this->assertNotEmpty($response->json('token'));
    }

    #[Test]
    public function login_returns_401_for_wrong_password()
    {
        $pos = PointOfSale::factory()->create();
        User::factory()->create([
            'email'            => 'bob@igp.com',
            'password'         => Hash::make('correct-password'),
            'point_of_sale_id' => $pos->id,
        ]);

        $response = $this->postJson('/api/login', [
            'email'    => 'bob@igp.com',
            'password' => 'wrong-password', // ≥8 chars — passe validation, échoue auth
        ]);

        $response->assertStatus(401)
                 ->assertJsonPath('message', 'Identifiants invalides');
    }

    #[Test]
    public function login_returns_401_for_nonexistent_email()
    {
        $response = $this->postJson('/api/login', [
            'email'    => 'nobody@igp.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
                 ->assertJsonPath('message', 'Identifiants invalides');
    }

    #[Test]
    public function login_returns_422_when_fields_are_missing()
    {
        $this->postJson('/api/login', [])->assertStatus(422);
    }

    // =========================================================================
    // GET /api/me
    // =========================================================================

    #[Test]
    public function me_returns_the_authenticated_user()
    {
        $pos  = PointOfSale::factory()->create();
        $user = User::factory()->create(['point_of_sale_id' => $pos->id]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->getJson('/api/me');

        $response->assertStatus(200)
                 ->assertJsonPath('user.email', $user->email)
                 ->assertJsonPath('user.id', $user->id);
    }

    #[Test]
    public function me_returns_401_without_token()
    {
        $this->getJson('/api/me')->assertStatus(401);
    }

    // =========================================================================
    // POST /api/logout
    // =========================================================================

    #[Test]
    public function logout_deletes_the_current_token()
    {
        $pos        = PointOfSale::factory()->create();
        $user       = User::factory()->create(['point_of_sale_id' => $pos->id]);
        $tokenModel = $user->createToken('test');
        $plainToken = $tokenModel->plainTextToken;
        $tokenId    = $tokenModel->accessToken->id;

        $response = $this->withHeader('Authorization', 'Bearer ' . $plainToken)
                         ->postJson('/api/logout');

        $response->assertStatus(200)
                 ->assertJsonPath('message', 'Déconnexion réussie');

        // Le token doit avoir été supprimé de la base
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    #[Test]
    public function logout_returns_401_without_token()
    {
        $this->postJson('/api/logout')->assertStatus(401);
    }

    // =========================================================================
    // Flux complet login → profil → logout
    // =========================================================================

    #[Test]
    public function full_auth_flow_login_then_logout()
    {
        $pos = PointOfSale::factory()->create();
        User::factory()->create([
            'email'            => 'test@example.com',
            'password'         => Hash::make('password123'),
            'point_of_sale_id' => $pos->id,
        ]);

        // 1. Login
        $loginResponse = $this->postJson('/api/login', [
            'email'    => 'test@example.com',
            'password' => 'password123',
        ]);
        $loginResponse->assertStatus(200);
        $token = $loginResponse->json('token');
        $this->assertNotEmpty($token);

        // 2. Logout immédiat — vérifie la suppression du token en base.
        // Note : on ne fait pas d'appel /api/me entre les deux pour éviter
        // que Sanctum (mode stateful, domaine localhost) réauthentifie via
        // session plutôt que via Bearer, ce qui rendrait currentAccessToken()
        // non suppressible (TransientToken). /me est couvert par son propre test.
        $tokenId = \Laravel\Sanctum\PersonalAccessToken::findToken($token)?->id;

        $this->withHeader('Authorization', 'Bearer ' . $token)
             ->postJson('/api/logout')
             ->assertStatus(200)
             ->assertJsonPath('message', 'Déconnexion réussie');

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }
}
