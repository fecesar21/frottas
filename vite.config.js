import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  root: '.',
  publicDir: 'public_assets',
  build: {
    outDir: 'public',
    emptyOutDir: false,
    rollupOptions: {
      input: {
        main: 'index.html',
        solicitacao: 'solicitacao.html',
      },
    },
  },
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
})
