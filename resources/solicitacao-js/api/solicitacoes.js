import api from './axios'

export const listar = (params) => api.get('/solicitacoes', { params })
export const buscar = (id) => api.get(`/solicitacoes/${id}`)
export const criar = (data) => api.post('/solicitacoes', data)
export const cancelar = (id) => api.patch(`/solicitacoes/${id}/cancelar`)
