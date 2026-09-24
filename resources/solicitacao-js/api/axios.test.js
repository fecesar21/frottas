import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { AxiosError } from 'axios'
import api from './axios'

// Adapter falso: toda requisição falha com o status informado,
// passando pelo interceptor de resposta real da instância.
function falharCom(status) {
  api.defaults.adapter = (config) =>
    Promise.reject(
      new AxiosError('Request failed', AxiosError.ERR_BAD_REQUEST, config, null, {
        status,
        data: { error: 'Usuário ou senha inválidos' },
        headers: {},
        config,
      })
    )
}

describe('interceptor de resposta da SPA de solicitação', () => {
  const locationOriginal = window.location
  let hrefSetter

  beforeEach(() => {
    hrefSetter = vi.fn()
    delete window.location
    window.location = Object.defineProperty({}, 'href', {
      get: () => 'http://localhost/solicitar/login',
      set: hrefSetter,
    })
    localStorage.setItem('hd_solicitacao_token', 'token-antigo')
    localStorage.setItem('hd_solicitacao_user', JSON.stringify({ nome: 'Fulano' }))
  })

  afterEach(() => {
    window.location = locationOriginal
  })

  it('rejeita o 401 do login AD sem redirecionar nem limpar a sessão', async () => {
    falharCom(401)

    const erro = await api.post('/auth/login-ad', { usuario: 'x', senha: 'y' }).catch(e => e)

    expect(erro.response.status).toBe(401)
    expect(hrefSetter).not.toHaveBeenCalled()
    expect(localStorage.getItem('hd_solicitacao_token')).toBe('token-antigo')
  })

  it('limpa a sessão e redireciona para o login em 401 de rota protegida', async () => {
    falharCom(401)

    const erro = await api.get('/viagens').catch(e => e)

    expect(erro.response.status).toBe(401)
    expect(localStorage.getItem('hd_solicitacao_token')).toBeNull()
    expect(localStorage.getItem('hd_solicitacao_user')).toBeNull()
    expect(hrefSetter).toHaveBeenCalledWith('/solicitar/login')
  })

  it('não redireciona em erros que não são 401', async () => {
    falharCom(422)

    await api.get('/viagens').catch(() => {})

    expect(hrefSetter).not.toHaveBeenCalled()
    expect(localStorage.getItem('hd_solicitacao_token')).toBe('token-antigo')
  })
})
