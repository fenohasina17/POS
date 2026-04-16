<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\PointOfSale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function un_utilisateur_peut_se_connecter_et_voir_son_profil()
    {
        $pos = PointOfSale::factory()->create();
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'point_of_sale_id' => $pos->id,
        ]);

        // 1. Login
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $loginResponse->assertStatus(200);
        $token = $loginResponse->json('token') ?? $loginResponse->json('access_token');

        // 2. Accès au profil (Route /api/me)
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->getJson('/api/me');

        $response->assertStatus(200);
        
        // Correction selon ton retour : l'email est dans l'objet 'user'
        $response->assertJsonPath('user.email', 'test@example.com');
    }
}