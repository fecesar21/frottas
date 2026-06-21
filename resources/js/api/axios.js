import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('hd_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

 api.interceptors.response.use(
     (res) => res,
     (error) => {
       const isLoginRequest = error.config?.url === '/auth/login'
       if (error.response?.status === 401 && !isLoginRequest) {
         localStorage.removeItem('hd_token')
         localStorage.removeItem('hd_user')
         window.location.href = '/login'
       }
     return Promise.reject(error)
   }
)

export default api
