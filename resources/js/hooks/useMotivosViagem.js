import { useQuery } from '@tanstack/react-query'
import * as motivosApi from '../api/motivosViagem'

export function useMotivosViagem(params = {}) {
  const { data, isLoading } = useQuery({
    queryKey: ['motivos-viagem', params],
    queryFn: () => motivosApi.listar(params).then(r => r.data.data),
    staleTime: 5 * 60_000,
  })
  return { motivos: data ?? [], isLoading }
}
