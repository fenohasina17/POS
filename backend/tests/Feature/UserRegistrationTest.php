<?php

namespace Tests\Feature;

use App\Models\PointOfSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class UserRegistrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function un_administrateur_peut_creer_un_utilisateur_valide()
    {
        $pos = PointOfSale::factory()->create();
        $admin = User::factory()->create(['point_of_sale_id' => $pos->id]);
        \Laravel\Sanctum\Sanctum::actingAs($admin);

        $payload = [
            'name' => 'Jean Caissier',
            'email' => 'jean@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'point_of_sale_id' => $pos->id,
        ];

        $response = $this->postJson('/api/users', $payload);
        $response->assertStatus(201);
    }
}