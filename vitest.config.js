import { defineConfig } from 'vitest/config'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./resources/tests/setup.js'],
    include: ['resources/**/*.test.{js,jsx}'],
    restoreMocks: true,
  },
})
