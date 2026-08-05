import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { AuthProvider } from './contexts/AuthContext'
import { PrivateRoute } from './components/Layout'

import Login from './pages/Login'
import NovaSolicitacao from './pages/NovaSolicitacao'
import MinhasSolicitacoes from './pages/MinhasSolicitacoes'

export default function App() {
  return (
    <AuthProvider>
      <BrowserRouter basename="/solicitar">
        <Routes>
          <Route path="/login" element={<Login />} />
          <Route element={<PrivateRoute />}>
            <Route path="/" element={<NovaSolicitacao />} />
            <Route path="/minhas-solicitacoes" element={<MinhasSolicitacoes />} />
          </Route>
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  )
}
