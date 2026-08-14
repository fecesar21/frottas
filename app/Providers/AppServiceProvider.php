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
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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

        // Usado por POST /api/auth/login (routes/api.php). Precisa estar num
        // service provider, não no callback `then` de withRouting() em
        // bootstrap/app.php: aquele callback é pulado inteiro quando as
        // rotas estão em cache (`php artisan route:cache`), o que deixava
        // o rate limiter "login" indefinido em produção.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Usado por POST /api/auth/login-ad. Chaveado por IP + usuário
        // submetido (não só IP) porque atrás de NAT corporativo centenas de
        // funcionários compartilham o mesmo IP — um limiter só por IP
        // limitaria o volume de login de todo o escritório em vez de conter
        // tentativas de credential stuffing contra uma única conta.
        RateLimiter::for('login-ad', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip().'|'.$request->input('usuario'));
        });

        // Usado por POST /api/auth/esqueci-senha e /api/auth/redefinir-senha.
        // Chaveado por IP + e-mail submetido para não deixar um atacante
        // esgotar as tentativas de todos os e-mails de uma vez via um único IP,
        // nem permitir que ele martele um e-mail específico trocando de IP sem limite.
        RateLimiter::for('esqueci-senha', function (Request $request) {
            $email = Str::lower(trim((string) $request->input('email')));

            return Limit::perMinutes(15, 5)->by($request->ip().'|'.$email);
        });

        // Segundo teto, só por IP, para conter varredura em massa de e-mails
        // distintos vindos do mesmo IP (o limiter acima é por ip|email, então
        // sozinho não impede um atacante de testar milhares de e-mails
        // diferentes do mesmo IP). Mais permissivo para não penalizar tráfego
        // legítimo de múltiplos usuários atrás do mesmo IP/NAT.
        RateLimiter::for('esqueci-senha-ip', function (Request $request) {
            return Limit::perMinutes(15, 20)->by($request->ip());
        });
    }
}
