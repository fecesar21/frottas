import api from './axios'

export const listar = (params) => api.get('/solicitacoes', { params })
export const buscar = (id) => api.get(`/solicitacoes/${id}`)
export const aceitar = (id, data) => api.patch(`/solicitacoes/${id}/aceitar`, data)
export const cancelar = (id) => api.patch(`/solicitacoes/${id}/cancelar`)
