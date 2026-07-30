import { BarChart, Bar, Cell, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts'
import { CHART_COLORS } from './chartTheme'

export default function BarChartCard({
  title,
  data,
  xKey,
  valueKey,
  valueFormatter = String,
  layout = 'vertical', // 'vertical' = colunas em pé; 'horizontal' = barras deitadas
  loading,
  color = CHART_COLORS[0],
  colorByItem = false, // true = cada barra recebe uma cor da paleta (ex: por motorista)
}) {
  const isHorizontal = layout === 'horizontal'
  const height = isHorizontal ? Math.max(220, (data?.length ?? 0) * 40) : 260

  return (
    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
      <h3 className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-4">{title}</h3>
      {loading ? (
        <div className="h-64 animate-pulse bg-gray-100 rounded-lg" />
      ) : !data?.length ? (
        <div className="h-40 flex items-center justify-center text-sm text-gray-400">Sem dados no período</div>
      ) : (
        <ResponsiveContainer width="100%" height={height}>
          <BarChart
            data={data}
            layout={isHorizontal ? 'vertical' : 'horizontal'}
            margin={{ top: 5, right: 20, left: isHorizontal ? 10 : 0, bottom: 5 }}
          >
            <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
            {isHorizontal ? (
              <>
                <XAxis type="number" tick={{ fontSize: 12 }} stroke="#9ca3af" tickFormatter={valueFormatter} />
                <YAxis type="category" dataKey={xKey} tick={{ fontSize: 12 }} stroke="#9ca3af" width={110} />
              </>
            ) : (
              <>
                <XAxis dataKey={xKey} tick={{ fontSize: 12 }} stroke="#9ca3af" />
                <YAxis tick={{ fontSize: 12 }} stroke="#9ca3af" tickFormatter={valueFormatter} width={70} />
              </>
            )}
            <Tooltip formatter={(value) => valueFormatter(value)} />
            <Bar dataKey={valueKey} fill={color} radius={isHorizontal ? [0, 4, 4, 0] : [4, 4, 0, 0]}>
              {colorByItem && data.map((_, i) => (
                <Cell key={i} fill={CHART_COLORS[i % CHART_COLORS.length]} />
              ))}
            </Bar>
          </BarChart>
        </ResponsiveContainer>
      )}
    </div>
  )
}
