<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginAdRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Motorista;
use App\Models\UnidadeAdMapeamento;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;

class AuthController extends Controller
{
    public function login(LoginRequest $r)
    {
        $input = $r->validated();

        $cpf = preg_replace('/\D/', '', $input['usuario']);
        $usuario = Usuario::where('ativo', true)
            ->where('cpf', $cpf)
            ->first();

        if (! $usuario || ! Hash::check($input['senha'], $usuario->senha_hash)) {
            return response()->json(['error' => 'Usuário ou senha inválidos'], 401);
        }

        $usuario->update(['ultimo_acesso' => now()]);
        $token = $usuario->createToken('app', [], now()->addHours(8))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->buildUserPayload($usuario),
        ]);
    }

    public function loginAd(LoginAdRequest $r)
    {
        $input = $r->validated();

        try {
            $ldapUser = LdapUser::findBy('samaccountname', $input['usuario']);
        } catch (LdapRecordException $e) {
            Log::error('Falha ao consultar o AD', ['erro' => $e->getMessage()]);

            return response()->json(['error' => 'Serviço de autenticação indisponível, tente novamente'], 503);
        }

        if (! $ldapUser) {
            return response()->json(['error' => 'Usuário ou senha inválidos'], 401);
        }

        try {
            $binded = $ldapUser->getConnection()->auth()->attempt($ldapUser->getDn(), $input['senha']);
        } catch (LdapRecordException $e) {
            Log::error('Falha ao conectar ao AD para bind', ['erro' => $e->getMessage()]);

            return response()->json(['error' => 'Serviço de autenticação indisponível, tente novamente'], 503);
        }

        if (! $binded) {
            return response()->json(['error' => 'Usuário ou senha inválidos'], 401);
        }

        $guid = $ldapUser->getConvertedGuid();
        $valorAd = $ldapUser->getFirstAttribute(config('ldap.unidade_attribute'));
        $unidadeId = UnidadeAdMapeamento::where('valor_ad', $valorAd)->first()?->unidade_id;

        $usuario = Usuario::firstOrNew(['ldap_guid' => $guid]);
        $usuario->fill([
            'nome' => $ldapUser->getFirstAttribute('displayname'),
            'email' => $ldapUser->getFirstAttribute('mail'),
            'perfil' => 'solicitante',
            'ativo' => true,
            'unidade_id' => $unidadeId,
            'ldap_sync_at' => now(),
        ]);
        $usuario->save();

        $usuario->update(['ultimo_acesso' => now()]);
        $token = $usuario->createToken('app', [], now()->addHours(8))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->buildUserPayload($usuario),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->buildUserPayload($request->user())]);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        Auth::forgetGuards();

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
            'id' => $usuario->id,
            'nome' => $usuario->nome,
            'cpf' => $usuario->cpf,
            'email' => $usuario->email,
            'perfil' => $usuario->perfil,
            'motorista_id' => $usuario->motorista_id,
            'unidade_id' => $usuario->unidade_id,
            'checkin_ativo' => $checkinAtivo,
        ];
    }
}
