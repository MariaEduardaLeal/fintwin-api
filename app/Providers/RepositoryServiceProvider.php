<?php

namespace App\Providers;

use App\Repositories\Contracts\HealthCheckRepositoryInterface;
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
    ];

    public function register(): void
    {
        foreach ($this->repositories as $contract => $implementation) {
            $this->app->bind($contract, $implementation);
        }
    }
}
