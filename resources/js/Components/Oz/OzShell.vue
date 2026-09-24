<script setup>
import { useOzTheme } from '@/composables/useOzTheme'
import OzToastHost from './OzToastHost.vue'
import OzConfirmHost from './OzConfirmHost.vue'
import OzLoadingBar from './OzLoadingBar.vue'
import { MotionConfig } from 'motion-v'

// Oz kit sayfa iskeleti: üstte topbar, solda rail, sağda kayan içerik.
// Slots: topbar, rail, default
// Mobilde (≤768px) topbar ve rail gizlenir; yerine `mobile` slot'u (OzMobileNav: üst başlık + alt sekme çubuğu) gelir.
// OzMobileNav kök düğümü display: contents olduğundan başlık ve sekme çubuğu doğrudan bu grid'e yerleşir.
const { theme } = useOzTheme()
</script>

<template>
  <div class="oz-root" :data-theme="theme">
    <!-- reduced-motion="user": sistemde "hareketi azalt" açıksa motion animasyonları kapanır -->
    <MotionConfig reduced-motion="user">
      <OzLoadingBar />
      <div class="oz-shell">
        <div class="oz-shell-top"><slot name="topbar" /></div>
        <div class="oz-shell-rail"><slot name="rail" /></div>
        <div class="oz-shell-mobile"><slot name="mobile" /></div>
        <main class="oz-shell-content">
          <slot />
        </main>
      </div>
      <OzToastHost />
      <OzConfirmHost />
    </MotionConfig>
  </div>
</template>

<!-- Token'lar global: floating panel gibi alt bileşenler de .oz-root altındaki değişkenleri okur. -->
<style>
.oz-root,
.oz-root[data-theme="light"] {
  --oz-accent: #4f46e5;
  --oz-accent-hover: #4338ca;
  --oz-accent-strong: #3730a3;
  --oz-accent-soft: #eef2ff;
  --oz-accent-rgb: 79, 70, 229;

  --oz-ink: #0f172a;
  --oz-ink-soft: #1e293b;
  --oz-muted: #64748b;
  --oz-muted-2: #94a3b8;

  --oz-surface: #ffffff;
  --oz-surface-2: #f8fafc;
  --oz-page-bg: #f4f6fa;
  --oz-border: #e2e8f0;
  --oz-border-soft: #edf2f7;
  --oz-border-strong: #cbd5e1;

  --oz-success: #16a34a;
  --oz-success-soft: #f0fdf4;
  --oz-success-rgb: 22, 163, 74;
  --oz-warning: #d97706;
  --oz-warning-soft: #fffbeb;
  --oz-warning-rgb: 217, 119, 6;
  --oz-danger: #dc2626;
  --oz-danger-soft: #fef2f2;
  --oz-danger-rgb: 220, 38, 38;
  --oz-info: #2563eb;
  --oz-info-soft: #eff6ff;
  --oz-info-rgb: 37, 99, 235;

  /* Tasarımdaki açık-tema degradeleri token'a alındı, koyu temada düz tonlara döner */
  --oz-stat-bg: radial-gradient(120% 90% at 95% 10%, #fafcff 0%, #ffffff 100%);
  --oz-th-bg: linear-gradient(180deg, #fbfcfe 0%, #f1f5f9 100%);
  --oz-track: #eef2f6;
  --oz-search-hover-bg: #ffffff;
  --oz-pill-info-bg: linear-gradient(180deg, #eff6ff 0%, #e0edff 100%);
  --oz-pill-warning-bg: linear-gradient(180deg, #fffbeb 0%, #fef3c7 100%);
  --oz-pill-accent-bg: linear-gradient(180deg, #eef2ff 0%, #e0e7ff 100%);
  --oz-pill-success-bg: linear-gradient(180deg, #f0fdf4 0%, #dcfce7 100%);
  --oz-pill-danger-bg: linear-gradient(180deg, #fef2f2 0%, #fee2e2 100%);
  --oz-pill-neutral-bg: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
  --oz-inset-hl: rgba(255, 255, 255, 0.8);

  --oz-shadow: 0 10px 30px -4px rgba(15, 23, 42, 0.08), 0 4px 12px -2px rgba(15, 23, 42, 0.03);
  --oz-shadow-sm: 0 2px 8px -1px rgba(15, 23, 42, 0.05), 0 1px 3px 0 rgba(15, 23, 42, 0.03);
  --oz-radius: 14px;
  --oz-radius-sm: 9px;
}

.oz-root[data-theme="dark"] {
  --oz-accent: #818cf8;
  --oz-accent-hover: #a5b4fc;
  --oz-accent-strong: #6366f1;
  --oz-accent-soft: rgba(129, 140, 248, .16);
  --oz-accent-rgb: 129, 140, 248;

  --oz-ink: #eef1f8;
  --oz-ink-soft: #f5f6fb;
  --oz-muted: #9aa3ba;
  --oz-muted-2: #6b7488;

  --oz-surface: #17182a;
  --oz-surface-2: #1d1f33;
  --oz-page-bg: #0e0f1c;
  --oz-border: #2b2d45;
  --oz-border-soft: #262840;
  --oz-border-strong: #3a3d5c;

  --oz-success: #4ade80;
  --oz-success-soft: rgba(74, 222, 128, .12);
  --oz-success-rgb: 74, 222, 128;
  --oz-warning: #fbbf24;
  --oz-warning-soft: rgba(251, 191, 36, .12);
  --oz-warning-rgb: 251, 191, 36;
  --oz-danger: #f87171;
  --oz-danger-soft: rgba(248, 113, 113, .12);
  --oz-danger-rgb: 248, 113, 113;
  --oz-info: #60a5fa;
  --oz-info-soft: rgba(96, 165, 250, .12);
  --oz-info-rgb: 96, 165, 250;

  --oz-stat-bg: var(--oz-surface);
  --oz-th-bg: var(--oz-surface-2);
  --oz-track: #262840;
  --oz-search-hover-bg: var(--oz-surface);
  --oz-pill-info-bg: var(--oz-info-soft);
  --oz-pill-warning-bg: var(--oz-warning-soft);
  --oz-pill-accent-bg: var(--oz-accent-soft);
  --oz-pill-success-bg: var(--oz-success-soft);
  --oz-pill-danger-bg: var(--oz-danger-soft);
  --oz-pill-neutral-bg: var(--oz-surface-2);
  --oz-inset-hl: rgba(255, 255, 255, 0.04);

  --oz-shadow: 0 8px 28px rgba(0, 0, 0, .45);
  --oz-shadow-sm: 0 2px 10px rgba(0, 0, 0, .35);
}

/* Belge hiç kaymasın: app.css'teki html { overflow-x: hidden } body'nin overflow-hidden'ını
   geçersiz kılıyor ve body 100vh olduğundan mobilde (adres çubuğu açıkken) html kayabiliyordu;
   o zaman tüm iskelet yukarı çıkıp alt sekme çubuğunun altında boşluk kalıyordu. */
html:has(.oz-root), html:has(.oz-root) body { overflow: hidden; overscroll-behavior: none; }
/* Inertia'nın NProgress çubuğu yerine OzLoadingBar kullanılır */
html:has(.oz-root) #nprogress { display: none; }

.oz-root {
  /* Görünen alana sabit: belge kaysa bile iskelet ve alt sekme çubuğu yerinde kalır.
     Kaydırma yalnızca .oz-shell-content içinde. */
  position: fixed;
  inset: 0;
  overflow: hidden;
  background: var(--oz-page-bg);
  color: var(--oz-ink);
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
  font-size: 13.5px;
  line-height: normal;
  -webkit-font-smoothing: antialiased;
}
.oz-root *, .oz-root *::before, .oz-root *::after { box-sizing: border-box; }
.oz-root button { font-family: inherit; }

.oz-root ::-webkit-scrollbar { width: 6px; height: 6px; }
.oz-root ::-webkit-scrollbar-track { background: transparent; }
.oz-root ::-webkit-scrollbar-thumb { background: var(--oz-border); border-radius: 4px; }
.oz-root ::-webkit-scrollbar-thumb:hover { background: var(--oz-muted-2); }

@media (prefers-reduced-motion: reduce) { .oz-root * { transition: none !important; } }
</style>

<style scoped>
.oz-shell {
  display: grid;
  grid-template-columns: 56px minmax(0, 1fr);
  grid-template-rows: 62px minmax(0, 1fr);
  gap: 12px;
  padding: 14px;
  height: 100%;
}
.oz-shell-top { grid-column: 1 / -1; grid-row: 1; min-width: 0; z-index: 20; }
.oz-shell-rail { grid-column: 1; grid-row: 2; min-height: 0; z-index: 15; }
/* isolation: sayfa içindeki z-index'ler (ör. 9999) kendi katmanında kalır, üst bar / rail menülerinin üstüne çıkamaz.
   body'ye teleport edilen modal/drawer'lar bundan etkilenmez.
   scrollbar-gutter: kaydırma çubuğunun yeri her zaman ayrılır; içerik uzayıp çubuk belirdiğinde sayfa sola kaymaz. */
.oz-shell-content { grid-column: 2; grid-row: 2; overflow-y: auto; scrollbar-gutter: stable; overscroll-behavior-y: contain; min-width: 0; padding-right: 2px; isolation: isolate; }
.oz-shell-mobile { display: none; }

@media (max-width: 768px) {
  .oz-shell {
    grid-template-columns: minmax(0, 1fr); grid-template-rows: auto minmax(0, 1fr) auto;
    padding: 10px 10px max(10px, env(safe-area-inset-bottom)); gap: 10px;
  }
  .oz-shell-top, .oz-shell-rail { display: none; }
  .oz-shell-mobile { display: contents; }
  .oz-shell-content { grid-column: 1; grid-row: 2; padding-right: 0; }
}
</style>
