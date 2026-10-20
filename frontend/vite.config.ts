import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    // Dev server proxies to the API of the Docker stack.
    proxy: {
      '/api': process.env.API_URL ?? 'http://localhost:8080',
    },
  },
})
