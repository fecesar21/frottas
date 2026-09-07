import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { ShieldCheck, ChevronRight } from 'lucide-react'
import * as unidadesApi from '../../api/unidades'
import * as ldapApi from '../../api/unidadeLdapConfig'
import Modal from '../../components/ui/Modal'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import UnidadeLdapConfigForm from './UnidadeLdapConfigForm'

function useLdapConfig(unidadeId, habilitado) {
  return useQuery({
    queryKey: ['unidade-ldap-config', unidadeId],
    queryFn: () => ldapApi.buscar(unidadeId).then(r => r.data).catch(e => {
      if (e.response?.status === 404) return null
      throw e
    }),
    enabled: habilitado,
  })
}

function LinhaUnidade({ unidade, onEditar }) {
  const { data: config, isLoading } = useLdapConfig(unidade.id, true)

  return (
    <div
      onClick={() => !isLoading && onEditar(unidade, config)}
      className="flex items-center justify-between px-5 py-4 cursor-pointer hover:bg-gray-50 transition-colors"
    >
      <div className="flex items-center gap-3">
        <div className="w-9 h-9 rounded-lg bg-brand-50 flex items-center justify-center">
          <ShieldCheck size={18} className="text-brand-600" />
        </div>
        <div>
          <p className="text-sm font-medium text-gray-800">{unidade.nome}</p>
          {isLoading ? (
            <p className="text-xs text-gray-400 mt-0.5">Carregando...</p>
          ) : (
            <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${
              !config ? 'bg-gray-100 text-gray-500'
                : config.ativo ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'
            }`}>
              {!config ? 'Não configurada' : config.ativo ? 'Configurada e ativa' : 'Configurada (inativa)'}
            </span>
          )}
        </div>
      </div>
      <ChevronRight size={16} className="text-gray-300 shrink-0" />
    </div>
  )
}

export default function ConfiguracoesLdap() {
  const [unidadeSelecionada, setUnidadeSelecionada] = useState(null)
  const [configSelecionada, setConfigSelecionada] = useState(null)

  const { data, isLoading } = useQuery({
    queryKey: ['unidades'],
    queryFn: () => unidadesApi.listar().then(r => r.data),
  })

  const abrirEdicao = (unidade, config) => {
    setUnidadeSelecionada(unidade)
    setConfigSelecionada(config)
  }

  const fechar = () => {
    setUnidadeSelecionada(null)
    setConfigSelecionada(null)
  }

  if (isLoading) return <LoadingSpinner />

  const lista = data ?? []

  return (
    <div className="space-y-4">
      <p className="text-sm text-gray-500">
        Configure a conexão LDAP/AD de cada unidade para permitir login de solicitantes com usuário de rede.
        No login, o sistema tenta autenticar contra cada unidade ativa até uma responder.
      </p>

      <div className="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100 overflow-hidden">
        {lista.map(unidade => (
          <LinhaUnidade key={unidade.id} unidade={unidade} onEditar={abrirEdicao} />
        ))}
      </div>

      <Modal
        open={!!unidadeSelecionada}
        onClose={fechar}
        title={`Configuração LDAP — ${unidadeSelecionada?.nome ?? ''}`}
        size="lg"
      >
        {unidadeSelecionada && (
          <UnidadeLdapConfigForm
            unidadeId={unidadeSelecionada.id}
            config={configSelecionada}
            onSuccess={fechar}
          />
        )}
      </Modal>
    </div>
  )
}
