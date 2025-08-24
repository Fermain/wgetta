import path from 'path';
import { defineConfig } from 'vite';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import tailwind from '@tailwindcss/vite';

// https://vite.dev/config/
export default defineConfig({
  plugins: [svelte(), tailwind()],
  resolve: {
    alias: {
      $lib: path.resolve('./src/lib')
    }
  },
  build: {
    outDir: '../dist',
    emptyOutDir: false,
    manifest: true,
    sourcemap: true
  }
});
