import api from './axios'

export const listar = (params) => api.get('/checkins', { params })
export const buscar = (id) => api.get(`/checkins/${id}`)
export const criar = (data) => api.post('/checkins', data)
export const checkout = (id, data) => api.patch(`/checkins/${id}/checkout`, data)
