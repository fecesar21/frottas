import api from './axios'

export const listar = (params) => api.get('/colaboradores', { params })
export const sincronizar = () => api.post('/colaboradores/sincronizar')
