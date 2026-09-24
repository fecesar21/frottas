import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import Sidebar from './Sidebar'
import { useAuth } from '../../contexts/AuthContext'

vi.mock('../../contexts/AuthContext', () => ({ useAuth: vi.fn() }))

const MENUS_GESTAO = [
  'Dashboard', 'Veículos', 'Motoristas', 'Check-ins',
  'Viagens', 'Abastecimentos', 'Relatórios',
]

function mockAuth(perfil, extra = {}) {
  useAuth.mockReturnValue({
    user: { nome: 'Teste Usuário', perfil },
    isAdmin: perfil === 'admin',
    isGestor: ['admin', 'gestor'].includes(perfil),
    isOperador: perfil === 'operador',
    checkinAtivo: null,
    logout: vi.fn(),
    ...extra,
  })
}

function renderSidebar() {
  return render(
    <MemoryRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <Sidebar open={false} onClose={() => {}} />
    </MemoryRouter>
  )
}

const link = (nome) => screen.queryByRole('link', { name: nome })

describe('Sidebar', () => {
  it('operador com check-in vê apenas Check-ins, Viagens e Abastecimentos', () => {
    mockAuth('operador', { checkinAtivo: { id: 'c1' } })
    renderSidebar()

    expect(link('Dashboard')).not.toBeInTheDocument()
    expect(link('Relatórios')).not.toBeInTheDocument()
    expect(link('Veículos')).not.toBeInTheDocument()
    expect(link('Solicitações de Transporte')).not.toBeInTheDocument()
    expect(link('Usuários')).not.toBeInTheDocument()

    expect(link('Check-ins')).toBeInTheDocument()
    expect(link('Viagens')).toBeInTheDocument()
    expect(link('Abastecimentos')).toBeInTheDocument()
    expect(screen.getAllByRole('link')).toHaveLength(3)
  })

  it('operador sem check-in vê somente Check-ins', () => {
    mockAuth('operador')
    renderSidebar()

    expect(screen.getAllByRole('link')).toHaveLength(1)
    expect(link('Check-ins')).toBeInTheDocument()
  })

  it.each(['gestor', 'admin'])('%s vê todos os menus do sistema', (perfil) => {
    mockAuth(perfil)
    renderSidebar()

    for (const nome of MENUS_GESTAO) {
      expect(link(nome)).toBeInTheDocument()
    }
    expect(link('Solicitações de Transporte')).toBeInTheDocument()
  })

  it.each(['admin', 'gestor', 'operador'])('%s não vê Escalas nem Passagem de Plantão', (perfil) => {
    mockAuth(perfil, { checkinAtivo: { id: 'c1' } })
    renderSidebar()

    expect(link('Escalas')).not.toBeInTheDocument()
    expect(link('Passagem de Plantão')).not.toBeInTheDocument()
  })

  it('somente admin vê a seção ADMIN', () => {
    mockAuth('gestor')
    const { unmount } = renderSidebar()
    expect(link('Usuários')).not.toBeInTheDocument()
    unmount()

    mockAuth('admin')
    renderSidebar()
    expect(link('Usuários')).toBeInTheDocument()
    expect(link('Unidades')).toBeInTheDocument()
    expect(link('Configurações')).toBeInTheDocument()
  })
})
