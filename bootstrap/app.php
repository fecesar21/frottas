<?php

use App\Http\Middleware\EscopoUnidade;
use App\Http\Middleware\RestringirDashboard;
use App\Http\Middleware\RestringirSolicitante;
use App\Http\Middleware\SomenteAdmin;
use Carbon\Carbon;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => SomenteAdmin::class,
            'throttle' => ThrottleRequests::class,
            'escopo.unidade' => EscopoUnidade::class,
            'solicitante.restrito' => RestringirSolicitante::class,
            'dashboard.restrito' => RestringirDashboard::class,
        ]);

        $middleware->appendToGroup('api', [
            ThrottleRequests::class.':api',
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('alertas:verificar')->daily();
        $schedule->command('colaboradores:sincronizar')->hourly()->withoutOverlapping();

        // Resumo em PDF por e-mail 1h após o fim de cada plantão e no dia 01 (mês anterior).
        $umaHoraApos = fn (string $hora) => Carbon::createFromFormat('H:i', $hora)->addHour()->format('H:i');
        $schedule->command('relatorio:resumo plantao')
            ->dailyAt($umaHoraApos(config('plantao.inicio_noturno', '19:00')))->withoutOverlapping();
        $schedule->command('relatorio:resumo plantao')
            ->dailyAt($umaHoraApos(config('plantao.inicio_diurno', '07:00')))->withoutOverlapping();
        $schedule->command('relatorio:resumo mensal')->monthlyOn(1, '07:00')->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Dados inválidos.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Registro não encontrado.'], 404);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Não autenticado.'], 401);
            }
        });
    })->create();
