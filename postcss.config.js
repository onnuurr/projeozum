// Tailwind v4'e geçişle birlikte Tailwind işleme artık vite.config.js'teki
// @tailwindcss/vite eklentisi üzerinden yapılıyor (v3'ün burada duran ayrı
// `tailwindcss` PostCSS eklentisi kaldırıldı — v4 söz dizimini anlamıyordu,
// aynı anda çalışsaydı çakışırdı).
export default {
    plugins: {
        autoprefixer: {},
    },
};
