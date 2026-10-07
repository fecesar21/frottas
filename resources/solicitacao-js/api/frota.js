import api from './axios'

export const status = () => api.get('/frota/status')
