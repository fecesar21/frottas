import api from './axios'

export const listar = (params) => api.get('/motoristas', { params })
export const buscar = (id) => api.get(`/motoristas/${id}`)
export const criar = (data) => api.post('/motoristas', data)
export const atualizar = (id, data) => api.put(`/motoristas/${id}`, data)
export const desativar = (id) => api.delete(`/motoristas/${id}`)
export const alertasCnh = () => api.get('/motoristas/alertas/cnh')
export const atualizarStatus = (id, status) => api.patch(`/motoristas/${id}`, { status })
export const listarDisponiveis = () => api.get('/motoristas/disponiveis')
