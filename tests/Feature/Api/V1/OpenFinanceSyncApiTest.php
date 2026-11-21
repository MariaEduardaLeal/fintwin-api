<?php

namespace Tests\Feature\Api\V1;

use App\Events\BankSyncRequestedEvent;
use App\Integrations\Pluggy\PluggyClient;
use App\Jobs\SyncPluggyBankTransactionsJob;
use App\Jobs\SyncPluggyCreditInvoicesJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class OpenFinanceSyncApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_finance_endpoints_require_authentication(): void
    {
        $this->postJson('/api/v1/pluggy/connect-token')->assertUnauthorized();
        $this->postJson('/api/v1/sync/open-finance', ['pluggy_item_id' => 'item-1'])->assertUnauthorized();
    }

    public function test_sync_endpoint_validates_payload_and_queues_pluggy_jobs(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/sync/open-finance', [])->assertUnprocessable();

        $response = $this->postJson('/api/v1/sync/open-finance', [
            'pluggy_item_id' => 'item-abc-123',
            'from_date' => '2026-10-01',
        ]);

        $response->assertAccepted()
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.pluggy_item_id', 'item-abc-123')
            ->assertJsonPath('data.queues.0', 'open-finance');

        Queue::assertPushedOn('open-finance', SyncPluggyBankTransactionsJob::class, static function (SyncPluggyBankTransactionsJob $job) use ($user): bool {
            return $job->userId === $user->id
                && $job->pluggyItemId === 'item-abc-123'
                && $job->fromDate === '2026-10-01';
        });

        Queue::assertPushedOn('open-finance', SyncPluggyCreditInvoicesJob::class, static function (SyncPluggyCreditInvoicesJob $job) use ($user): bool {
            return $job->userId === $user->id
                && $job->pluggyItemId === 'item-abc-123'
                && $job->fromDate === '2026-10-01';
        });

        Queue::assertPushedTimes(SyncPluggyBankTransactionsJob::class, 1);
        Queue::assertPushedTimes(SyncPluggyCreditInvoicesJob::class, 1);
    }

    public function test_sync_service_dispatches_bank_sync_requested_event(): void
    {
        Event::fake([BankSyncRequestedEvent::class]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/sync/open-finance', [
            'pluggy_item_id' => 'item-evt-1',
        ])->assertAccepted();

        Event::assertDispatched(BankSyncRequestedEvent::class, static function (BankSyncRequestedEvent $event) use ($user): bool {
            return $event->userId === $user->id && $event->pluggyItemId === 'item-evt-1';
        });
    }

    public function test_connect_token_endpoint_returns_pluggy_access_token(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $pluggyMock = Mockery::mock(PluggyClient::class);
        $pluggyMock->shouldReceive('createConnectToken')
            ->once()
            ->with(null, (string) $user->id)
            ->andReturn(['accessToken' => 'connect-jwt-xyz']);

        $this->app->instance(PluggyClient::class, $pluggyMock);

        $this->postJson('/api/v1/pluggy/connect-token')
            ->assertOk()
            ->assertJsonPath('data.access_token', 'connect-jwt-xyz');
    }
}
