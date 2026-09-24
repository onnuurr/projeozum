<script setup>
import OzIcon from './OzIcon.vue'

// Ayar satırı: solda ikon + başlık + açıklama, sağda kontrol (OzSwitch, OzButton, OzSelect…).
//   <OzSettingRow icon="shield" title="İki adımlı doğrulama" description="…"><OzSwitch v-model="x" /></OzSettingRow>
// Slot'un altına açılan ek içerik için #details (ör. anahtar açıkken görünen URL alanı).
defineProps({
  icon: { type: String, default: '' },
  title: { type: String, required: true },
  description: { type: String, default: '' },
  // danger: yıkıcı işlemler (tüm oturumları kapat, önbelleği temizle)
  tone: { type: String, default: 'neutral', validator: (v) => ['neutral', 'danger'].includes(v) },
})
</script>

<template>
  <div class="oz-setting-row" :class="`tone-${tone}`">
    <div class="main">
      <span v-if="icon" class="ico"><OzIcon :name="icon" :size="16" /></span>
      <div class="text">
        <div class="title">{{ title }}</div>
        <div v-if="description" class="desc">{{ description }}</div>
      </div>
      <div v-if="$slots.default" class="control"><slot /></div>
    </div>
    <div v-if="$slots.details" class="details"><slot name="details" /></div>
  </div>
</template>

<style scoped>
.oz-setting-row { border: 1px solid var(--oz-border); border-radius: 12px; background: var(--oz-surface); }
.main { display: flex; align-items: center; gap: 12px; padding: 12px 14px; }
.ico {
  width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  background: var(--oz-surface-2); color: var(--oz-muted); border: 1px solid var(--oz-border-soft);
}
.tone-danger .ico { background: var(--oz-danger-soft); color: var(--oz-danger); border-color: rgba(var(--oz-danger-rgb), .2); }
.text { flex: 1 1 180px; min-width: 0; }
.title { font-size: 13px; font-weight: 600; color: var(--oz-ink-soft); }
.desc { font-size: 11.5px; color: var(--oz-muted); margin-top: 2px; line-height: 1.45; }
.control { flex-shrink: 0; margin-left: auto; display: flex; align-items: center; gap: 8px; }
.details { padding: 0 14px 14px 60px; }

/* Dar ekranda: anahtar gibi küçük kontroller başlığın sağında kalır,
   geniş olanlar (select, buton) sığmazsa alt satıra sağa yaslı iner */
@media (max-width: 520px) {
  .main { flex-wrap: wrap; row-gap: 10px; }
  .text { flex-basis: 150px; }
  .details { padding-left: 14px; }
}
</style>
