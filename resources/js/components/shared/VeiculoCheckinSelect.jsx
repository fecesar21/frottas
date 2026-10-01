import { Ambulance, Car } from 'lucide-react'

const ehAmbulancia = (v) => /ambul/i.test(`${v?.modelo ?? ''} ${v?.tipo ?? ''}`)

/**
 * Veículo do operador numa operação (viagem, abastecimento). Com um único
 * check-in ativo mostra o veículo fixo; com dois (check-in duplo), o motorista
 * escolhe qual deles usar.
 */
export default function VeiculoCheckinSelect({ checkins, value, onChange }) {
  if (checkins.length <= 1) {
    const c = checkins[0]
    return (
      <div className="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-sm text-gray-700">
        {c?.veiculo?.placa ?? c?.veiculo_id ?? '—'}
      </div>
    )
  }

  return (
    <div role="radiogroup" aria-label="Veículo" className="grid grid-cols-1 sm:grid-cols-2 gap-2">
      {checkins.map((c) => {
        const selecionado = value === c.veiculo_id
        const Icone = ehAmbulancia(c.veiculo) ? Ambulance : Car
        return (
          <button key={c.id} type="button" role="radio" aria-checked={selecionado}
            onClick={() => onChange(c.veiculo_id)}
            className={`flex items-center gap-3 border rounded-lg px-3 py-2 text-left text-sm transition-colors ${selecionado ? 'border-blue-600 bg-blue-50 ring-2 ring-blue-500' : 'border-gray-300 hover:border-blue-400'}`}>
            <Icone size={20} className={selecionado ? 'text-blue-600' : 'text-gray-500'} />
            <span>
              <span className="block font-mono font-semibold text-gray-800">{c.veiculo?.placa ?? c.veiculo_id}</span>
              <span className="block text-xs text-gray-500">{c.veiculo?.modelo ?? ''}</span>
            </span>
          </button>
        )
      })}
    </div>
  )
}
