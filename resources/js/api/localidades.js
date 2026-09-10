import api from './axios'

export const listar = (params) => api.get('/localidades', { params })
export const buscar = (id) => api.get(`/localidades/${id}`)
export const criar = (data) => api.post('/localidades', data)
export const atualizar = (id, data) => api.patch(`/localidades/${id}`, data)
export const desativar = (id) => api.delete(`/localidades/${id}`)
