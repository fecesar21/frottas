import api from './axios'

export const listar = (params) => api.get('/motivos-viagem', { params })
export const criar = (data) => api.post('/motivos-viagem', data)
export const atualizar = (id, data) => api.put(`/motivos-viagem/${id}`, data)
export const excluir = (id) => api.delete(`/motivos-viagem/${id}`)
