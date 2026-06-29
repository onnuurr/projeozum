---
name: vue-inertia-page
description: Use when creating or editing an Inertia page component in this project (resources/js/Pages/ or Modules/*/Resources/assets/js/Pages/). Covers layout pick (AuthenticatedLayout/GuestLayout/AppLayout), defineProps for controller-passed data, useForm pattern, composables (useToast/useModal/useDrawer), and the @/ + @Modules import aliases.
---

# Vue Inertia Page — Proje Konvansiyonu

## Konum

| Sayfa türü | Yer |
|---|---|
| App-level (auth, profile, dashboard) | `resources/js/Pages/<Section>/<Page>.vue` |
| Modül sayfası | `Modules/<Mod>/Resources/assets/js/Pages/<Page>.vue` |

`app.js` modül sayfalarını `Module::Page` rota ismi ile çözer
(Inertia render: `'Tenant::Index'`). Controller'da
`return Inertia::render('Tenant::Index', [...])`.

## İskelet

```vue
<script setup>
import { computed, ref } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useToast } from '@/composables/useToast.js';

defineOptions({ layout: AuthenticatedLayout });

const props = defineProps({
    tenants: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const { showToast } = useToast();

const form = useForm({ q: props.filters.q ?? '' });
</script>

<template>
    <Head title="Tenants" />

    <template #header>
        <h2>Tenants</h2>
    </template>

    <div class="page-content">
        <!-- içerik -->
    </div>
</template>
```

## Layout seçimi

| Layout | Kullanım |
|---|---|
| `@/Layouts/AuthenticatedLayout.vue` | Giriş yapmış kullanıcı sayfaları (sidebar/nav var). `#header` slot'u alır. |
| `@/Layouts/GuestLayout.vue` | Auth ekranları (login/register/forgot). Ortalanmış kapsayıcı. |
| `@/Layouts/AppLayout.vue` | Belirli app modüllerinde (kontrol et: bazı modüller buna geçer). |

Layout iki yolla bağlanır:
1. **Tercih edilen**: `defineOptions({ layout: AuthenticatedLayout })`.
2. Sayfa içinde wrap: `<AuthenticatedLayout> ... </AuthenticatedLayout>`.

Kanonik referans: `resources/js/Pages/Auth/Login.vue` (custom layout — kendi
panelini render ediyor; nadir bir desen, tipik sayfalar bu kadar özelleşmez).

## `defineProps`

Controller'dan `Inertia::render(..., [...])` ile gelen veriler **prop**'a iner.
**Açık tip + varsayılan** ver:

```js
defineProps({
    tenant: { type: Object, required: true },
    canEdit: { type: Boolean, default: false },
    permissions: { type: Array, default: () => [] },
});
```

`v-if="props.x"` yerine `computed` ile türet; controller'ın gönderdiği şeyi
sayfa içinde mutasyona uğratma.

## Form: `useForm`

Form sayfası ise `vue-form-input` skill'i ile birlikte oku. Özet:

```js
const form = useForm({ name: '', email: '' });

form.post(route('tenants.store'), {
    onSuccess: () => showToast({ type: 'success', title: 'Kaydedildi' }),
    onError: () => form.reset('password'),
});
```

- `route()` ziggy/laravel-vite helper'ı global tanımlı; import gerekmez.
- `form.processing` → button loading.
- `form.recentlySuccessful` → "kaydedildi" göstergesi.

## Composable'lar

`resources/js/composables/`

| Composable | Ne yapar |
|---|---|
| `useToast.js` | `{ toasts, showToast({type,title,message,duration}), dismissToast(id) }` |
| `useModal.js` | `{ openModal(id), closeModal(id), isOpen(id) }` |
| `useDrawer.js` | `{ open, openDrawer(), closeDrawer() }` (tek drawer state) |
| `useMenu.ts` | Navigation/dropdown state (TS) |
| `useSearch.js` | Search state |

Sayfa için yeni `Pinia store` veya `provide/inject` açmadan **önce** mevcut
composable'a metot eklemeyi değerlendir.

## Import alias'ları

`vite.config.js`'de:
- `@` → `resources/js`
- `@Modules` → `Modules/`

Tüm importlar bunlardan biriyle başlar:
```js
import FormInput from '@/Components/Form/FormInput.vue';
import TenantCard from '@Modules/Tenant/Resources/assets/js/Components/TenantCard.vue';
```

Relative import (`../../../`) kullanma.

## Icon kütüphanesi

- `lucide-vue-next` (mevcut, tercih edilen, küçük bundle).
- `@phosphor-icons/vue` (mevcut ama az kullanılıyor).
- Inline SVG (auth ekranları gibi özel tasarımda).

Yeni icon kütüphanesi **ekleme**.

## Pinia ve state

`pinia` dependency'de var ama yaygın kullanılmıyor. Sayfa-içi state için
`ref/reactive` yeter. Çapraz-sayfa state için önce composable, sonra Pinia
düşün — gerekçesini PR'da açıkla.

## Test

Frontend testi (vitest/cypress/playwright) **şu an yok**. UI değişikliği
yaparsan tarayıcıda doğrulamak için projedeki `/run` veya `/verify` skill'ini
kullan; bu skill bu konuyu kapsamaz.

## Modül sayfası özellikleri

- Modül sayfasında controller `return Inertia::render('Tenant::Index', [...])`.
- Modül-altı bileşenler `@Modules/Tenant/Resources/assets/js/Components/...`'tan
  import edilebilir.
- Modüller arası bileşen paylaşımı **kötüdür**; ortak bileşeni
  `resources/js/Components/`'a taşı.

## Checklist

- [ ] Doğru konum (Pages/ veya Modules/.../Pages/)
- [ ] Layout `defineOptions({ layout: ... })` ile bağlandı
- [ ] `defineProps` ile type + default; controller'ın gönderdiğini açıkça karşıladım
- [ ] Form varsa `useForm` + `vue-form-input` deseni uygulandı
- [ ] Import'lar `@/` veya `@Modules/` alias'ından
- [ ] Toast/Modal/Drawer için mevcut composable kullanıldı
- [ ] Pinia store / yeni global state açmadan composable yetmediğini doğruladım
- [ ] `<Head title="...">` ile sayfa başlığı set edildi
