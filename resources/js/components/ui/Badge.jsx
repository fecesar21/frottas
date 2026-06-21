const colorMap = {
  disponivel:  'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20',
  em_uso:      'bg-brand-50 text-brand-700 ring-1 ring-inset ring-brand-500/20',
  manutencao:  'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20',
  inativo:     'bg-gray-100 text-gray-500 ring-1 ring-inset ring-gray-400/20',
  ativo:       'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20',
  ativo_driver:'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20',
  em_andamento:'bg-brand-50 text-brand-700 ring-1 ring-inset ring-brand-500/20',
  concluida:   'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20',
  cancelada:   'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20',
  finalizado:  'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20',
  pendente:    'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20',
  ok:          'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20',
  pendencia:   'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20',
  na:          'bg-gray-100 text-gray-400 ring-1 ring-inset ring-gray-300/20',
  admin:       'bg-purple-50 text-purple-700 ring-1 ring-inset ring-purple-600/20',
  gestor:      'bg-brand-50 text-brand-700 ring-1 ring-inset ring-brand-500/20',
  operador:    'bg-gray-100 text-gray-600 ring-1 ring-inset ring-gray-400/20',
  vencendo:    'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20',
  vencida:     'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20',
}

const labelMap = {
  disponivel:  'Disponível',
  em_uso:      'Em uso',
  manutencao:  'Manutenção',
  inativo:     'Inativo',
  ativo:       'Ativo',
  em_andamento:'Em andamento',
  concluida:   'Concluída',
  cancelada:   'Cancelada',
  finalizado:  'Finalizado',
  pendente:    'Pendente',
  ok:          'OK',
  pendencia:   'Pendência',
  na:          'N/A',
  admin:       'Admin',
  gestor:      'Gestor',
  operador:    'Operador',
  vencendo:    'Vencendo',
  vencida:     'Vencida',
}

export default function Badge({ value, className = '' }) {
  const color = colorMap[value] ?? 'bg-gray-100 text-gray-500 ring-1 ring-inset ring-gray-300/20'
  const label = labelMap[value] ?? value
  return (
    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${color} ${className}`}>
      {label}
    </span>
  )
}
