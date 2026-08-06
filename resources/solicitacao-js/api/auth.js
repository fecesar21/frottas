import api from './axios'

export const login = (data) => api.post('/auth/login-ad', data)
export const logout = () => api.post('/auth/logout')
export const me = () => api.get('/auth/me')
