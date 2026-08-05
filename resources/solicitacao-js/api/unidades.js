import api from './axios'

export const listar = (params) => api.get('/unidades', { params })
