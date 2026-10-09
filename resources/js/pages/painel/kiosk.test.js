import { describe, it, expect } from 'vitest'
import { corDoTempo, formatarMinutos } from './kiosk'

describe('corDoTempo', () => {
  it('verde abaixo de 25, laranja entre 25 e 30, vermelho acima de 30', () => {
    expect(corDoTempo(24.9)).toBe('verde')
    expect(corDoTempo(25)).toBe('laranja')
    expect(corDoTempo(30)).toBe('laranja')
    expect(corDoTempo(30.1)).toBe('vermelho')
    expect(corDoTempo(null)).toBe('neutro')
  })
})

describe('formatarMinutos', () => {
  it('formata minutos e horas', () => {
    expect(formatarMinutos(null)).toBe('—')
    expect(formatarMinutos(12.4)).toBe('12 min')
    expect(formatarMinutos(75)).toBe('1h 15min')
    expect(formatarMinutos(120)).toBe('2h')
  })
})
