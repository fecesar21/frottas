import { CheckCircle, AlertCircle, XCircle, Info } from 'lucide-react'

const typeMap = {
  success: { icon: CheckCircle, cls: 'bg-green-50 border-green-200 text-green-800' },
  error:   { icon: XCircle,     cls: 'bg-red-50 border-red-200 text-red-800' },
  warning: { icon: AlertCircle, cls: 'bg-yellow-50 border-yellow-200 text-yellow-800' },
  info:    { icon: Info,        cls: 'bg-blue-50 border-blue-200 text-blue-800' },
}

export default function Alert({ type = 'info', message, className = '' }) {
  if (!message) return null
  const { icon: Icon, cls } = typeMap[type] ?? typeMap.info
  return (
    <div className={`flex items-start gap-3 p-4 rounded-xl border ${cls} ${className}`}>
      <Icon size={18} className="mt-0.5 shrink-0" />
      <p className="text-sm">{message}</p>
    </div>
  )
}
