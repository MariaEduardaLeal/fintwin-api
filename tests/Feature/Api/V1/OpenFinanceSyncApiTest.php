<?php

namespace Tests\Feature\Api\V1;

use App\Enums\BankAccountType;
use App\Events\BankSyncRequestedEvent;
use App\Integrations\Pluggy\PluggyClient;
use App\Jobs\SyncPluggyBankTransactionsJob;
use App\Jobs\SyncPluggyCreditInvoicesJob;
use App\Models\User;
use App\Repositories\Contracts\TransactionRepositoryInterface;
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

        config()->set('integrations.pluggy.webhook_url', 'https://fintwin.freedevs.tech/api/v1/webhooks/pluggy');

        $pluggyMock = Mockery::mock(PluggyClient::class);
        $pluggyMock->shouldReceive('createConnectToken')
            ->once()
            ->with(null, (string) $user->id, 'https://fintwin.freedevs.tech/api/v1/webhooks/pluggy')
            ->andReturn(['accessToken' => 'connect-jwt-xyz']);

        $this->app->instance(PluggyClient::class, $pluggyMock);

        $this->postJson('/api/v1/pluggy/connect-token')
            ->assertOk()
            ->assertJsonPath('data.access_token', 'connect-jwt-xyz');
    }

    public function test_pluggy_webhook_queues_sync_on_item_created_with_client_user_id(): void
    {
        Queue::fake();
        config()->set('integrations.pluggy.client_id', 'pluggy-client-test');
        config()->set('integrations.pluggy.webhook_secret', null);

        $user = User::factory()->create();

        $this->postJson('/api/v1/webhooks/pluggy', [
            'event' => 'item/created',
            'eventId' => 'evt-created-1',
            'clientId' => 'pluggy-client-test',
            'itemId' => 'item-webhook-1',
            'clientUserId' => (string) $user->id,
        ])
            ->assertOk()
            ->assertExactJson(['received' => true]);

        Queue::assertPushedOn('open-finance', SyncPluggyBankTransactionsJob::class, static function (SyncPluggyBankTransactionsJob $job) use ($user): bool {
            return $job->userId === $user->id && $job->pluggyItemId === 'item-webhook-1';
        });
        Queue::assertPushedOn('open-finance', SyncPluggyCreditInvoicesJob::class, static function (SyncPluggyCreditInvoicesJob $job) use ($user): bool {
            return $job->userId === $user->id && $job->pluggyItemId === 'item-webhook-1';
        });
    }

    public function test_pluggy_webhook_resolves_user_from_existing_bank_account_on_item_updated(): void
    {
        Queue::fake();
        config()->set('integrations.pluggy.client_id', 'pluggy-client-test');
        config()->set('integrations.pluggy.webhook_secret', null);

        $user = User::factory()->create();
        app(TransactionRepositoryInterface::class)->upsertBankAccount(
            userId: $user->id,
            match: ['pluggy_account_id' => 'acc-existing-99'],
            attributes: [
                'pluggy_item_id' => 'item-existing-99',
                'institution_name' => 'Bradesco',
                'name' => 'Conta Corrente',
                'type' => BankAccountType::Checking,
                'balance' => 1000.00,
            ],
        );

        $this->postJson('/api/v1/webhooks/pluggy', [
            'event' => 'item/updated',
            'eventId' => 'evt-updated-1',
            'clientId' => 'pluggy-client-test',
            'itemId' => 'item-existing-99',
        ])
            ->assertOk()
            ->assertExactJson(['received' => true]);

        Queue::assertPushedOn('open-finance', SyncPluggyBankTransactionsJob::class, static function (SyncPluggyBankTransactionsJob $job) use ($user): bool {
            return $job->userId === $user->id && $job->pluggyItemId === 'item-existing-99';
        });
        Queue::assertPushedOn('open-finance', SyncPluggyCreditInvoicesJob::class, static function (SyncPluggyCreditInvoicesJob $job) use ($user): bool {
            return $job->userId === $user->id && $job->pluggyItemId === 'item-existing-99';
        });
    }

    public function test_pluggy_webhook_acknowledges_item_error_without_dispatching_sync(): void
    {
        Queue::fake();
        config()->set('integrations.pluggy.client_id', 'pluggy-client-test');
        config()->set('integrations.pluggy.webhook_secret', null);

        $user = User::factory()->create();

        $this->postJson('/api/v1/webhooks/pluggy', [
            'event' => 'item/error',
            'eventId' => 'evt-err-1',
            'clientId' => 'pluggy-client-test',
            'itemId' => 'item-err-1',
            'clientUserId' => (string) $user->id,
            'error' => ['code' => 'LOGIN_ERROR', 'message' => 'Invalid credentials'],
        ])
            ->assertOk()
            ->assertExactJson(['received' => true]);

        Queue::assertNothingPushed();
    }

    public function test_pluggy_webhook_rejects_mismatched_client_id(): void
    {
        Queue::fake();
        config()->set('integrations.pluggy.client_id', 'pluggy-client-test');
        config()->set('integrations.pluggy.webhook_secret', null);

        $this->postJson('/api/v1/webhooks/pluggy', [
            'event' => 'item/created',
            'clientId' => 'wrong-client-id',
            'itemId' => 'item-1',
        ])->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_pluggy_webhook_enforces_optional_webhook_secret(): void
    {
        Queue::fake();
        config()->set('integrations.pluggy.client_id', 'pluggy-client-test');
        config()->set('integrations.pluggy.webhook_secret', 'super-secret-token');

        $payload = [
            'event' => 'item/error',
            'clientId' => 'pluggy-client-test',
            'itemId' => 'item-1',
        ];

        $this->postJson('/api/v1/webhooks/pluggy', $payload)->assertUnauthorized();

        $this->withHeader('Authorization', 'Bearer wrong-secret')
            ->postJson('/api/v1/webhooks/pluggy', $payload)
            ->assertUnauthorized();

        $this->withHeader('Authorization', 'Bearer super-secret-token')
            ->postJson('/api/v1/webhooks/pluggy', $payload)
            ->assertOk()
            ->assertExactJson(['received' => true]);
    }
}
