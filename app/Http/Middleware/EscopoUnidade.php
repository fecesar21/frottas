<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve a unidade de contexto efetiva para a requisição.
 *
 * - Admin: usa ?unidade_id= da query string (ou null = sem filtro)
 * - Gestor/Operador: sempre usa a unidade_id do próprio usuário
 *
 * O ID resolvido fica disponível via $request->unidade_efetiva.
 */
class EscopoUnidade
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $request->merge([
                'unidade_efetiva' => $user->perfil === 'admin'
                    ? $request->query('unidade_id')
                    : $user->unidade_id,
            ]);
        }

        return $next($request);
    }
}
