import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    build: {
        // ApexCharts ships as one ~580 kB module; keep it separate for caching.
        chunkSizeWarningLimit: 600,
        rollupOptions: {
            output: {
                manualChunks: {
                    charts: ['apexcharts'],
                    calendar: ['@fullcalendar/core', '@fullcalendar/daygrid', '@fullcalendar/list', '@fullcalendar/timegrid', '@fullcalendar/interaction'],
                },
            },
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
