import api from './axios'

export const buscar = (unidadeId) => api.get(`/unidades/${unidadeId}/ldap-config`)
export const salvar = (unidadeId, data) => api.put(`/unidades/${unidadeId}/ldap-config`, data)
export const remover = (unidadeId) => api.delete(`/unidades/${unidadeId}/ldap-config`)
export const testar = (unidadeId, data) => api.post(`/unidades/${unidadeId}/ldap-config/testar`, data)
