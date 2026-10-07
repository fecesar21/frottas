import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import PainelFrota from './PainelFrota'
import * as frotaApi from '../api/frota'

vi.mock('../api/frota')

describe('PainelFrota', () => {
  it('mostra veículos e motoristas com o status atual', async () => {
    frotaApi.status.mockResolvedValue({ data: {
      veiculos: [
        { id: 'v1', placa: 'AAA1111', modelo: 'SPIN', status: 'disponivel', motorista_nome: 'JOÃO' },
        { id: 'v2', placa: 'BBB2222', modelo: 'AMBULANCIA', status: 'em_viagem', motorista_nome: 'MARIA' },
        { id: 'v3', placa: 'CCC3333', modelo: 'GOL', status: 'manutencao', motorista_nome: null },
      ],
      motoristas: [{ id: 'm1', nome: 'JOÃO', status: 'disponivel', placa: 'AAA1111' }],
      atualizado_em: '2026-10-06T23:40:00-03:00',
    } })

    render(<PainelFrota />)

    expect(await screen.findByText('BBB2222')).toBeInTheDocument()
    expect(screen.getByText('Em Viagem')).toBeInTheDocument()
    expect(screen.getByText('Em Manutenção')).toBeInTheDocument()
    expect(screen.getByText('1 disponíveis · 1 em viagem · 1 em manutenção')).toBeInTheDocument()

    fireEvent.click(screen.getByText('Motoristas (1)'))
    expect(screen.getByText('JOÃO')).toBeInTheDocument()
    expect(screen.getByText('Disponível')).toBeInTheDocument()
  })
})
