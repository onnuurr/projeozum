<script setup>
// Başlıklı kart. Slots: default (gövde), actions (başlığın sağı), title (özel başlık)
defineProps({
  title: { type: String, default: '' },
  // Başlığın yanına "· ..." olarak eklenen açıklama
  subtitle: { type: String, default: '' },
  // Başlığın sağındaki küçük metin (ör. "9 kayıt"); actions slot'u verilirse onun yerine geçer
  meta: { type: String, default: '' },
  // Sağ üstte bağlantı metni; tıklanınca link-click yayar
  linkText: { type: String, default: '' },
})

const emit = defineEmits(['link-click'])
</script>

<template>
  <section class="oz-card">
    <div v-if="title || $slots.title || $slots.actions || meta || linkText" class="oz-card-head">
      <h2>
        <slot name="title">{{ title }}</slot>
        <span v-if="subtitle" class="sub"> · {{ subtitle }}</span>
      </h2>
      <slot name="actions">
        <a v-if="linkText" href="#" class="card-link" @click.prevent="emit('link-click')">{{ linkText }}</a>
        <span v-else-if="meta" class="meta">{{ meta }}</span>
      </slot>
    </div>
    <slot />
  </section>
</template>

<style scoped>
.oz-card {
  background: var(--oz-surface); border-radius: var(--oz-radius); box-shadow: var(--oz-shadow-sm);
  border: 1px solid var(--oz-border);
  overflow: hidden; overflow: clip; /* clip: kaydırma kabı oluşturmaz → içindeki tablo başlığı sayfaya yapışabilir (eski tarayıcıda hidden) */
}
.oz-card-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px 12px; padding: 16px 18px 12px; }
.oz-card-head h2 { min-width: 0; }
.oz-card-head h2 { margin: 0; font-size: 14.5px; font-weight: 700; color: var(--oz-ink-soft); }
.oz-card-head .sub { font-size: 11px; color: var(--oz-muted); font-weight: 500; }
.meta { font-size: 11px; color: var(--oz-muted); font-weight: 500; white-space: nowrap; }
.card-link { font-size: 11.5px; font-weight: 600; color: var(--oz-accent); text-decoration: none; cursor: pointer; white-space: nowrap; }
.card-link:hover { color: var(--oz-accent-hover); text-decoration: underline; }
</style>
