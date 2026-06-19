import api from './axios'

export const listar = () => api.get('/usuarios')
export const criar = (data) => api.post('/usuarios', data)
export const atualizar = (id, data) => api.put(`/usuarios/${id}`, data)
export const desativar = (id) => api.delete(`/usuarios/${id}`)
