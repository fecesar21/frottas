import api from './axios'

export const listar = (params) => api.get('/abastecimentos', { params })
export const resumo = () => api.get('/abastecimentos/resumo')
export const criar = (data) => api.post('/abastecimentos', data)
export const excluir = (id) => api.delete(`/abastecimentos/${id}`)
