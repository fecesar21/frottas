<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $r)
    {
        $input = $r->validated();

    // Aceita login por nome de usuário OU por e-mail
	    $usuario = \App\Models\Usuario::where('ativo', true)
	        ->where(function($q) use ($input) {
	            $q->where('nome',  $input['usuario'])
	              ->orWhere('email', strtolower($input['usuario']));
	        })->first();

	    if (!$usuario || !\Illuminate\Support\Facades\Hash::check($input['senha'], $usuario->senha_hash)) {
	        return response()->json(['error' => 'Usuário ou senha inválidos'], 401);
	    }

	    $usuario->update(['ultimo_acesso' => now()]);
	    $token = $usuario->createToken('app', [], now()->addHours(8))->plainTextToken;

	    return response()->json([
	        'token' => $token,
	        'user'  => [
	            'id'           => $usuario->id,
	            'nome'         => $usuario->nome,
	            'email'        => $usuario->email,
	            'perfil'       => $usuario->perfil,
	            'motorista_id' => $usuario->motorista_id,
	        ],
	    ]);
}
/*    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'senha' => 'required|string',
        ]);

        $usuario = Usuario::where('email', strtolower($request->email))
            ->where('ativo', true)
            ->first();

        if (! $usuario || ! Hash::check($request->senha, $usuario->senha_hash)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        $usuario->update(['ultimo_acesso' => now()]);

        $token = $usuario->createToken('fleetcore-api', ['*'], now()->addHours(8))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'     => $usuario->id,
                'nome'   => $usuario->nome,
                'email'  => $usuario->email,
                'perfil' => $usuario->perfil,
		'motorista_id'=> $usuario->motorista_id,
            ],
        ]);
    }
*/
    public function me(Request $request)
    {
        return response()->json(['user' => $request->user()]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout realizado com sucesso']);
    }
}
