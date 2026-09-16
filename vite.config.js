// vite.config.js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [
    laravel({
      // Keep just your main entries; import other JSX from app.js
      input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/react/index.jsx'],
      // JS/JSX are HMR’d by Vite; these trigger full reloads for server-side changes
      refresh: ['resources/views/**', 'routes/**', 'app/**'],
    }),
    react(),
  ],
  resolve: {
    extensions: ['.js', '.jsx'],
  },
  server: {
    // watch: {
    // usePolling: true,
    // interval: 300,
    // binaryInterval: 1000,
    // },
    host: 'localhost',
    port: 5175,
    hmr: {
      host: 'localhost',
      port: 5175,
    },
  },
});
