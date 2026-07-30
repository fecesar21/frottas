import { PieChart, Pie, Cell, Tooltip, Legend, ResponsiveContainer } from 'recharts'
import { CHART_COLORS } from './chartTheme'

const MAX_FATIAS = 7

function agruparOutros(data, nameKey, valueKey, pctKey) {
  if (!data || data.length <= MAX_FATIAS) return data ?? []

  const principais = data.slice(0, MAX_FATIAS - 1)
  const resto = data.slice(MAX_FATIAS - 1)
  const outros = resto.reduce(
    (acc, item) => ({
      [nameKey]: 'Outros',
      [valueKey]: acc[valueKey] + (item[valueKey] ?? 0),
      [pctKey]: acc[pctKey] + (item[pctKey] ?? 0),
    }),
    { [nameKey]: 'Outros', [valueKey]: 0, [pctKey]: 0 }
  )

  return [...principais, outros]
}

export default function PieChartCard({ title, data, nameKey, valueKey, pctKey, loading }) {
  const chartData = agruparOutros(data, nameKey, valueKey, pctKey)

  return (
    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
      <h3 className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-4">{title}</h3>
      {loading ? (
        <div className="h-64 animate-pulse bg-gray-100 rounded-lg" />
      ) : !chartData.length ? (
        <div className="h-40 flex items-center justify-center text-sm text-gray-400">Sem dados no período</div>
      ) : (
        <ResponsiveContainer width="100%" height={280}>
          <PieChart>
            <Pie
              data={chartData}
              dataKey={valueKey}
              nameKey={nameKey}
              cx="50%"
              cy="50%"
              outerRadius={90}
              label={({ payload }) => `${payload[pctKey]?.toFixed(1)}%`}
            >
              {chartData.map((_, i) => (
                <Cell key={i} fill={CHART_COLORS[i % CHART_COLORS.length]} />
              ))}
            </Pie>
            <Tooltip formatter={(value, name, item) => [`${value} (${item.payload[pctKey]?.toFixed(1)}%)`, name]} />
            <Legend wrapperStyle={{ fontSize: 12 }} />
          </PieChart>
        </ResponsiveContainer>
      )}
    </div>
  )
}
