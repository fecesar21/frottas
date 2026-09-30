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
        // quebrados (404 ao aceitar/assumir): marca como lidas e descarta.
        $ids = $notificacoes->pluck('data.solicitacao_id')->filter()->unique();
        $existentes = Solicitacao::whereIn('id', $ids)->pluck('id')->all();
        [$orfas, $notificacoes] = $notificacoes->partition(
            fn ($n) => isset($n->data['solicitacao_id']) && ! in_array($n->data['solicitacao_id'], $existentes, true)
        );
        $orfas->each->markAsRead();
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
