<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve a unidade de contexto efetiva para a requisição.
 *
 * - Admin: usa ?unidade_id= da query string (ou null = sem filtro)
 * - Gestor da matriz: sem filtro (enxerga todas as unidades, como o admin)
 * - Demais Gestor/Operador: sempre usa a unidade_id do próprio usuário
 *
 * O ID resolvido fica disponível via $request->unidade_efetiva.
 */
class EscopoUnidade
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $naoAdmin = $user->perfil !== 'admin';
            $gestorMatriz = $naoAdmin && $user->unidade?->tipo === 'matriz';
            $restrito = $naoAdmin && ! $gestorMatriz;

            $unidadeId = $restrito ? $user->unidade_id : $request->query('unidade_id');

            // Sentinela: um usuário não-admin sem unidade_id mapeada (ex.: um
            // solicitante recém-provisionado via AD sem mapeamento de unidade
            // configurado) NÃO deve cair no comportamento "sem filtro" usado
            // pelo admin. Os controllers aplicam o filtro via padrão
            // `->when($unidadeId, ...)`, onde um valor falsy = nenhum filtro.
            // Forçamos aqui um UUID inexistente para garantir zero resultados
            // até que a unidade seja mapeada corretamente, em vez de expor
            // dados de todas as unidades.
            if ($restrito && $unidadeId === null) {
                $unidadeId = '00000000-0000-0000-0000-000000000000';
            }

            $request->merge(['unidade_efetiva' => $unidadeId]);
        }

        return $next($request);
    }
}
