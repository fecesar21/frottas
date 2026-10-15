import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Pause, Play } from 'lucide-react'
import { format } from 'date-fns'
import * as viagensApi from '../../api/viagens'
import * as localidadesApi from '../../api/localidades'
import Modal from '../../components/ui/Modal'

// Parada intermediária no trajeto (viagem em andamento): registra ou retoma.
// `circular`: botão grande redondo para o motorista no celular (barra fixa inferior).
export default function ParadaControle({ viagem, circular = false }) {
  const qc = useQueryClient()
  const [aberto, setAberto] = useState(false)
  const [form, setForm] = useState({ local: '', motivo: '' })
  const [erro, setErro] = useState('')
  const paradaAberta = (viagem.paradas ?? []).find(p => !p.saida_at)

  const { data: localidades = [] } = useQuery({
    queryKey: ['localidades'],
    queryFn: () => localidadesApi.listar().then(r => r.data),
    enabled: aberto,
  })

  const onErro = (e) => setErro(e.response?.data?.errors?.local?.[0] ?? e.response?.data?.message ?? 'Erro ao registrar parada')
  const registrar = useMutation({
    mutationFn: () => viagensApi.registrarParada(viagem.id, form),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['viagens'] }); setAberto(false) },
    onError: onErro,
  })
  const retomar = useMutation({
    mutationFn: () => viagensApi.retomarViagem(viagem.id, paradaAberta.id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['viagens'] }),
    onError: onErro,
  })

  const circulo = 'w-36 h-36 rounded-full shadow-lg flex flex-col items-center justify-center gap-1 text-white text-sm font-semibold leading-tight text-center px-3 disabled:opacity-60'

  if (paradaAberta && circular) {
    return (
      <button onClick={() => retomar.mutate()} disabled={retomar.isPending} className={`${circulo} bg-blue-600 active:bg-blue-700`}>
        <Play size={26} />
        {retomar.isPending ? 'Retomando...' : 'Retomar Viagem'}
      </button>
    )
  }

  if (paradaAberta) {
    return (
      <div className="flex items-center gap-2 flex-wrap">
        <span className="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1">
          Parado em {paradaAberta.local} desde {format(new Date(paradaAberta.chegada_at), 'HH:mm')}
        </span>
        <button onClick={() => retomar.mutate()} disabled={retomar.isPending}
          className="flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 border border-blue-300 rounded px-2 py-1 hover:bg-blue-50 transition-colors disabled:opacity-60">
          <Play size={12} /> {retomar.isPending ? 'Retomando...' : 'Retomar viagem'}
        </button>
        {erro && <span className="text-xs text-red-600">{erro}</span>}
      </div>
    )
  }

  return (
    <>
      {circular ? (
        <button onClick={() => { setForm({ local: '', motivo: '' }); setErro(''); setAberto(true) }} className={`${circulo} bg-amber-600 active:bg-amber-700`}>
          <Pause size={26} />
          Adicionar Parada
        </button>
      ) : (
        <button onClick={() => { setForm({ local: '', motivo: '' }); setErro(''); setAberto(true) }}
          className="flex items-center gap-1 text-xs text-amber-700 hover:text-amber-900 border border-amber-300 rounded px-2 py-1 hover:bg-amber-50 transition-colors">
          <Pause size={12} /> Parada
        </button>
      )}
      <Modal open={aberto} onClose={() => setAberto(false)} title="Registrar parada no trajeto">
        <form onSubmit={(e) => { e.preventDefault(); setErro(''); registrar.mutate() }} className="space-y-4">
          {erro && <p className="text-sm text-red-600">{erro}</p>}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1" htmlFor="parada-local">Local *</label>
            <input id="parada-local" required maxLength={150} list="parada-localidades" value={form.local}
              onChange={e => setForm(f => ({ ...f, local: e.target.value }))}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm uppercase focus:outline-none focus:ring-2 focus:ring-blue-500" />
            <datalist id="parada-localidades">
              {localidades.filter(l => l.ativo).map(l => <option key={l.id} value={l.nome.toUpperCase()} />)}
            </datalist>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1" htmlFor="parada-motivo">Motivo</label>
            <input id="parada-motivo" maxLength={250} value={form.motivo} placeholder="Ex.: buscar acompanhante"
              onChange={e => setForm(f => ({ ...f, motivo: e.target.value }))}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div className="flex justify-end">
            <button type="submit" disabled={registrar.isPending}
              className="bg-amber-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-amber-700 disabled:opacity-60">
              {registrar.isPending ? 'Registrando...' : 'Registrar parada'}
            </button>
          </div>
        </form>
      </Modal>
    </>
  )
}
