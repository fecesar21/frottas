<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UnidadeLdapConfig\UpsertUnidadeLdapConfigRequest;
use App\Models\Unidade;
use App\Models\UnidadeLdapConfiguracao;
use Illuminate\Http\JsonResponse;

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
