<?php

namespace App\Integrations\Pluggy;

use Generator;

class PluggyTransactionPaginator
{
    private const MAX_PAGES = 50;

    /**
     * Yield Pluggy transaction pages safely while guarding against infinite pagination.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function iterate(PluggyClient $pluggy, string $accountId, ?string $fromDate = null): Generator
    {
        $page = 1;

        do {
            $filters = ['page' => $page, 'pageSize' => 200];

            if ($fromDate !== null && $fromDate !== '') {
                $filters['from'] = $fromDate;
            }

            $response = $pluggy->getTransactions($accountId, $filters);
            $results = $response['results'] ?? [];

            if (! is_array($results) || $results === []) {
                break;
            }

            foreach ($results as $transaction) {
                if (is_array($transaction)) {
                    yield $transaction;
                }
            }

            $totalPages = (int) ($response['totalPages'] ?? 1);
            $page++;
        } while ($page <= $totalPages && $page <= self::MAX_PAGES);
    }
}
