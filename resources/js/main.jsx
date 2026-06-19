import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import '../css/app.css'
import App from './FleetApp'

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <App />
  </StrictMode>
)
