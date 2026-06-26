import api from './axios'

export const listar = (params) => api.get('/veiculos', { params })
export const buscar = (id) => api.get(`/veiculos/${id}`)
export const criar = (data) => api.post('/veiculos', data)
export const atualizar = (id, data) => api.put(`/veiculos/${id}`, data)
export const desativar = (id) => api.delete(`/veiculos/${id}`)
export const atualizarStatus = (id, status) => api.patch(`/veiculos/${id}`, { status })
