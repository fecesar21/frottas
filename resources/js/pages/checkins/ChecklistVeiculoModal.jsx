import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { CheckCircle, XCircle, Send, Camera } from 'lucide-react'
import * as checklistApi from '../../api/checklistVeiculo'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import Alert from '../../components/ui/Alert'

export default function ChecklistVeiculoModal({ onDone }) {
  const qc = useQueryClient()
  const [error, setError] = useState('')
  const [itemAberto, setItemAberto] = useState(null)
  const [observacao, setObservacao] = useState('')
  const [valor, setValor] = useState('')
  const [foto, setFoto] = useState(null)
  const [conformeAberto, setConformeAberto] = useState(null)

  const { data: checklist, isLoading } = useQuery({
    queryKey: ['checklist-veiculo', 'pendente'],
    queryFn: () => checklistApi.pendente().then(r => r.data),
  })

  const salvarItem = useMutation({
    mutationFn: (payload) => checklistApi.atualizarItem(checklist.id, payload),
    onSuccess: () => {
      setItemAberto(null)
      setConformeAberto(null)
      setObservacao('')
      setValor('')
      setFoto(null)
      qc.invalidateQueries({ queryKey: ['checklist-veiculo', 'pendente'] })
    },
    onError: (e) => {
      const errors = e.response?.data?.errors
      const primeiraMensagem = errors ? Object.values(errors)[0]?.[0] : undefined
      setError(primeiraMensagem ?? e.response?.data?.message ?? 'Erro ao salvar item')
    },
  })

  const enviar = useMutation({
    mutationFn: () => checklistApi.enviar(checklist.id, {}),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['checklist-veiculo', 'pendente'] })
      onDone()
    },
    onError: (e) => {
      const errors = e.response?.data?.errors
      const primeiraMensagem = errors ? Object.values(errors)[0]?.[0] : undefined
      setError(primeiraMensagem ?? e.response?.data?.message ?? 'Erro ao enviar checklist')
    },
  })

  if (isLoading) return <LoadingSpinner />
  if (!checklist) return null

  if (checklist.status === 'enviado') {
    return (
      <div className="space-y-4 text-center py-6">
        <CheckCircle className="mx-auto text-green-600" size={36} />
        <p className="text-sm text-gray-600">Checklist do veículo já foi enviado hoje.</p>
        <button onClick={onDone} className="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700">
          Fechar
        </button>
      </div>
    )
  }

  const respostas = checklist.respostas ?? []

  const categorias = {}
  for (const r of respostas) {
    const cat = r.item_modelo?.categoria?.nome ?? 'Geral'
    if (!categorias[cat]) categorias[cat] = []
    categorias[cat].push(r)
  }

  const requerValor = (resposta) => !!resposta.item_modelo?.requer_valor
  const valorMin = (resposta) => resposta.item_modelo?.valor_min ?? 0
  const valorMax = (resposta) => resposta.item_modelo?.valor_max ?? 300

  const desmarcar = (resposta) => {
    setError('')
    setItemAberto(null)
    setConformeAberto(null)
    setObservacao('')
    setValor('')
    setFoto(null)
    salvarItem.mutate({ item_modelo_id: resposta.item_modelo_id, conforme: null })
  }

  const marcarConforme = (resposta) => {
    if (resposta.conforme === true) {
      desmarcar(resposta)
      return
    }
    setError('')
    setItemAberto(null)
    if (requerValor(resposta)) {
      setConformeAberto(resposta.item_modelo_id)
      setValor(resposta.valor ?? '')
      return
    }
    salvarItem.mutate({ item_modelo_id: resposta.item_modelo_id, conforme: true })
  }

  const abrirNaoConforme = (resposta) => {
    if (resposta.conforme === false) {
      desmarcar(resposta)
      return
    }
    setError('')
    setConformeAberto(null)
    setItemAberto(resposta.item_modelo_id)
    setObservacao(resposta.observacao ?? '')
    setValor(resposta.valor ?? '')
    setFoto(null)
  }

  const validarValor = (resposta) => {
    if (!requerValor(resposta)) return true
    const min = valorMin(resposta)
    const max = valorMax(resposta)
    if (valor === '' || valor === null || valor === undefined) {
      setError(`Informe o valor (${min} a ${max}) para este item.`)
      return false
    }
    const numero = Number(valor)
    if (!Number.isInteger(numero) || numero < min || numero > max) {
      setError(`O valor deve ser um número inteiro entre ${min} e ${max}.`)
      return false
    }
    return true
  }

  const salvarConformeComValor = (resposta) => {
    setError('')
    if (!validarValor(resposta)) return
    salvarItem.mutate({ item_modelo_id: resposta.item_modelo_id, conforme: true, valor })
  }

  const salvarNaoConforme = (resposta) => {
    setError('')
    if (!observacao.trim()) {
      setError('Informe a observação para o item não conforme.')
      return
    }
    if (!validarValor(resposta)) return
    salvarItem.mutate({ item_modelo_id: resposta.item_modelo_id, conforme: false, observacao, valor: requerValor(resposta) ? valor : undefined, foto })
  }

  const todosRespondidos = respostas.every(r => r.conforme !== null)

  return (
    <div className="space-y-4">
      {error && <Alert type="error" message={error} />}

      <div className="flex items-center justify-between text-sm text-gray-600 bg-gray-50 rounded-lg p-3">
        <span>Veículo: <strong>{checklist.veiculo?.placa ?? '—'}</strong></span>
        <span>
          Conforme: <strong className="text-green-600">{checklist.itens_conforme}</strong>
          {' '}| Não conforme: <strong className="text-red-600">{checklist.itens_nao_conforme}</strong>
        </span>
      </div>

      {Object.entries(categorias).map(([cat, itens]) => (
        <div key={cat}>
          <h4 className="text-sm font-semibold text-gray-700 mb-2 uppercase tracking-wide">{cat}</h4>
          <div className="space-y-2">
            {itens.map((r) => (
              <div key={r.id} className="bg-white border rounded-lg px-4 py-3 space-y-2">
                <div className="flex items-center justify-between">
                  <span className="text-sm text-gray-700">{r.item_modelo?.label ?? '—'}</span>
                  <div className="flex items-center gap-2">
                    <button
                      onClick={() => marcarConforme(r)}
                      disabled={salvarItem.isPending}
                      className={`flex items-center gap-1 px-2.5 py-1 rounded-lg border text-xs font-medium transition-colors disabled:cursor-not-allowed ${
                        r.conforme === true ? 'text-green-600 bg-green-50 border-green-200' : 'text-gray-400 border-gray-200 hover:bg-gray-50'
                      }`}
                    >
                      <CheckCircle size={13} /> Conforme
                    </button>
                    <button
                      onClick={() => abrirNaoConforme(r)}
                      disabled={salvarItem.isPending}
                      className={`flex items-center gap-1 px-2.5 py-1 rounded-lg border text-xs font-medium transition-colors disabled:cursor-not-allowed ${
                        r.conforme === false ? 'text-red-600 bg-red-50 border-red-200' : 'text-gray-400 border-gray-200 hover:bg-gray-50'
                      }`}
                    >
                      <XCircle size={13} /> Não conforme
                    </button>
                  </div>
                </div>

                {conformeAberto === r.item_modelo_id && (
                  <div className="space-y-2 bg-green-50 border border-green-100 rounded-lg p-3">
                    <label className="block text-xs text-gray-600">
                      Nível de Oxigênio ({valorMin(r)} a {valorMax(r)})
                    </label>
                    <input
                      type="number"
                      inputMode="numeric"
                      min={valorMin(r)}
                      max={valorMax(r)}
                      step={1}
                      value={valor}
                      onChange={(e) => setValor(e.target.value)}
                      placeholder="Informe o valor"
                      className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400"
                    />
                    <div className="flex justify-end">
                      <button
                        onClick={() => salvarConformeComValor(r)}
                        disabled={salvarItem.isPending}
                        className="bg-green-600 text-white px-3 py-1.5 rounded-lg text-xs hover:bg-green-700 disabled:opacity-60"
                      >
                        {salvarItem.isPending ? 'Salvando...' : 'Salvar'}
                      </button>
                    </div>
                  </div>
                )}

                {itemAberto === r.item_modelo_id && (
                  <div className="space-y-2 bg-red-50 border border-red-100 rounded-lg p-3">
                    {requerValor(r) && (
                      <div className="space-y-1">
                        <label className="block text-xs text-gray-600">
                          Nível de Oxigênio ({valorMin(r)} a {valorMax(r)})
                        </label>
                        <input
                          type="number"
                          inputMode="numeric"
                          min={valorMin(r)}
                          max={valorMax(r)}
                          step={1}
                          value={valor}
                          onChange={(e) => setValor(e.target.value)}
                          placeholder="Informe o valor"
                          className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400"
                        />
                      </div>
                    )}
                    <textarea
                      value={observacao}
                      onChange={(e) => setObservacao(e.target.value)}
                      placeholder="Justifique o motivo da não conformidade"
                      rows={2}
                      className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400"
                    />
                    <div className="flex items-center justify-between gap-2">
                      <label className="flex items-center gap-1 text-xs text-gray-500 cursor-pointer">
                        <Camera size={14} />
                        {foto ? foto.name : 'Anexar foto (opcional)'}
                        <input type="file" accept="image/*" className="hidden" onChange={(e) => setFoto(e.target.files?.[0] ?? null)} />
                      </label>
                      <button
                        onClick={() => salvarNaoConforme(r)}
                        disabled={salvarItem.isPending}
                        className="bg-red-600 text-white px-3 py-1.5 rounded-lg text-xs hover:bg-red-700 disabled:opacity-60"
                      >
                        {salvarItem.isPending ? 'Salvando...' : 'Salvar'}
                      </button>
                    </div>
                  </div>
                )}

                {r.conforme !== null && itemAberto !== r.item_modelo_id && conformeAberto !== r.item_modelo_id && requerValor(r) && r.valor !== null && r.valor !== undefined && (
                  <p className="text-xs text-gray-500">Valor registrado: <strong>{r.valor}</strong></p>
                )}

                {r.conforme === false && itemAberto !== r.item_modelo_id && r.observacao && (
                  <p className="text-xs text-red-600">{r.observacao}</p>
                )}
              </div>
            ))}
          </div>
        </div>
      ))}

      <div className="flex justify-end pt-2">
        <button
          onClick={() => enviar.mutate()}
          disabled={!todosRespondidos || enviar.isPending}
          className="flex items-center gap-2 bg-green-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-green-700 disabled:opacity-60 disabled:cursor-not-allowed"
        >
          <Send size={16} />
          {enviar.isPending ? 'Enviando...' : 'Enviar checklist'}
        </button>
      </div>
      {!todosRespondidos && (
        <p className="text-xs text-gray-400 text-right">Responda todos os itens para enviar o checklist.</p>
      )}
    </div>
  )
}
