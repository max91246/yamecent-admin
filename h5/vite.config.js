import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
    plugins: [vue()],
    base: '/h5/',
    build: {
        outDir: '../public/h5',
        emptyOutDir: true,
    },
    server: {
        host: 'localhost',
        proxy: {
            '/api': {
                target: 'http://yamecent-admin.local',
                changeOrigin: true,
            }
        }
    }
})
