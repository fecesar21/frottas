import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import * as ldapApi from '../../api/unidadeLdapConfig'
import Alert from '../../components/ui/Alert'

export default function UnidadeLdapConfigForm({ unidadeId, config, onSuccess }) {
  const qc = useQueryClient()
  const [form, setForm] = useState({
    host: config?.host ?? '',
    port: config?.port ?? 636,
    base_dn: config?.base_dn ?? '',
    username: config?.username ?? '',
    password: '',
    use_ssl: config?.use_ssl ?? true,
    use_starttls: config?.use_starttls ?? false,
    unidade_attribute: config?.unidade_attribute ?? 'department',
    valoresAdTexto: (config?.valores_ad ?? []).join(', '),
    ativo: config?.ativo ?? true,
  })
  const [error, setError] = useState('')
  const [fieldErrors, setFieldErrors] = useState({})
  const [testeResultado, setTesteResultado] = useState(null)

  const set = (k) => (e) =>
    setForm(f => ({ ...f, [k]: e.target.type === 'checkbox' ? e.target.checked : e.target.value }))

  const montarPayload = () => ({
    host: form.host,
    port: Number(form.port),
    base_dn: form.base_dn,
    username: form.username,
    password: form.password || undefined,
    use_ssl: form.use_ssl,
    use_starttls: form.use_starttls,
    unidade_attribute: form.unidade_attribute,
    valores_ad: form.valoresAdTexto.split(',').map(v => v.trim()).filter(Boolean),
    ativo: form.ativo,
  })

  const testar = useMutation({
    mutationFn: () => ldapApi.testar(unidadeId, montarPayload()),
    onSuccess: (res) => setTesteResultado(res.data),
    onError: (e) => setTesteResultado({ sucesso: false, mensagem: e.response?.data?.message ?? 'Erro ao testar conexão' }),
  })

  const salvar = useMutation({
    mutationFn: () => ldapApi.salvar(unidadeId, montarPayload()),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['unidade-ldap-config', unidadeId] })
      onSuccess()
    },
    onError: (e) => {
      if (e.response?.data?.errors) setFieldErrors(e.response.data.errors)
      else setError(e.response?.data?.message ?? 'Erro ao salvar')
    },
  })

  const handleSubmit = (e) => {
    e.preventDefault()
    setError('')
    setFieldErrors({})
    salvar.mutate()
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      {error && <Alert type="error" message={error} />}
      {testeResultado && (
        <Alert type={testeResultado.sucesso ? 'success' : 'error'} message={testeResultado.mensagem} />
      )}

      <div className="grid grid-cols-2 gap-4">
        <div className="col-span-2">
          <label className="block text-sm font-medium text-gray-700 mb-1">Host do Domain Controller *</label>
          <input
            type="text" required value={form.host} onChange={set('host')}
            placeholder="Ex: 10.0.0.5 ou dc.unidade.local"
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          {fieldErrors.host && <p className="text-red-500 text-xs mt-1">{fieldErrors.host[0]}</p>}
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Porta *</label>
          <input
            type="number" required value={form.port} onChange={set('port')}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div className="flex items-end gap-4 pb-2">
          <label className="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" checked={form.use_ssl} onChange={set('use_ssl')} className="rounded" /> LDAPS (SSL)
          </label>
          <label className="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" checked={form.use_starttls} onChange={set('use_starttls')} className="rounded" /> StartTLS
          </label>
        </div>

        <div className="col-span-2">
          <label className="block text-sm font-medium text-gray-700 mb-1">Base DN *</label>
          <input
            type="text" required value={form.base_dn} onChange={set('base_dn')}
            placeholder='Ex: OU=Funcionarios,DC=empresa,DC=local'
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          {fieldErrors.base_dn && <p className="text-red-500 text-xs mt-1">{fieldErrors.base_dn[0]}</p>}
        </div>

        <div className="col-span-2">
          <label className="block text-sm font-medium text-gray-700 mb-1">Usuário da conta de serviço *</label>
          <input
            type="text" required value={form.username} onChange={set('username')}
            placeholder="usuario@dominio.local"
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          {fieldErrors.username && <p className="text-red-500 text-xs mt-1">{fieldErrors.username[0]}</p>}
        </div>

        <div className="col-span-2">
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Senha {config ? '(deixe em branco para manter a atual)' : '*'}
          </label>
          <input
            type="password" required={!config} value={form.password} onChange={set('password')}
            placeholder={config ? '••••••' : ''}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          {fieldErrors.password && <p className="text-red-500 text-xs mt-1">{fieldErrors.password[0]}</p>}
        </div>

        <div className="col-span-2">
          <label className="block text-sm font-medium text-gray-700 mb-1">Atributo AD de unidade</label>
          <input
            type="text" value={form.unidade_attribute} onChange={set('unidade_attribute')}
            placeholder="department"
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div className="col-span-2">
          <label className="block text-sm font-medium text-gray-700 mb-1">Valores do atributo que identificam esta unidade</label>
          <input
            type="text" value={form.valoresAdTexto} onChange={set('valoresAdTexto')}
            placeholder="Ex: HOSP-CENTRO, HOSP-CENTRO-ANEXO"
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          <p className="text-xs text-gray-400 mt-1">Opcional — reservado para uso futuro (desambiguação de unidade por atributo). Hoje o sistema não consulta este valor durante o login. Separe múltiplos valores por vírgula.</p>
          {fieldErrors.valores_ad && <p className="text-red-500 text-xs mt-1">{fieldErrors.valores_ad[0]}</p>}
        </div>

        <div className="col-span-2 flex items-center gap-2">
          <input type="checkbox" id="ativo" checked={form.ativo} onChange={set('ativo')} className="rounded" />
          <label htmlFor="ativo" className="text-sm text-gray-700">Configuração ativa (participa da tentativa de login)</label>
        </div>
      </div>

      <div className="flex justify-between items-center pt-2">
        <button
          type="button"
          onClick={() => testar.mutate()}
          disabled={testar.isPending}
          className="text-sm text-blue-600 hover:text-blue-800 font-medium disabled:opacity-60"
        >
          {testar.isPending ? 'Testando...' : 'Testar Conexão'}
        </button>
        <button
          type="submit"
          disabled={salvar.isPending}
          className="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 transition-colors"
        >
          {salvar.isPending ? 'Salvando...' : 'Salvar'}
        </button>
      </div>
    </form>
  )
}
