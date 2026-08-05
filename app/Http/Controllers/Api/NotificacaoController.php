<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
