<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Registra/remove a inscrição Web Push do aparelho do usuário logado, usada
 * para avisar o motorista de novas solicitações mesmo com o app fechado.
 */
class PushInscricaoController extends Controller
{
    public function chavePublica()
    {
        return response()->json(['chave' => config('webpush.vapid.public_key')]);
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'endpoint' => ['required', 'url', 'max:500'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'content_encoding' => ['nullable', 'string'],
        ]);

        // updatePushSubscription transfere o endpoint caso ele pertença a outro
        // usuário (aparelho compartilhado entre motoristas de turnos diferentes).
        $request->user()->updatePushSubscription(
            $dados['endpoint'],
            $dados['keys']['p256dh'],
            $dados['keys']['auth'],
            $dados['content_encoding'] ?? 'aes128gcm',
        );

        return response()->json(['message' => 'Notificações ativadas.'], 201);
    }

    public function destroy(Request $request)
    {
        $dados = $request->validate(['endpoint' => ['required', 'string']]);

        $request->user()->deletePushSubscription($dados['endpoint']);

        return response()->noContent();
    }
}
