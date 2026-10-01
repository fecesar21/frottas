import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import VeiculoCheckinSelect from './VeiculoCheckinSelect'

const ambulancia = { id: 'c1', veiculo_id: 'v1', veiculo: { placa: 'AMB-0001', modelo: 'Ambulância Sprinter' } }
const administrativo = { id: 'c2', veiculo_id: 'v2', veiculo: { placa: 'ADM-0002', modelo: 'Gol' } }

describe('VeiculoCheckinSelect', () => {
  it('com um check-in mostra o veículo fixo, sem opção de escolha', () => {
    render(<VeiculoCheckinSelect checkins={[ambulancia]} value="v1" onChange={vi.fn()} />)

    expect(screen.getByText('AMB-0001')).toBeInTheDocument()
    expect(screen.queryByRole('radio')).not.toBeInTheDocument()
  })

  it('com dois check-ins permite escolher o veículo', () => {
    const onChange = vi.fn()
    render(<VeiculoCheckinSelect checkins={[ambulancia, administrativo]} value="" onChange={onChange} />)

    expect(screen.getAllByRole('radio')).toHaveLength(2)
    fireEvent.click(screen.getByText('ADM-0002'))
    expect(onChange).toHaveBeenCalledWith('v2')
  })

  it('marca o veículo selecionado', () => {
    render(<VeiculoCheckinSelect checkins={[ambulancia, administrativo]} value="v1" onChange={vi.fn()} />)

    const [amb, adm] = screen.getAllByRole('radio')
    expect(amb).toHaveAttribute('aria-checked', 'true')
    expect(adm).toHaveAttribute('aria-checked', 'false')
  })
})
