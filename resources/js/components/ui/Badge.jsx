const colorMap = {
  disponivel: 'bg-green-100 text-green-800',
  em_uso: 'bg-blue-100 text-blue-800',
  manutencao: 'bg-yellow-100 text-yellow-800',
  inativo: 'bg-gray-100 text-gray-600',
  ativo: 'bg-green-100 text-green-800',
  ativo_driver: 'bg-green-100 text-green-800',
  em_andamento: 'bg-blue-100 text-blue-800',
  concluida: 'bg-green-100 text-green-800',
  cancelada: 'bg-red-100 text-red-800',
  finalizado: 'bg-green-100 text-green-800',
  pendente: 'bg-yellow-100 text-yellow-800',
  ok: 'bg-green-100 text-green-800',
  pendencia: 'bg-red-100 text-red-800',
  na: 'bg-gray-100 text-gray-500',
  admin: 'bg-purple-100 text-purple-800',
  gestor: 'bg-blue-100 text-blue-800',
  operador: 'bg-gray-100 text-gray-700',
  vencendo: 'bg-yellow-100 text-yellow-800',
  vencida: 'bg-red-100 text-red-800',
}

const labelMap = {
  disponivel: 'Disponível',
  em_uso: 'Em uso',
  manutencao: 'Manutenção',
  inativo: 'Inativo',
  ativo: 'Ativo',
  em_andamento: 'Em andamento',
  concluida: 'Concluída',
  cancelada: 'Cancelada',
  finalizado: 'Finalizado',
  pendente: 'Pendente',
  ok: 'OK',
  pendencia: 'Pendência',
  na: 'N/A',
  admin: 'Admin',
  gestor: 'Gestor',
  operador: 'Operador',
  vencendo: 'Vencendo',
  vencida: 'Vencida',
}

export default function Badge({ value, className = '' }) {
  const color = colorMap[value] ?? 'bg-gray-100 text-gray-600'
  const label = labelMap[value] ?? value
  return (
    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${color} ${className}`}>
      {label}
    </span>
  )
}
