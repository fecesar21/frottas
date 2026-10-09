<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usuários com perfil "dashboard" só acessam o Dashboard Gerencial
 * (exibido em modo kiosk). Qualquer outra rota autenticada retorna 403.
 */
class RestringirDashboard
{
    private const ROTAS_PERMITIDAS = [
        'dashboard-gerencial.transferencias',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->perfil === 'dashboard' && ! in_array($request->route()?->getName(), self::ROTAS_PERMITIDAS, true)) {
            return response()->json(['error' => 'Acesso não permitido para este perfil'], 403);
        }

        return $next($request);
    }
}
