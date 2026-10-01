import api from './axios'

export const listar = (params) => api.get('/veiculos', { params })
export const buscar = (id) => api.get(`/veiculos/${id}`)
export const criar = (data) => api.post('/veiculos', data)
export const atualizar = (id, data) => api.put(`/veiculos/${id}`, data)
export const desativar = (id) => api.delete(`/veiculos/${id}`)
export const atualizarStatus = (id, status, manutencaoMotivo) =>
  api.patch(`/veiculos/${id}`, manutencaoMotivo ? { status, manutencao_motivo: manutencaoMotivo } : { status })
export const iniciarManutencao = (id, data) => api.post(`/veiculos/${id}/manutencao`, data)
export const encerrarManutencao = (id, data) => api.post(`/veiculos/${id}/manutencao/encerrar`, data)
