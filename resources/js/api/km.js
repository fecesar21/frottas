import api from './axios'

export const listar = (params) => api.get('/km', { params })
export const registrar = (data) => api.post('/km', data)
