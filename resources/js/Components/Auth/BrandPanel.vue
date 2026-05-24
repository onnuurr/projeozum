<script setup>
defineProps({
    eyebrow: { type: String, default: 'v0.1 ' },
    backHref: { type: String, default: '#' },
    backLabel: { type: String, default: 'Ana sayfa' },
});
</script>

<template>
    <aside class="brand-panel" aria-hidden="true">
        <div class="brand-top">
            <div class="brand-logo">
                <span class="brand-logo-icon">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                        <path d="M3 7l9-4 9 4-9 4-9-4z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                        <path d="M3 12l9 4 9-4M3 17l9 4 9-4" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                    </svg>
                </span>
                <slot name="logo">OZM<strong>ERP</strong></slot>
            </div>
            
        </div>

        <div class="brand-hero">
            <span class="brand-eyebrow">
                <span class="dot"></span>
                {{ eyebrow }}
            </span>
            <h2 class="brand-headline">
                <slot name="headline">
                    Üretim hattınızı <em>akıllı</em> yönetin, kararlarınızı veriye dayandırın.
                </slot>
            </h2>
            <p class="brand-sub">
                <slot name="sub">
                    Sipariş, stok, kalite ve sevkiyatı tek panelde birleştiren modern tekstil ERP'si — gerçek zamanlı görünürlük ve tutarlı iş akışı için.
                </slot>
            </p>
        </div>

        <div class="glass-stack" aria-hidden="true">
            <div class="glass-card gc-1">
                <div class="gc-row">
                    <span class="gc-icon b1">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M3 3v18h18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                            <path d="M7 14l4-4 3 3 5-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <div class="gc-text">
                        <div class="gc-title">Üretim Verimliliği</div>
                        <div class="gc-meta">Bu hafta · 12 hat</div>
                    </div>
                    <div class="gc-value">%92</div>
                </div>
                <div class="gc-bar"><span></span></div>
            </div>

            <div class="glass-card gc-2">
                <div class="gc-row">
                    <span class="gc-icon b2">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M20 7L9 18l-5-5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <div class="gc-text">
                        <div class="gc-title">Tamamlanan Sipariş</div>
                        <div class="gc-meta">Mayıs · 248 adet</div>
                    </div>
                    <div class="gc-value">+18%</div>
                </div>
            </div>

            <div class="glass-card gc-3">
                <div class="gc-row">
                    <span class="gc-icon b3">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        </svg>
                    </span>
                    <div class="gc-text">
                        <div class="gc-title">Aktif İş Emri</div>
                        <div class="gc-meta">Şu an · 7 atölye</div>
                    </div>
                    <div class="gc-value">36</div>
                </div>
            </div>
        </div>

        <div class="brand-foot">
            <div class="feature">
                <div class="feature-num">2.4<span>K+</span></div>
                <div class="feature-label">Aktif kullanıcı</div>
            </div>
            <div class="feature">
                <div class="feature-num">99.9<span>%</span></div>
                <div class="feature-label">Hizmet süresi</div>
            </div>
            <div class="feature">
                <div class="feature-num">120<span>+</span></div>
                <div class="feature-label">Üretim modülü</div>
            </div>
        </div>
    </aside>
</template>
