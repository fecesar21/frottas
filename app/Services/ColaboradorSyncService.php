<?php

namespace App\Services;

use App\Models\Colaborador;
use App\Models\UnidadeLdapConfiguracao;
use Illuminate\Support\Facades\Log;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;

/**
 * Copia os usuários do AD de cada unidade (OU configurada em
 * UnidadeLdapConfiguracao::ou_colaboradores, ou o base_dn) para a tabela
 * local `colaboradores`. Contas desabilitadas ou que saíram da OU ficam
 * inativas — nunca são apagadas, pois podem estar vinculadas a viagens.
 */
class ColaboradorSyncService
{
    /** Bit ACCOUNTDISABLE do userAccountControl. */
    private const CONTA_DESABILITADA = 2;

    /**
     * @return array<string, array{ok: bool, sincronizados?: int, inativados?: int, erro?: string}>
     *                                                                                              indexado por unidade_id
     */
    public function sincronizarTodas(): array
    {
        $resultado = [];

        UnidadeLdapConfiguracao::where('ativo', true)->get()
            ->each(function (UnidadeLdapConfiguracao $config) use (&$resultado) {
                $resultado[$config->unidade_id] = $this->sincronizar($config);
            });

        return $resultado;
    }

    /**
     * @return array{ok: bool, sincronizados?: int, inativados?: int, erro?: string}
     */
    public function sincronizar(UnidadeLdapConfiguracao $config): array
    {
        $nomeConexao = 'unidade-ldap-'.$config->id;

        try {
            $manager = Container::getInstance()->getConnectionManager();
            if (! $manager->hasConnection($nomeConexao)) {
                $manager->addConnection(new Connection($config->paraConexaoLdap()), $nomeConexao);
            }

            $usuarios = LdapUser::on($nomeConexao)
                ->in($config->ou_colaboradores ?: $config->base_dn)
                ->select(['objectguid', 'displayname', 'cn', 'samaccountname', 'mail', 'department', 'title', 'useraccountcontrol'])
                ->paginate(500);
        } catch (LdapRecordException $e) {
            // Falha de conexão: não inativa ninguém, mantém a última cópia.
            Log::warning('Falha ao sincronizar colaboradores do AD', [
                'unidade_id' => $config->unidade_id,
                'erro' => $e->getMessage(),
            ]);

            return ['ok' => false, 'erro' => $e->getMessage()];
        }

        $agora = now();
        $vistos = [];

        foreach ($usuarios as $ldapUser) {
            $guid = $ldapUser->getConvertedGuid();
            $nome = trim((string) ($ldapUser->getFirstAttribute('displayname') ?: $ldapUser->getFirstAttribute('cn')));

            if (! $guid || $nome === '') {
                continue;
            }

            $uac = (int) $ldapUser->getFirstAttribute('useraccountcontrol');

            Colaborador::updateOrCreate(['ldap_guid' => $guid], [
                'unidade_id' => $config->unidade_id,
                'nome' => mb_strtoupper($nome),
                'samaccountname' => $ldapUser->getFirstAttribute('samaccountname'),
                'email' => $ldapUser->getFirstAttribute('mail'),
                'departamento' => $ldapUser->getFirstAttribute('department'),
                'cargo' => $ldapUser->getFirstAttribute('title'),
                'ativo' => ($uac & self::CONTA_DESABILITADA) === 0,
                'sincronizado_at' => $agora,
            ]);

            $vistos[] = $guid;
        }

        $inativados = Colaborador::where('unidade_id', $config->unidade_id)
            ->where('ativo', true)
            ->whereNotIn('ldap_guid', $vistos)
            ->update(['ativo' => false]);

        return ['ok' => true, 'sincronizados' => count($vistos), 'inativados' => $inativados];
    }
}
