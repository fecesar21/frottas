const MESES = [
  'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
  'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
]

export default function MonthYearFilter({ mes, ano, onChange }) {
  const anoAtual = new Date().getFullYear()
  const anos = Array.from({ length: 5 }, (_, i) => anoAtual - i)

  return (
    <div className="flex items-center gap-3 bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
      <span className="text-xs font-semibold text-gray-400 uppercase tracking-wider">Período</span>
      <select
        value={mes}
        onChange={(e) => onChange({ mes: Number(e.target.value), ano })}
        className="border border-gray-200 rounded-lg px-3 py-1.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-brand-500"
      >
        {MESES.map((nome, i) => (
          <option key={i} value={i + 1}>{nome}</option>
        ))}
      </select>
      <select
        value={ano}
        onChange={(e) => onChange({ mes, ano: Number(e.target.value) })}
        className="border border-gray-200 rounded-lg px-3 py-1.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-brand-500"
      >
        {anos.map((a) => (
          <option key={a} value={a}>{a}</option>
        ))}
      </select>
    </div>
  )
}
