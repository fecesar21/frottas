import api from './axios'

export const dashboard = () => api.get('/relatorios/dashboard')
export const dashboardGraficos = (params) => api.get('/relatorios/dashboard/graficos', { params })
export const abastecimentos = (params) => api.get('/relatorios/abastecimentos', { params })
export const abastecimentosPdf = (params) => api.get('/relatorios/abastecimentos/pdf', { params, responseType: 'blob' })
export const viagens = (params) => api.get('/relatorios/viagens', { params })
export const viagensPdf = (params) => api.get('/relatorios/viagens/pdf', { params, responseType: 'blob' })
export const plantao = (params) => api.get('/relatorios/plantao', { params })
export const plantaoPdf = (params) => api.get('/relatorios/plantao/pdf', { params, responseType: 'blob' })
export const motoristas = () => api.get('/relatorios/motoristas')
export const motoristasPdf = () => api.get('/relatorios/motoristas/pdf', { responseType: 'blob' })
export const checkins = () => api.get('/relatorios/checkins')
