const colorMap = {
  blue:   { icon: 'bg-brand-50 text-brand-600',   accent: 'card-accent-brand' },
  green:  { icon: 'bg-green-50 text-green-600',   accent: 'card-accent-green' },
  yellow: { icon: 'bg-yellow-50 text-yellow-600', accent: 'card-accent-yellow' },
  red:    { icon: 'bg-red-50 text-red-600',       accent: 'card-accent-red' },
  gray:   { icon: 'bg-gray-100 text-gray-500',    accent: 'card-accent-gray' },
}

export default function Card({ title, value, sub, icon: Icon, color = 'blue', className = '' }) {
  const { icon: iconClass, accent } = colorMap[color] ?? colorMap.blue

  return (
    <div className={`bg-white rounded-2xl shadow-sm hover:shadow-md border border-gray-100 p-5 flex items-center gap-4 transition-all duration-200 hover:-translate-y-0.5 ${accent} ${className}`}>
      {Icon && (
        <div className={`rounded-xl p-3 shrink-0 ${iconClass}`}>
          <Icon size={22} />
        </div>
      )}
      <div className="min-w-0">
        <p className="text-xs font-medium text-gray-400 uppercase tracking-wider truncate">{title}</p>
        <p className="text-3xl font-bold text-gray-800 leading-tight mt-0.5">{value}</p>
        {sub && <p className="text-xs text-gray-400 mt-0.5">{sub}</p>}
      </div>
    </div>
  )
}
