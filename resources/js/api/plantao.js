import api from './axios'

export const listar = (params) => api.get('/plantao', { params })
export const buscar = (id) => api.get(`/plantao/${id}`)
export const criar = (data) => api.post('/plantao', data)
export const modeloItens = () => api.get('/plantao/modelo/itens')
export const atualizarItem = (id, data) => api.patch(`/plantao/${id}/item`, data)
export const finalizar = (id, data) => api.patch(`/plantao/${id}/finalizar`, data)
