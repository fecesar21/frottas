<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Usuario\StoreUsuarioRequest;
use App\Http\Requests\Usuario\UpdateUsuarioRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\Motorista;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UsuarioController extends Controller
{
    public function index()
    {
        return UsuarioResource::collection(Usuario::orderBy('nome')->get());
    }

    public function store(StoreUsuarioRequest $request)
    {
        $d = $request->validated();

        if (empty($d['motorista_id']) && $d['perfil'] !== 'admin') {
            $primeiro = explode(' ', $d['nome'])[0];
            $mot = Motorista::where('nome', 'like', $primeiro . '%')->where('status', 'ativo')->first();
            if ($mot) {
                $d['motorista_id'] = $mot->id;
            }
        }

        $usuario = Usuario::create([
            'nome'         => $d['nome'],
            'email'        => $d['email'] ?? null,
            'senha_hash'   => Hash::make($d['senha']),
            'perfil'       => $d['perfil'],
            'motorista_id' => $d['motorista_id'] ?? null,
            'ativo'        => true,
        ]);

        return (new UsuarioResource($usuario))->response()->setStatusCode(201);
    }

    public function update(UpdateUsuarioRequest $request, Usuario $usuario)
    {
        $d = $request->validated();

        if (!empty($d['senha'])) {
            $d['senha_hash'] = Hash::make($d['senha']);
            unset($d['senha']);
        }

        $usuario->update($d);

        return new UsuarioResource($usuario->fresh());
    }

    public function destroy(Usuario $usuario)
    {
        if ($usuario->id === auth()->id()) {
            return response()->json(['error' => 'Não é possível desativar o próprio usuário'], 422);
        }

        $usuario->update(['ativo' => false]);

        return response()->json(['message' => 'Usuário desativado']);
    }
}
