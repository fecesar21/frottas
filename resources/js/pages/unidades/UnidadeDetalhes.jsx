import { useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, UserCheck, Truck, Link2, Unlink, Plus } from 'lucide-react'
import * as unidadesApi from '../../api/unidades'
import * as motoristasApi from '../../api/motoristas'
import * as veiculosApi from '../../api/veiculos'
import Modal from '../../components/ui/Modal'
import Alert from '../../components/ui/Alert'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import Badge from '../../components/ui/Badge'

function VincularModal({ unidadeId, tipo, jaVinculados, onClose }) {
  const qc = useQueryClient()
  const [selecionados, setSelecionados] = useState([])
  const [error, setError] = useState('')

  const { data: todos = [], isLoading } = useQuery({
    queryKey: [tipo === 'motoristas' ? 'motoristas' : 'veiculos', 'todos'],
    queryFn: () =>
      tipo === 'motoristas'
        ? motoristasApi.listar({ status: 'ativo' }).then(r => r.data.data ?? r.data)
        : veiculosApi.listar().then(r => r.data.data ?? r.data),
  })

  const jaVinculadosIds = new Set(jaVinculados.map(i => i.id))
  const disponiveis = todos.filter(i => !jaVinculadosIds.has(i.id))

  const toggle = (id) =>
    setSelecionados(prev =>
      prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]
    )

  const vincular = useMutation({
    mutationFn: () =>
      tipo === 'motoristas'
        ? unidadesApi.vincularMotoristas(unidadeId, selecionados)
        : unidadesApi.vincularVeiculos(unidadeId, selecionados),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['unidade', unidadeId] })
      onClose()
    },
    onError: (e) => setError(e.response?.data?.message ?? 'Erro ao vincular'),
  })

  return (
    <div className="space-y-4">
      {error && <Alert type="error" message={error} />}
      {isLoading ? (
        <LoadingSpinner />
      ) : disponiveis.length === 0 ? (
        <p className="text-center text-gray-400 py-4">
          {tipo === 'motoristas'
            ? 'Nenhum motorista disponível para vincular.'
            : 'Nenhum veículo disponível para vincular.'}
        </p>
      ) : (
        <div className="max-h-72 overflow-y-auto divide-y divide-gray-100 border border-gray-200 rounded-lg">
          {disponiveis.map(item => (
            <label
              key={item.id}
              className="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 cursor-pointer"
            >
              <input
                type="checkbox"
                checked={selecionados.includes(item.id)}
                onChange={() => toggle(item.id)}
                className="rounded accent-blue-600"
              />
              {tipo === 'motoristas' ? (
                <span className="text-sm text-gray-700">
                  <span className="font-medium">{item.nome}</span>
                  <span className="text-gray-400 ml-2">— {item.cpf}</span>
                </span>
              ) : (
                <span className="text-sm text-gray-700">
                  <span className="font-mono font-semibold">{item.placa}</span>
                  <span className="text-gray-400 ml-2">— {item.modelo} {item.marca}</span>
                  <span className="ml-2"><Badge value={item.status} /></span>
                </span>
              )}
            </label>
          ))}
        </div>
      )}

      <div className="flex justify-end gap-3 pt-1">
        <button onClick={onClose} className="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">
          Cancelar
        </button>
        <button
          onClick={() => vincular.mutate()}
          disabled={selecionados.length === 0 || vincular.isPending}
          className="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 transition-colors"
        >
          <Link2 size={14} />
          {vincular.isPending ? 'Vinculando...' : `Vincular (${selecionados.length})`}
        </button>
      </div>
    </div>
  )
}

export default function UnidadeDetalhes() {
  const { id } = useParams()
  const navigate = useNavigate()
  const qc = useQueryClient()
  const [aba, setAba] = useState('motoristas')
  const [modalVincular, setModalVincular] = useState(null)
  const [error, setError] = useState('')

  const { data: unidade, isLoading } = useQuery({
    queryKey: ['unidade', id],
    queryFn: () => unidadesApi.buscar(id).then(r => r.data),
  })

  const desvincularMotorista = useMutation({
    mutationFn: (motoristaId) => unidadesApi.desvincularMotorista(id, motoristaId),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['unidade', id] }),
    onError: (e) => setError(e.response?.data?.message ?? 'Erro ao desvincular'),
  })

  const desvincularVeiculo = useMutation({
    mutationFn: (veiculoId) => unidadesApi.desvincularVeiculo(id, veiculoId),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['unidade', id] }),
    onError: (e) => setError(e.response?.data?.message ?? 'Erro ao desvincular'),
  })

  if (isLoading) return <LoadingSpinner />
  if (!unidade) return null

  const motoristas = unidade.motoristas ?? []
  const veiculos = unidade.veiculos ?? []

  return (
    <div className="space-y-5">
      <div className="flex items-center gap-3">
        <button
          onClick={() => navigate('/unidades')}
          className="text-gray-400 hover:text-gray-700 p-1.5 rounded-lg hover:bg-gray-100 transition-colors"
        >
          <ArrowLeft size={18} />
        </button>
        <div>
          <h2 className="text-lg font-semibold text-gray-800">{unidade.nome}</h2>
          <div className="flex items-center gap-2 mt-0.5">
            <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${
              unidade.tipo === 'matriz'
                ? 'bg-purple-100 text-purple-700'
                : 'bg-blue-100 text-blue-700'
            }`}>
              {unidade.tipo === 'matriz' ? 'Matriz' : 'Filial'}
            </span>
            <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${
              unidade.ativo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
            }`}>
              {unidade.ativo ? 'Ativa' : 'Inativa'}
            </span>
          </div>
        </div>
      </div>

      {error && <Alert type="error" message={error} />}

      {/* Abas */}
      <div className="border-b border-gray-200">
        <div className="flex gap-1">
          {[
            { key: 'motoristas', label: 'Motoristas', icon: UserCheck, count: motoristas.length },
            { key: 'veiculos', label: 'Veículos', icon: Truck, count: veiculos.length },
          ].map(({ key, label, icon: Icon, count }) => (
            <button
              key={key}
              onClick={() => setAba(key)}
              className={`flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 transition-colors ${
                aba === key
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700'
              }`}
            >
              <Icon size={15} />
              {label}
              <span className={`text-xs px-1.5 py-0.5 rounded-full ${
                aba === key ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-500'
              }`}>
                {count}
              </span>
            </button>
          ))}
        </div>
      </div>

      {/* Conteúdo da aba */}
      <div className="bg-white rounded-xl border border-gray-200">
        <div className="flex items-center justify-between px-4 py-3 border-b border-gray-100">
          <p className="text-sm text-gray-500">
            {aba === 'motoristas'
              ? `${motoristas.length} motorista(s) vinculado(s)`
              : `${veiculos.length} veículo(s) vinculado(s)`}
          </p>
          <button
            onClick={() => setModalVincular(aba)}
            className="flex items-center gap-1.5 bg-blue-600 text-white px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-blue-700 transition-colors"
          >
            <Plus size={13} />
            Vincular {aba === 'motoristas' ? 'motoristas' : 'veículos'}
          </button>
        </div>

        {aba === 'motoristas' && (
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-500 text-xs uppercase">
              <tr>
                {['Nome', 'CPF', 'Turno', 'Status', ''].map(h => (
                  <th key={h} className="px-4 py-3 text-left font-medium">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {motoristas.length === 0 ? (
                <tr><td colSpan={5} className="px-4 py-8 text-center text-gray-400">Nenhum motorista vinculado</td></tr>
              ) : motoristas.map(m => (
                <tr key={m.id} className="hover:bg-gray-50">
                  <td className="px-4 py-3 font-medium text-gray-800">{m.nome}</td>
                  <td className="px-4 py-3 text-gray-500">{m.cpf}</td>
                  <td className="px-4 py-3 text-gray-500 capitalize">{m.turno_padrao ?? '—'}</td>
                  <td className="px-4 py-3"><Badge value={m.status} /></td>
                  <td className="px-4 py-3">
                    <button
                      onClick={() => desvincularMotorista.mutate(m.id)}
                      disabled={desvincularMotorista.isPending}
                      title="Desvincular"
                      className="text-gray-400 hover:text-red-500 transition-colors"
                    >
                      <Unlink size={14} />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}

        {aba === 'veiculos' && (
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-500 text-xs uppercase">
              <tr>
                {['Placa', 'Modelo', 'Marca', 'Status', ''].map(h => (
                  <th key={h} className="px-4 py-3 text-left font-medium">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {veiculos.length === 0 ? (
                <tr><td colSpan={5} className="px-4 py-8 text-center text-gray-400">Nenhum veículo vinculado</td></tr>
              ) : veiculos.map(v => (
                <tr key={v.id} className="hover:bg-gray-50">
                  <td className="px-4 py-3 font-mono font-semibold text-gray-800">{v.placa}</td>
                  <td className="px-4 py-3 text-gray-700">{v.modelo}</td>
                  <td className="px-4 py-3 text-gray-500">{v.marca ?? '—'}</td>
                  <td className="px-4 py-3"><Badge value={v.status} /></td>
                  <td className="px-4 py-3">
                    <button
                      onClick={() => desvincularVeiculo.mutate(v.id)}
                      disabled={desvincularVeiculo.isPending}
                      title="Desvincular"
                      className="text-gray-400 hover:text-red-500 transition-colors"
                    >
                      <Unlink size={14} />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      <Modal
        open={!!modalVincular}
        onClose={() => setModalVincular(null)}
        title={`Vincular ${modalVincular === 'motoristas' ? 'motoristas' : 'veículos'}`}
        size="md"
      >
        {modalVincular && (
          <VincularModal
            unidadeId={id}
            tipo={modalVincular}
            jaVinculados={modalVincular === 'motoristas' ? motoristas : veiculos}
            onClose={() => setModalVincular(null)}
          />
        )}
      </Modal>
    </div>
  )
}
