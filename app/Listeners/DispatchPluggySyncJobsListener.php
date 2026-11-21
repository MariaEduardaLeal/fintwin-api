<?php

namespace App\Listeners;

use App\Events\BankSyncRequestedEvent;
use App\Jobs\SyncPluggyBankTransactionsJob;
use App\Jobs\SyncPluggyCreditInvoicesJob;

class DispatchPluggySyncJobsListener
{
    public function handle(BankSyncRequestedEvent $event): void
    {
        SyncPluggyBankTransactionsJob::dispatch(
            userId: $event->userId,
            pluggyItemId: $event->pluggyItemId,
            fromDate: $event->fromDate,
        );

        SyncPluggyCreditInvoicesJob::dispatch(
            userId: $event->userId,
            pluggyItemId: $event->pluggyItemId,
            fromDate: $event->fromDate,
        );
    }
}
