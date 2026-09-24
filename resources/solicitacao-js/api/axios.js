import axios from 'axios'

const LOGIN_AD_ENDPOINT = '/auth/login-ad'

const api = axios.create({
  baseURL: '/api',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('hd_solicitacao_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

api.interceptors.response.use(
  (res) => res,
  (error) => {
    // Credenciais inválidas no login devolvem 401: deixa o erro chegar à tela
    // de login para exibir a mensagem, em vez de recarregar a página.
    const isLoginRequest = error.config?.url?.includes(LOGIN_AD_ENDPOINT)
    if (error.response?.status === 401 && !isLoginRequest) {
      localStorage.removeItem('hd_solicitacao_token')
      localStorage.removeItem('hd_solicitacao_user')
      window.location.href = '/solicitar/login'
    }
    return Promise.reject(error)
  }
)

export default api
