import { NavLink, Routes, Route, Navigate } from 'react-router-dom'
import RelatorioAbastecimentos from './RelatorioAbastecimentos'
import RelatorioViagens from './RelatorioViagens'
import RelatorioPlantao from './RelatorioPlantao'
import RelatorioMotoristas from './RelatorioMotoristas'

const tabs = [
  { to: 'abastecimentos', label: 'Abastecimentos' },
  { to: 'viagens', label: 'Viagens' },
  { to: 'plantao', label: 'Plantão' },
  { to: 'motoristas', label: 'Motoristas' },
]

export default function Relatorios() {
  return (
    <div className="space-y-4">
      <nav className="flex gap-1 bg-white border border-gray-200 rounded-xl p-1">
        {tabs.map(({ to, label }) => (
          <NavLink key={to} to={to}
            className={({ isActive }) =>
              `flex-1 text-center px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                isActive ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100'
              }`
            }>
            {label}
          </NavLink>
        ))}
      </nav>

      <Routes>
        <Route index element={<Navigate to="abastecimentos" replace />} />
        <Route path="abastecimentos" element={<RelatorioAbastecimentos />} />
        <Route path="viagens" element={<RelatorioViagens />} />
        <Route path="plantao" element={<RelatorioPlantao />} />
        <Route path="motoristas" element={<RelatorioMotoristas />} />
      </Routes>
    </div>
  )
}
