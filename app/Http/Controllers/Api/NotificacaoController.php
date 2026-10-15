<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Solicitacao;
use Illuminate\Http\Request;

class NotificacaoController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->notifications()->latest()->paginate(15);
    }

    public function naoLidas(Request $request)
    {
        $notificacoes = $request->user()->unreadNotifications()->latest()->limit(20)->get();

        // Notificações que apontam para uma solicitação apagada viram popups
        // quebrados (404 ao aceitar/assumir), e convites ao motorista ficam na
        // tela até serem respondidos: marca como lidas e descarta as que não
        // aguardam mais resposta deste usuário.
        $ids = $notificacoes->pluck('data.solicitacao_id')->filter()->unique();
        $solicitacoes = Solicitacao::whereIn('id', $ids)->get(['id', 'status', 'motorista_pendente_id'])->keyBy('id');
        $motoristaId = $request->user()->motorista_id;
        [$obsoletas, $notificacoes] = $notificacoes->partition(function ($n) use ($solicitacoes, $motoristaId) {
            if (! isset($n->data['solicitacao_id'])) {
                return false;
            }
            $solicitacao = $solicitacoes->get($n->data['solicitacao_id']);
            if (! $solicitacao) {
                return true;
            }

            return match (class_basename($n->type)) {
                'NovaViagemDesignada' => $solicitacao->motorista_pendente_id !== $motoristaId
                    || $solicitacao->status !== (($n->data['fila'] ?? false) ? 'aguardando_finalizacao_trajeto' : 'pendente_motorista'),
                'NovaSolicitacaoDisponivel' => $solicitacao->status !== 'aberto',
                default => false,
            };
        });
        $obsoletas->each->markAsRead();
        $notificacoes = $notificacoes->values();

        return response()->json([
            'total' => $notificacoes->count(),
            'notificacoes' => $notificacoes,
        ]);
    }

    public function marcarLida(string $id)
    {
        $notificacao = auth()->user()->notifications()->findOrFail($id);
        $notificacao->markAsRead();

        return response()->json(['message' => 'Notificação marcada como lida']);
    }

    public function marcarTodasLidas(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->noContent();
    }
}
