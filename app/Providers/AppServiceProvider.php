<?php

namespace App\Providers;

use App\Models\Checkin;
use App\Models\Motorista;
use App\Models\Usuario;
use App\Models\Veiculo;
use App\Policies\CheckinPolicy;
use App\Policies\MotoristaPolicy;
use App\Policies\UsuarioPolicy;
use App\Policies\VeiculoPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Usuario::class, UsuarioPolicy::class);
        Gate::policy(Veiculo::class, VeiculoPolicy::class);
        Gate::policy(Motorista::class, MotoristaPolicy::class);
        Gate::policy(Checkin::class, CheckinPolicy::class);
    }
}
