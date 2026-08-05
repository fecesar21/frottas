import api from './axios'

export const naoLidas = () => api.get('/notificacoes/nao-lidas')
export const marcarTodasLidas = () => api.post('/notificacoes/marcar-lidas')
