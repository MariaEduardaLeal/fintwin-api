<?php

namespace App\Integrations\Pluggy;

use Generator;

class PluggyTransactionPaginator
{
    private const MAX_PAGES = 50;

    /**
     * Yield Pluggy v2 transactions via opaque cursor pagination (`next`).
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function iterate(PluggyClient $pluggy, string $accountId, ?string $fromDate = null): Generator
    {
        $filters = [];

        if ($fromDate !== null && $fromDate !== '') {
            $filters['from'] = $fromDate;
        }

        $pages = 0;
        $seenNext = [];

        while ($pages < self::MAX_PAGES) {
            $response = $pluggy->getTransactions($accountId, $filters);
            $pages++;

            $results = $response['results'] ?? [];

            if (is_array($results)) {
                foreach ($results as $transaction) {
                    if (is_array($transaction)) {
                        yield $transaction;
                    }
                }
            }

            $next = $response['next'] ?? null;

            if (! is_string($next) || trim($next) === '' || isset($seenNext[$next])) {
                break;
            }

            $seenNext[$next] = true;

            $queryPart = parse_url($next, PHP_URL_QUERY);
            $queryString = is_string($queryPart) ? $queryPart : ltrim($next, '?');

            $nextFilters = [];
            parse_str($queryString, $nextFilters);
            unset($nextFilters['accountId']);

            if ($nextFilters === []) {
                break;
            }

            $filters = $nextFilters;
        }
    }
}
