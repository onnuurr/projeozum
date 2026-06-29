---
name: vue-component
description: Use when creating or editing a generic (non-form, non-page) Vue 3 SFC component in this project (resources/js/Components/ or Modules/*/Resources/assets/js/Components/). Covers script setup, defineProps/defineEmits style, v-model via computed getter/setter, slot conventions, scoped vs global styles, and the rule that Components2/ is LEGACY (Breeze) — new components go under Components/.
---

# Vue Component — Proje Konvansiyonu

## Komponentin kapsamı

Bu skill **genel** komponentler için (kart, liste satırı, modal içeriği,
sidebar item, vs.). İki özel durum **ayrı skill'lerle**:
- Form girişleri → `vue-form-input`
- Inertia sayfası → `vue-inertia-page`

## Konum & klasör politikası

| Konum | Anlamı |
|---|---|
| `resources/js/Components/...` | **Aktif**, paylaşılan UI bileşenleri. **Yeni komponentler buraya.** |
| `resources/js/Components2/...` | **Legacy** Breeze scaffold. **Yeni kodda kullanılmaz**, sadece eski sayfalar kademeli olarak göç ediyor. Mevcut `Components2` dosyalarını "kopyalayıp yenisini yapma"; ya import yolunu `Components/`'a çevir ya da gerçekten genelleştirilmiş bir muadili `Components/` altında yarat. |
| `Modules/<Mod>/Resources/assets/js/Components/...` | Yalnızca o modüle özgü bileşen. Birden fazla modül kullanacaksa **`resources/js/Components/`'a taşı**. |

## SFC iskeleti

```vue
<script setup>
import { computed } from 'vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    title: { type: String, default: '' },
    selectable: { type: Boolean, default: false },
    modelValue: { type: [String, Number, null], default: null },
});

const emit = defineEmits(['update:modelValue', 'select']);

const selectedId = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});
</script>

<template>
    <div class="card">
        <header v-if="title || $slots.header" class="card-head">
            <slot name="header">{{ title }}</slot>
        </header>

        <slot />
    </div>
</template>
```

## Prop deklarasyonu

- **Object form** (`{ type, default, required, validator }`) — array form
  (`['title']`) kullanma.
- Her prop için **`type`** ve gerekirse **`default`** ver. Bool default
  her zaman `false`.
- Object / array default → factory: `default: () => ({})` / `default: () => []`.
- `required: true` yerine sane default tercih et; gerçekten zorunlu ise yaz.
- TypeScript runtime form (proje JS-ağırlıklı): yeni komponentte
  `lang="ts"` zorunlu değil; mevcut komponent TS ise koru.

## Emits

- `defineEmits(['update:modelValue', 'select', 'close'])` — string array.
- İsimler kebab-case, ama Vue otomatik camelCase'e map eder.
- Custom event yaparken `v-model:<name>` deseni gerekiyorsa
  `update:<name>` emit et.

## v-model deseni: **computed getter/setter**

Proje genelinde tutarlı: yeni `defineModel()` (Vue 3.4+) **kullanma**, ekip
şu an `computed({ get, set })` ile devam ediyor.

```js
const value = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});
```

Bkz: `resources/js/Components/Form/FormInput.vue`,
`resources/js/Components/CustomSelect.vue`.

## Slot'lar

- Varsayılan slot `<slot />`.
- Adlandırılmış slot: `<slot name="header" />`. Tüketici tarafında
  `<template #header>`.
- Adlandırılmış slot'un boş olup olmadığını **`$slots.header`** ile kontrol
  et (`useSlots()` script tarafında).
- Slot scope (`<slot :item="x" />`) — gerekiyorsa kullan; abuse etme.

## Stil: Tailwind + uygulama CSS sınıfları + scoped

Üç katman var, üçünü de gör:

1. **Tailwind utility'leri** — template'de doğrudan
   (`class="flex items-center gap-2"`). Çoğu zaman bu yeter.
2. **Uygulama CSS sınıfları** — `form-input`, `field`, `btn`, `btn-primary`,
   `card-head` gibi sınıflar global stil sayfalarında tanımlı (örn. Login.vue
   `<style>` bloğu, layout'ların globalleri). Bunlar **konvansiyondur**;
   yeni komponent uygun olanı **doğrudan kullanır** (yeni varyant açma).
3. **Scoped `<style>`** — komponente has yeni görsel davranış için.
   `<style scoped>` veya hatta `<style>` (Login.vue gibi büyük layout'larda).
   `@apply` **kullanılmaz**; Tailwind ile gerekiyorsa class doğrudan yazılır.

Yasaklar:
- `dark:` prefixleri yok (proje dark mode konfigüre değil).
- Yeni global CSS dosyası yaratma; gerekirse `resources/css/`'e mevcut bir
  yere ekle ve ekiple konuş.
- CSS-in-JS yok.

## İmport alias'ları

Komponent içinde de aynı:
```js
import IconChevron from '@/Components/Icons/IconChevron.vue';
import useToast from '@/composables/useToast.js';
```

Relative `../../../` kullanma.

## İkon kütüphanesi

- `lucide-vue-next` — tercih edilen.
- `@phosphor-icons/vue` — yedek.
- Inline SVG — özel tasarımda (auth ekranlarındaki gibi).

Yeni ikon kütüphanesi **ekleme**.

## Composable kullanımı

Toast / modal / drawer için yerel `ref` açmadan önce
`@/composables/use*`'a bak (vue-inertia-page skill'ine bak). Aynı state
birden fazla yerde gerekiyorsa composable, tek yerde gerekiyorsa local
`ref`.

## Ne zaman komponent açmalı, ne zaman partial?

- Bir markup bloğu **iki sayfada** veya **iki kez** kullanılıyorsa → komponent.
- Yalnız bir sayfada lokal kullanılıyorsa → aynı dosyada `template` parçası
  ya da private subcomponent açmaya gerek yok.

## Checklist

- [ ] Yeni dosya `resources/js/Components/` veya modülün `Components/` altında (Components2 değil)
- [ ] `<script setup>`; `defineProps` object form + tip + default
- [ ] `defineEmits` array; `update:modelValue` çıkış noktası net
- [ ] v-model `computed` getter/setter ile; `defineModel()` yok
- [ ] Slot: default + named (gerekiyorsa); empty check `$slots.x`
- [ ] Tailwind + mevcut app CSS sınıfları kullanıldı; `dark:` yok
- [ ] Import'lar `@/` veya `@Modules/` alias'ından
- [ ] `lucide-vue-next` veya mevcut ikon kütüphanesi; yeni kütüphane eklenmedi
- [ ] Aynı state'i ikinci komponentte istersem composable'a çıkacak ölçüde temiz
