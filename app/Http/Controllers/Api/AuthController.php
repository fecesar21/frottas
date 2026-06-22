<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Motorista;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $r)
    {
        $input = $r->validated();

        // Aceita login por nome de usuário OU por e-mail
        $usuario = Usuario::where('ativo', true)
            ->where(function ($q) use ($input) {
                $q->where('nome', $input['usuario'])
                  ->orWhere('email', strtolower($input['usuario']));
            })->first();

        if (!$usuario || !Hash::check($input['senha'], $usuario->senha_hash)) {
            return response()->json(['error' => 'Usuário ou senha inválidos'], 401);
        }

        $usuario->update(['ultimo_acesso' => now()]);
        $token = $usuario->createToken('app', [], now()->addHours(8))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->buildUserPayload($usuario),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->buildUserPayload($request->user())]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout realizado com sucesso']);
    }

    private function buildUserPayload(Usuario $usuario): array
    {
        $checkinAtivo = null;
        if ($usuario->perfil === 'operador' && $usuario->motorista_id) {
            $motorista = Motorista::with('checkinAtivo.veiculo')->find($usuario->motorista_id);
            $checkinAtivo = $motorista?->checkinAtivo;
        }

        return [
            'id'            => $usuario->id,
            'nome'          => $usuario->nome,
            'email'         => $usuario->email,
            'perfil'        => $usuario->perfil,
            'motorista_id'  => $usuario->motorista_id,
            'checkin_ativo' => $checkinAtivo,
        ];
    }
}
