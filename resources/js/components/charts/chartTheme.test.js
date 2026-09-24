import { describe, it, expect } from 'vitest'
import { fmtDuracao } from './chartTheme'

describe('fmtDuracao', () => {
  it('formata minutos, horas e dias', () => {
    expect(fmtDuracao(null)).toBe('—')
    expect(fmtDuracao(45)).toBe('45min')
    expect(fmtDuracao(120)).toBe('2h')
    expect(fmtDuracao(320)).toBe('5h 20min')
    expect(fmtDuracao(1440)).toBe('1d')
    expect(fmtDuracao(2040)).toBe('1d 10h')
  })
})
