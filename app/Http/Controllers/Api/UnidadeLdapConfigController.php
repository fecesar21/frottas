<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UnidadeLdapConfig\UpsertUnidadeLdapConfigRequest;
use App\Models\Unidade;
use App\Models\UnidadeLdapConfiguracao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnidadeLdapConfigController extends Controller
{
    public function show(Unidade $unidade): JsonResponse
    {
        $config = UnidadeLdapConfiguracao::where('unidade_id', $unidade->id)->first();

        if (! $config) {
            return response()->json(['message' => 'Unidade sem configuração LDAP.'], 404);
        }

        return response()->json($this->apresentar($config));
    }

    public function upsert(UpsertUnidadeLdapConfigRequest $request, Unidade $unidade): JsonResponse
    {
        $data = $request->validated();

        $config = UnidadeLdapConfiguracao::where('unidade_id', $unidade->id)->first();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);

            if (! $config) {
                return response()->json(['errors' => ['password' => ['A senha é obrigatória ao criar uma nova configuração.']]], 422);
            }
        }

        $data['unidade_id'] = $unidade->id;
        $data['use_ssl'] = $data['use_ssl'] ?? true;
        $data['use_starttls'] = $data['use_starttls'] ?? false;
        $data['ativo'] = $data['ativo'] ?? true;

        $config = $config
            ? tap($config)->update($data)
            : UnidadeLdapConfiguracao::create($data);

        return response()->json($this->apresentar($config));
    }

    public function testar(Request $request, Unidade $unidade): JsonResponse
    {
        $data = $request->validate([
            'host' => 'required|string',
            'port' => 'required|integer',
            'base_dn' => 'required|string',
            'username' => 'required|string',
            'password' => 'nullable|string',
            'use_ssl' => 'boolean',
            'use_starttls' => 'boolean',
        ]);

        if (blank($data['password'] ?? null)) {
            $configExistente = UnidadeLdapConfiguracao::where('unidade_id', $unidade->id)->first();
            if (! $configExistente) {
                return response()->json(['sucesso' => false, 'mensagem' => 'Informe a senha da conta de serviço para testar.']);
            }
            $data['password'] = $configExistente->password;
        }

        $conexao = new \LdapRecord\Connection([
            'hosts' => [$data['host']],
            'port' => $data['port'],
            'base_dn' => $data['base_dn'],
            'username' => $data['username'],
            'password' => $data['password'],
            'use_tls' => $data['use_ssl'] ?? true,
            'use_starttls' => $data['use_starttls'] ?? false,
            'timeout' => 5,
            'options' => [
                LDAP_OPT_X_TLS_REQUIRE_CERT => LDAP_OPT_X_TLS_NEVER,
            ],
        ]);

        try {
            $conexao->connect();
        } catch (\LdapRecord\LdapRecordException $e) {
            return response()->json([
                'sucesso' => false,
                'mensagem' => 'Falha ao autenticar a conta de serviço: '.$e->getMessage(),
            ]);
        }

        try {
            $resultado = $conexao->query()->in($data['base_dn'])->rawFilter('(objectClass=user)')->limit(1)->get();
        } catch (\LdapRecord\LdapRecordException $e) {
            return response()->json([
                'sucesso' => false,
                'mensagem' => 'Falha ao consultar o Base DN informado: '.$e->getMessage(),
            ]);
        }

        if (empty($resultado)) {
            return response()->json([
                'sucesso' => true,
                'mensagem' => 'Conexão OK, mas nenhum usuário encontrado no Base DN informado — confira o Base DN.',
            ]);
        }

        return response()->json(['sucesso' => true, 'mensagem' => 'Conexão bem-sucedida.']);
    }

    public function destroy(Unidade $unidade): JsonResponse
    {
        UnidadeLdapConfiguracao::where('unidade_id', $unidade->id)->delete();

        return response()->json(['message' => 'Configuração LDAP removida.']);
    }

    private function apresentar(UnidadeLdapConfiguracao $config): array
    {
        return [
            'id' => $config->id,
            'unidade_id' => $config->unidade_id,
            'host' => $config->host,
            'port' => $config->port,
            'base_dn' => $config->base_dn,
            'username' => $config->username,
            'use_ssl' => $config->use_ssl,
            'use_starttls' => $config->use_starttls,
            'unidade_attribute' => $config->unidade_attribute,
            'valores_ad' => $config->valores_ad,
            'ativo' => $config->ativo,
            'senha_configurada' => true,
        ];
    }
}
