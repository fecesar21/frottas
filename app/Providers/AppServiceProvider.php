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
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

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

        // 2. DEFINIÇÃO DO RATE LIMITER 'api'
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
