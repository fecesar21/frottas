<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe usuários com perfil "solicitante" ao subconjunto de rotas
 * relevante para eles: solicitações próprias, leitura de unidades e
 * notificações. Qualquer outra rota autenticada retorna 403.
 */
class RestringirSolicitante
{
    private const ROTAS_PERMITIDAS = [
        'solicitacoes.index',
        'solicitacoes.show',
        'solicitacoes.store',
        'solicitacoes.cancelar',
        'solicitacoes.motorista-aceitar',
        'solicitacoes.motorista-recusar',
        'unidades.index',
        'unidades.show',
        'notificacoes.index',
        'notificacoes.nao-lidas',
        'notificacoes.marcar-lidas',
        'notificacoes.marcar-lida',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->perfil === 'solicitante' && ! in_array($request->route()?->getName(), self::ROTAS_PERMITIDAS, true)) {
            return response()->json(['error' => 'Acesso não permitido para este perfil'], 403);
        }

        return $next($request);
    }
}
