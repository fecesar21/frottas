import api from './axios'

export const listar = (params) => api.get('/motivos-viagem', { params })
