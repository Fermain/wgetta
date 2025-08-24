import { defineConfig } from 'vite'
import { svelte } from '@sveltejs/vite-plugin-svelte'
import tailwind from '@tailwindcss/vite'
import forms from '@tailwindcss/forms'
import typography from '@tailwindcss/typography'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    svelte(),
    tailwind({
      plugins: [forms(), typography()]
    })
  ],
  build: {
    outDir: '../dist',
    emptyOutDir: false,
    manifest: true,
    sourcemap: true
  }
})
