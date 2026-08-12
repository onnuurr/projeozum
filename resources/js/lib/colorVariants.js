// Badge/Tag/Avatar/ProgressBar/Spinner gibi component'lerin ortak renk-varyant haritası.
// tailwind.config.js'teki semantic token'larla (primary/success/warning/danger/info) hizalı.
export const COLOR_KEYS = ['primary', 'success', 'warning', 'danger', 'info', 'neutral'];

export const colorVariants = {
    primary: { filled: 'bg-primary text-white', tonal: 'bg-primary/10 text-primary', outlined: 'border border-primary text-primary bg-transparent', solid: 'bg-primary', dot: 'bg-primary' },
    success: { filled: 'bg-success text-white', tonal: 'bg-success/10 text-success', outlined: 'border border-success text-success bg-transparent', solid: 'bg-success', dot: 'bg-success' },
    warning: { filled: 'bg-warning text-white', tonal: 'bg-warning/10 text-warning', outlined: 'border border-warning text-warning bg-transparent', solid: 'bg-warning', dot: 'bg-warning' },
    danger: { filled: 'bg-danger text-white', tonal: 'bg-danger/10 text-danger', outlined: 'border border-danger text-danger bg-transparent', solid: 'bg-danger', dot: 'bg-danger' },
    info: { filled: 'bg-info text-white', tonal: 'bg-info/10 text-info', outlined: 'border border-info text-info bg-transparent', solid: 'bg-info', dot: 'bg-info' },
    neutral: { filled: 'bg-gray-500 text-white', tonal: 'bg-gray-100 text-gray-600', outlined: 'border border-gray-300 text-gray-600 bg-transparent', solid: 'bg-gray-400', dot: 'bg-gray-400' },
};

// Canvas (Chart.js) çizimleri Tailwind class kullanamaz, gerçek hex değer ister.
export const colorHex = {
    primary: '#F96A21',
    success: '#16A34A',
    warning: '#F59E0B',
    danger: '#DC2626',
    info: '#2563EB',
    neutral: '#9CA3AF',
};

export function hexToRgba(hex, alpha = 1) {
    const clean = hex.replace('#', '');
    const full = clean.length === 3 ? clean.split('').map((c) => c + c).join('') : clean;
    const r = parseInt(full.substring(0, 2), 16);
    const g = parseInt(full.substring(2, 4), 16);
    const b = parseInt(full.substring(4, 6), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}
