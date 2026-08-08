import api from './axios'

export const pendente = () => api.get('/checklist-veiculo/pendente')
export const buscar = (id) => api.get(`/checklist-veiculo/${id}`)

export const atualizarItem = (checklistId, { item_modelo_id, conforme, observacao, foto }) => {
  const formData = new FormData()
  formData.append('item_modelo_id', item_modelo_id)
  formData.append('conforme', conforme ? 1 : 0)
  if (observacao) formData.append('observacao', observacao)
  if (foto) formData.append('foto', foto)
  formData.append('_method', 'PATCH')

  return api.post(`/checklist-veiculo/${checklistId}/item`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

export const enviar = (checklistId, data) => api.patch(`/checklist-veiculo/${checklistId}/enviar`, data)
