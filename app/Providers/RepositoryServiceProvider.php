<?php

namespace App\Providers;

use App\Repositories\Contracts\CreditCardRepositoryInterface;
use App\Repositories\Contracts\DividendScheduleRepositoryInterface;
use App\Repositories\Contracts\HealthCheckRepositoryInterface;
use App\Repositories\Contracts\PortfolioRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\EloquentCreditCardRepository;
use App\Repositories\Eloquent\EloquentDividendScheduleRepository;
use App\Repositories\Eloquent\EloquentPortfolioRepository;
use App\Repositories\Eloquent\EloquentTransactionRepository;
use App\Repositories\Eloquent\EloquentUserRepository;
use App\Repositories\Eloquent\HealthCheckRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Repository interface-to-implementation bindings for Clean Architecture.
     *
     * @var array<class-string, class-string>
     */
    protected array $repositories = [
        HealthCheckRepositoryInterface::class => HealthCheckRepository::class,
        UserRepositoryInterface::class => EloquentUserRepository::class,
        TransactionRepositoryInterface::class => EloquentTransactionRepository::class,
        CreditCardRepositoryInterface::class => EloquentCreditCardRepository::class,
        PortfolioRepositoryInterface::class => EloquentPortfolioRepository::class,
        DividendScheduleRepositoryInterface::class => EloquentDividendScheduleRepository::class,
    ];

    public function register(): void
    {
        foreach ($this->repositories as $contract => $implementation) {
            $this->app->bind($contract, $implementation);
        }
    }
}
