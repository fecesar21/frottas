import api from './axios'

export const dashboard = () => api.get('/relatorios/dashboard')
export const abastecimentos = (params) => api.get('/relatorios/abastecimentos', { params })
export const viagens = (params) => api.get('/relatorios/viagens', { params })
export const plantao = (params) => api.get('/relatorios/plantao', { params })
export const motoristas = () => api.get('/relatorios/motoristas')
export const checkins = () => api.get('/relatorios/checkins')
