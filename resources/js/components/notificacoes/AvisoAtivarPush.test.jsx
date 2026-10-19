import { describe, it, expect, vi, afterEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import AvisoAtivarPush from './AvisoAtivarPush'

let perfil = { isOperador: true }
vi.mock('../../contexts/AuthContext', () => ({ useAuth: () => perfil }))
vi.mock('../../lib/push', () => ({
  pushSuportado: () => true,
  ativarPush: vi.fn(),
  sincronizarPush: vi.fn(() => Promise.resolve()),
}))

const comPermissao = (permission) => vi.stubGlobal('Notification', { permission })

afterEach(() => { vi.unstubAllGlobals(); perfil = { isOperador: true } })

describe('AvisoAtivarPush', () => {
  it('convida a ativar quando a permissão ainda não foi pedida', () => {
    comPermissao('default')
    render(<AvisoAtivarPush />)
    expect(screen.getByRole('button', { name: 'Ativar' })).toBeInTheDocument()
  })

  it('explica como liberar quando as notificações estão bloqueadas', () => {
    comPermissao('denied')
    render(<AvisoAtivarPush />)
    expect(screen.getByText(/notificações estão bloqueadas/)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Ativar' })).not.toBeInTheDocument()
  })

  it('não mostra nada com permissão concedida ou para quem não é motorista', () => {
    comPermissao('granted')
    const { container, rerender } = render(<AvisoAtivarPush />)
    expect(container).toBeEmptyDOMElement()

    comPermissao('denied')
    perfil = { isOperador: false }
    rerender(<AvisoAtivarPush key="gestor" />)
    expect(container).toBeEmptyDOMElement()
  })
})
