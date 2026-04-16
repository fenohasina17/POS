<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\CashRegisterSession;
use App\Policies\CashRegisterSessionPolicy;
use App\Models\CashTransaction;
use App\Policies\CashTransactionPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        CashRegisterSession::class => CashRegisterSessionPolicy::class,
        CashTransaction::class => CashTransactionPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot()
    {
        $this->registerPolicies();

        //
    }
}
