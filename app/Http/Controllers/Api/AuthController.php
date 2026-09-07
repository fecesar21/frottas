<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\EsqueciSenhaRequest;
use App\Http\Requests\Auth\LoginAdRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RedefinirSenhaRequest;
use App\Models\Motorista;
use App\Models\UnidadeLdapConfiguracao;
use App\Models\Usuario;
use App\Notifications\RedefinicaoSenhaNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LdapRecord\Connection;
use LdapRecord\Container;
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

        $resolvido = $this->resolverLoginLdap($input['usuario'], $input['senha']);

        if ($resolvido === null) {
            return response()->json(['error' => 'Usuário ou senha inválidos'], 401);
        }

        if ($resolvido === []) {
            return response()->json(['error' => 'Serviço de autenticação indisponível, tente novamente'], 503);
        }

        ['ldapUser' => $ldapUser, 'unidadeConfig' => $unidadeConfig] = $resolvido;

        $guid = $ldapUser->getConvertedGuid();

        if (blank($guid)) {
            Log::error('AD retornou usuário sem objectGUID utilizável', ['dn' => $ldapUser->getDn()]);

            return response()->json(['error' => 'Serviço de autenticação indisponível, tente novamente'], 503);
        }

        $mail = $ldapUser->getFirstAttribute('mail');
        $unidadeId = $unidadeConfig->unidade_id;

        $usuario = Usuario::where('ldap_guid', $guid)->first();

        // Se ainda não há registro vinculado por ldap_guid, tenta achar uma
        // conta pré-existente (ex.: admin/gestor cadastrado manualmente)
        // pelo e-mail do AD, para vincular em vez de colidir na constraint
        // de unicidade de e-mail ao tentar criar um registro novo.
        $vinculandoContaExistente = false;
        if (! $usuario && $mail) {
            $usuario = Usuario::whereNull('ldap_guid')->where('email', $mail)->first();
            $vinculandoContaExistente = (bool) $usuario;
        }

        $novoRegistro = ! $usuario;
        $usuario = $usuario ?: new Usuario;

        if ($novoRegistro) {
            $usuario->fill([
                'nome' => $ldapUser->getFirstAttribute('displayname'),
                'email' => $mail,
                'perfil' => 'solicitante',
                'unidade_id' => $unidadeId,
                'ativo' => true,
            ]);
        } elseif (! $vinculandoContaExistente) {
            // Re-login normal de um solicitante já vinculado: mantém os
            // dados sincronizados com o AD.
            $usuario->fill([
                'nome' => $ldapUser->getFirstAttribute('displayname'),
                'email' => $mail,
                'unidade_id' => $unidadeId,
            ]);
        }
        // Se $vinculandoContaExistente: preserva perfil/unidade/nome/ativo
        // da conta existente — só passa a aceitar login via AD também.

        $usuario->ldap_guid = $guid;
        $usuario->ldap_sync_at = now();
        $usuario->save();

        if ($usuario->exists && ! $usuario->ativo) {
            return response()->json(['error' => 'Usuário ou senha inválidos'], 401);
        }

        $usuario->update(['ultimo_acesso' => now()]);
        $token = $usuario->createToken('app', [], now()->addHours(8))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->buildUserPayload($usuario),
        ]);
    }

    /**
     * Tenta autenticar usuario/senha contra cada UnidadeLdapConfiguracao
     * ativa, em ordem, até um bind funcionar.
     *
     * @return array{ldapUser: LdapUser, unidadeConfig: UnidadeLdapConfiguracao}|null|array{}
     *   null       => nenhuma unidade autenticou (usuário/senha inválidos)
     *   []         => nenhuma unidade respondeu (falha de conectividade em todas)
     *   array{...} => sucesso
     */
    private function resolverLoginLdap(string $usuario, string $senha): ?array
    {
        $configs = UnidadeLdapConfiguracao::where('ativo', true)->get();

        if ($configs->isEmpty()) {
            // Sem unidades configuradas não é uma falha de conectividade:
            // trata como credenciais inválidas, igual a "usuário não
            // encontrado em nenhuma unidade".
            return null;
        }

        $algumaRespondeu = false;

        foreach ($configs as $config) {
            $nomeConexao = 'unidade-ldap-'.$config->id;

            try {
                $manager = Container::getInstance()->getConnectionManager();

                // Se a conexão já estiver registrada (ex.: uma fake do
                // DirectoryEmulator em testes), não a substitui por uma
                // conexão real — apenas reaproveita a existente.
                if (! $manager->hasConnection($nomeConexao)) {
                    $manager->addConnection(
                        new Connection($config->paraConexaoLdap()),
                        $nomeConexao
                    );
                }

                $ldapUser = LdapUser::on($nomeConexao)->findBy('samaccountname', $usuario);

                if (! $ldapUser) {
                    $algumaRespondeu = true;
                    continue;
                }

                $binded = $ldapUser->getConnection()->auth()->attempt($ldapUser->getDn(), $senha);
                $algumaRespondeu = true;

                if ($binded) {
                    return ['ldapUser' => $ldapUser, 'unidadeConfig' => $config];
                }
            } catch (LdapRecordException $e) {
                Log::warning('Falha ao consultar AD de uma unidade durante login', [
                    'unidade_id' => $config->unidade_id,
                    'erro' => $e->getMessage(),
                ]);
                continue;
            }
        }

        return $algumaRespondeu ? null : [];
    }

    public function esqueciSenha(EsqueciSenhaRequest $r)
    {
        $email = Str::lower(trim($r->validated()['email']));

        $usuario = Usuario::where('ativo', true)->where('email', $email)->first();

        if ($usuario) {
            $token = Str::random(64);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                ['token' => Hash::make($token), 'created_at' => now()]
            );

            $usuario->notify(new RedefinicaoSenhaNotification($token, $email));
        } else {
            // Paga o mesmo custo de hashing do ramo "usuário encontrado" para
            // não vazar, por diferença de tempo de resposta, se o e-mail existe.
            Hash::make(Str::random(64));
        }

        return response()->json([
            'message' => 'Se o e-mail informado estiver cadastrado, você receberá instruções para redefinir sua senha.',
        ]);
    }

    public function redefinirSenha(RedefinirSenhaRequest $r)
    {
        $dados = $r->validated();
        $email = Str::lower(trim($dados['email']));

        $registro = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (! $registro
            || Carbon::parse($registro->created_at)->addMinutes(60)->isPast()
            || ! Hash::check($dados['token'], $registro->token)
        ) {
            return response()->json(['message' => 'Token inválido ou expirado'], 422);
        }

        $usuario = Usuario::where('ativo', true)->where('email', $email)->first();

        if (! $usuario) {
            return response()->json(['message' => 'Token inválido ou expirado'], 422);
        }

        $usuario->update(['senha_hash' => Hash::make($dados['senha'])]);
        $usuario->tokens()->delete();

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return response()->json(['message' => 'Senha redefinida com sucesso.']);
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
