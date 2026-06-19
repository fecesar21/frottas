import api from './axios'

export const listar = (params) => api.get('/escalas', { params })
export const criar = (data) => api.post('/escalas', data)
export const remover = (id) => api.delete(`/escalas/${id}`)
export const gerarSemana = (data) => api.post('/escalas/semana', data)
