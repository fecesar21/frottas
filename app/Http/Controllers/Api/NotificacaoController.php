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

    public function marcarLida(string $id)
    {
        $notificacao = auth()->user()->notifications()->findOrFail($id);
        $notificacao->markAsRead();

        return response()->json(['message' => 'Notificação marcada como lida']);
    }
}
