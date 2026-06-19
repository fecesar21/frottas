import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AuthProvider } from './contexts/AuthContext'
import { PrivateRoute, AdminRoute } from './components/layout/Layout'

import Login from './pages/Login'
import Dashboard from './pages/Dashboard'
import VeiculosList from './pages/veiculos/VeiculosList'
import MotoristasList from './pages/motoristas/MotoristasList'
import EscalasCalendario from './pages/escalas/EscalasCalendario'
import CheckinsList from './pages/checkins/CheckinsList'
import PlantaoList from './pages/plantao/PlantaoList'
import ViagensList from './pages/viagens/ViagensList'
import AbastecimentosList from './pages/abastecimentos/AbastecimentosList'
import Relatorios from './pages/relatorios/Relatorios'
import UsuariosList from './pages/usuarios/UsuariosList'

const qc = new QueryClient({
  defaultOptions: {
    queries: { retry: 1, staleTime: 30_000 },
  },
})

export default function App() {
  return (
    <QueryClientProvider client={qc}>
      <AuthProvider>
        <BrowserRouter>
          <Routes>
            <Route path="/login" element={<Login />} />
            <Route element={<PrivateRoute />}>
              <Route path="/" element={<Dashboard />} />
              <Route path="/veiculos" element={<VeiculosList />} />
              <Route path="/motoristas" element={<MotoristasList />} />
              <Route path="/escalas" element={<EscalasCalendario />} />
              <Route path="/checkins" element={<CheckinsList />} />
              <Route path="/plantao" element={<PlantaoList />} />
              <Route path="/viagens" element={<ViagensList />} />
              <Route path="/abastecimentos" element={<AbastecimentosList />} />
              <Route path="/relatorios/*" element={<Relatorios />} />
              <Route path="/usuarios" element={
                <AdminRoute><UsuariosList /></AdminRoute>
              } />
            </Route>
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </BrowserRouter>
      </AuthProvider>
    </QueryClientProvider>
  )
}
