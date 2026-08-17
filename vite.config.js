import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";
import tailwindcss from "@tailwindcss/vite";
import path from "path";

function pusherWssPatch() {
    return {
        name: "pusher-wss-patch",
        transform(code, id) {
            if (id.includes("pusher-js")) {
                return code.replace(
                    /new delayed_strategy_DelayedStrategy\(wss_loop, \{ delay: 2e3 \}\)/g,
                    "// wss_loop removed for local dev",
                );
            }
        },
    };
}

export default defineConfig({
    server: {
        host: "127.0.0.1",
        port: 5173,
        strictPort: true,
        hmr: {
            host: "127.0.0.1",
        },
    },
    plugins: [
        pusherWssPatch(),
        tailwindcss(),
        laravel({
            input: ["resources/js/app.js", "resources/css/app.css"],
            refresh: [
                "resources/views/**",
                "Modules/**/Resources/views/**",
                "Modules/**/Resources/js/**",
                "Modules/**/Resources/assets/js/**",
            ],
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            "@": path.resolve(__dirname, "resources/js"),
            "@Modules": path.resolve(__dirname, "Modules/"),
        },
    },
    build: {
        // Uyarı limitini 500kb'dan 1000kb'a çıkarıyoruz
        chunkSizeWarningLimit: 1000,
        // "computing gzip size" adımı build çıktısını etkilemeyen saf bir raporlama
        // adımı ama büyük projelerde bellek yoğun — bu sunucuda 3.8GB RAM + dolu swap
        // var, resources/js/new/ eklenince build OOM-kill ile ölüyordu. Kapatmak
        // çıktı dosyalarını DEĞİŞTİRMEZ, sadece terminaldeki boyut özetini kaldırır.
        reportCompressedSize: false,
        rollupOptions: {
            output: {
                // Büyük kütüphaneleri (Vendor) ayrı bir JS dosyasına ayırıyoruz
                manualChunks: {
                    "vendor-vue": ["vue", "@inertiajs/vue3"],
                    "vendor-chart": ["chart.js"], // Chart.js'i kendi dosyasına ayır
                },
            },
        },
    },
});
