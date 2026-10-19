import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    // `npm run dev` talks to the API of the running Docker stack.
    proxy: {
      '/api': process.env.API_URL ?? 'http://localhost:8080',
    },
  },
})
