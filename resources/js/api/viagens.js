import api from './axios'

export const listar = (params) => api.get('/viagens', { params })
export const buscar = (id) => api.get(`/viagens/${id}`)
export const criar = (data) => api.post('/viagens', data)
export const atualizar = (id, data) => api.put(`/viagens/${id}`, data)
export const chegada        = (id, data) => api.patch(`/viagens/${id}/chegada`, data)
export const registrarPonto = (id, data) => api.post(`/viagens/${id}/pontos`, data)
export const buscarPontos   = (id)       => api.get(`/viagens/${id}/pontos`)
