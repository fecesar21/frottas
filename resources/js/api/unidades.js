import api from './axios'

export const listar = (params) => api.get('/unidades', { params })
export const buscar = (id) => api.get(`/unidades/${id}`)
export const criar = (data) => api.post('/unidades', data)
export const atualizar = (id, data) => api.patch(`/unidades/${id}`, data)
export const desativar = (id) => api.delete(`/unidades/${id}`)

export const vincularMotoristas = (id, motorista_ids) =>
  api.post(`/unidades/${id}/motoristas`, { motorista_ids })
export const desvincularMotorista = (id, motoristaId) =>
  api.delete(`/unidades/${id}/motoristas/${motoristaId}`)

export const vincularVeiculos = (id, veiculo_ids) =>
  api.post(`/unidades/${id}/veiculos`, { veiculo_ids })
export const desvincularVeiculo = (id, veiculoId) =>
  api.delete(`/unidades/${id}/veiculos/${veiculoId}`)
