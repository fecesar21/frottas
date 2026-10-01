import api from './axios'

export const listar = (params) => api.get('/solicitacoes', { params })
export const buscar = (id) => api.get(`/solicitacoes/${id}`)
export const aceitar = (id, data) => api.patch(`/solicitacoes/${id}/aceitar`, data)
export const recusar = (id, motivo) => api.patch(`/solicitacoes/${id}/recusar`, { motivo })
export const cancelar = (id) => api.patch(`/solicitacoes/${id}/cancelar`)
export const motoristaAceitar = (id, kmSaida) => api.patch(`/solicitacoes/${id}/motorista-aceitar`, kmSaida != null ? { km_saida: kmSaida } : {})
export const motoristaRecusar = (id, motivo) => api.patch(`/solicitacoes/${id}/motorista-recusar`, { motivo })
export const assumir = (id, kmSaida) => api.patch(`/solicitacoes/${id}/assumir`, kmSaida != null ? { km_saida: kmSaida } : {})
