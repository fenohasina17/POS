<?php

namespace Tests\Feature;

use App\Models\CashRegisterSession;
use App\Models\Sale;
use App\Services\SyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sync.central_url'   => 'https://central.test',
            'sync.api_key'       => 'cle-test',
            'sync.terminal_id'   => 'pos-101-1',
            'sync.restaurant_id' => '101',
        ]);
        Http::fake(['central.test/*' => Http::response(['ok' => true])]);
    }

    /** Enregistrements d'une ressource envoyés au Central, tous appels confondus. */
    private function sentRecords(string $resource): array
    {
        return Http::recorded()
            ->filter(fn ($pair) => ($pair[0]->data()['resource'] ?? null) === $resource)
            ->flatMap(fn ($pair) => $pair[0]->data()['records'])
            ->all();
    }

    #[Test]
    public function a_session_closed_after_its_first_sync_is_sent_again()
    {
        $session = CashRegisterSession::factory()->create(['is_closed' => false]);
        $this->travel(30)->seconds(); // cycle de sync suivant

        app(SyncService::class)->sync();
        $this->assertCount(1, $this->sentRecords('cash_register_sessions'));

        // Rien n'a changé : rien n'est renvoyé
        app(SyncService::class)->sync();
        $this->assertCount(1, $this->sentRecords('cash_register_sessions'));

        // Clôture plus tard, comme dans la caisse
        $this->travel(5)->minutes();
        $session->update(['is_closed' => true, 'actual_cash_amount' => 100000, 'closed_at' => now()]);
        $this->travel(30)->seconds();

        app(SyncService::class)->sync();

        $sent = $this->sentRecords('cash_register_sessions');
        $this->assertCount(2, $sent);
        $this->assertTrue($sent[1]['is_closed']);
        $this->assertEquals(0, app(SyncService::class)->countPending());
    }

    #[Test]
    public function marking_as_synced_does_not_touch_updated_at()
    {
        $this->travel(-10)->minutes();
        $sale = Sale::factory()->create();
        $updatedAt = $sale->fresh()->updated_at;
        $this->travelBack();

        app(SyncService::class)->sync();

        $this->assertNotNull($sale->fresh()->synced_at);
        $this->assertEquals($updatedAt, $sale->fresh()->updated_at);
    }

    #[Test]
    public function nothing_is_marked_synced_when_the_central_refuses_the_batch()
    {
        Http::fake(['central.test/*' => Http::response(['message' => 'Clé API invalide.'], 401)]);
        CashRegisterSession::factory()->create();

        app(SyncService::class)->sync();

        $this->assertGreaterThan(0, app(SyncService::class)->countPending());
    }
}
