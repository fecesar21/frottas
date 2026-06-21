export default function LoadingSpinner({ text = 'Carregando...' }) {
  return (
    <div className="flex flex-col items-center justify-center py-16 gap-3">
      <div className="w-9 h-9 border-4 border-brand-100 border-t-brand-500 rounded-full animate-spin" />
      <p className="text-sm text-gray-400 font-medium">{text}</p>
    </div>
  )
}
