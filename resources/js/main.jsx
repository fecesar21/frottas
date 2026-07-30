import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import '../css/app.css'
import App from './FleetApp'

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <App />
  </StrictMode>
)

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').catch(err => {
      console.warn('Falha ao registrar service worker', err)
    })
  })
}
