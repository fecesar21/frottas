import api from './axios'

export const listarCorrecoes = (params) => api.get('/auditoria-correcoes', { params })
