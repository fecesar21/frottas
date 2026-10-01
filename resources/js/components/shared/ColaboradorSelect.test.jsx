import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import ColaboradorSelect from './ColaboradorSelect'

vi.mock('../../api/unidades', () => ({
  listar: vi.fn(() => Promise.resolve({ data: [{ id: 'u1', nome: 'MATRIZ' }] })),
}))
vi.mock('../../api/colaboradores', () => ({
  listar: vi.fn(() => Promise.resolve({ data: { data: [
    { id: 'c1', nome: 'ANA PAULA', unidade: 'MATRIZ', departamento: 'ENFERMAGEM' },
  ] } })),
}))

const renderComQuery = (ui) =>
  render(<QueryClientProvider client={new QueryClient()}>{ui}</QueryClientProvider>)

describe('ColaboradorSelect', () => {
  it('busca pelo nome e adiciona o colaborador escolhido', async () => {
    const onChange = vi.fn()
    renderComQuery(<ColaboradorSelect value={[]} onChange={onChange} />)

    fireEvent.change(screen.getByPlaceholderText(/buscar colaborador/i), { target: { value: 'ana' } })
    fireEvent.click(await screen.findByText('ANA PAULA', {}, { timeout: 2000 }))

    expect(onChange).toHaveBeenCalledWith([expect.objectContaining({ id: 'c1' })])
  })

  it('remove um colaborador selecionado', () => {
    const onChange = vi.fn()
    renderComQuery(<ColaboradorSelect value={[{ id: 'c1', nome: 'ANA PAULA' }]} onChange={onChange} />)

    fireEvent.click(screen.getByRole('button', { name: 'Remover ANA PAULA' }))

    expect(onChange).toHaveBeenCalledWith([])
  })
})
