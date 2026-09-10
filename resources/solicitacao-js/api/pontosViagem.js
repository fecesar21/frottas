import api from './axios'

export const listar = () => api.get('/pontos-viagem')
