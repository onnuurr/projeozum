---
name: vue-form-input
description: Use when adding or editing a form input, checkbox, textarea, OTP, or submit button inside a Vue/Inertia template in this project (resources/js/Pages/, resources/js/Components/, or Modules/*/Resources/assets/js/...). Codifies the FormField + FormInput component pair, useForm error wiring, slot-based icons, and the rule to NEVER use the legacy Components2/ Breeze inputs in new code.
---

# Vue Form Input — Proje Konvansiyonu

> **Yeni form bileşeni yazma; mevcutları kullan.** Proje **kendi form takımına**
> sahip: `resources/js/Components/Form/*`. Native `<input>` veya Breeze
> `Components2/` bileşenleri yeni kodda **kullanılmaz**.

## Kanonik form takımı

`resources/js/Components/Form/`

| Bileşen | Ne için | Önemli prop |
|---|---|---|
| `FormField` | label + error sarmalı (input bunun **slot'una** girer) | `label`, `error`, `for-id`, `link?`, `variant?` |
| `FormInput` | text/email/password/number/tarih girişi | `modelValue`, `type`, `id`, `placeholder`, `required`, `minlength`, `min`, `max`, `autocomplete`, `variant?` |
| `FormTextarea` | çok satır metin | `modelValue`, `placeholder`, `rows`, `minHeight`, `id`, `name` |
| `FormCheckbox` | checkbox / radio | `modelValue`, `label`, `type` (`'checkbox'\|'radio'`), `value` (radio için) |
| `FormOtpInput` | 6 haneli OTP | `modelValue`, `error`, `disabled` + `@complete` |
| `FormButton` | submit / aksiyon butonu | `type`, `variant`, `loading`, `disabled`, `size`, `pill`, `block` |
| `SocialButton` | Google / Microsoft sosyal giriş | `provider` |
| `CustomSelect` | aranabilir dropdown | `modelValue`, `options:[{value,label,dot?}]`, `placeholder`, `label`, `showLabel` |

Path'ler örnek: `resources/js/Components/Form/FormInput.vue`,
`resources/js/Components/Form/FormField.vue`.

## Kanonik kullanım: FormField sarar, içine input slot olarak girer

`resources/js/Pages/Auth/Login.vue` referans örneği:

```vue
<FormField
    label="E-posta adresi"
    for-id="email"
    :error="form.errors.email"
>
    <FormInput
        id="email"
        v-model="form.email"
        type="email"
        placeholder="ornek@firma.com"
        autocomplete="email"
        required
    >
        <template #icon>
            <!-- sol simge SVG -->
        </template>
    </FormInput>
</FormField>
```

Kritik:
- **`for-id` propu** `FormField`'a verilir, **`id` propu** `FormInput`'a verilir; ikisi **aynı string** olmalı.
- Hata `FormField.error` üzerinden geçer; `FormInput` hatayı kendisi göstermez.
- `useForm` Inertia helper'ından gelir: `form.errors.<alan>`.

## İkon ve trailing slot'ları

`FormInput` iki named slot sunar:
- `#icon` → girişin **solunda** dekoratif simge (e-posta, kilit vs.).
- `#trailing` → girişin **sağında** tıklanabilir aksiyon (parola göster/gizle butonu gibi). Login.vue'da göz simgesi ile parola toggle örneği var.

```vue
<FormInput v-model="form.password" :type="passwordFieldType" ...>
    <template #icon><!-- lock SVG --></template>
    <template #trailing>
        <button type="button" class="input-trail" @click="togglePasswordVisibility">
            <!-- eye / eye-slash SVG -->
        </button>
    </template>
</FormInput>
```

## Variant: `auth` vs default

`FormInput` ve `FormField`'da `variant` prop'u var, **varsayılan `"auth"`**.
- `variant="auth"` → giriş ekranı tarzı (`field`, `form-input` CSS sınıfları,
  ikon için 40px sol padding).
- Başka bir değer (ör. `variant="default"`) → `form-group` / `form-input-wrap`
  app-wide sınıfları.

Yeni form yazarken **çoğunlukla varsayılan `auth` yeterli**; ERP-içi tabloya
yakın formlar için `default` kullan, ardından gerçek görünümü dev'de test et.

## useForm ile bağlantı

```vue
<script setup>
import { useForm } from '@inertiajs/vue3';
import FormField from '@/Components/Form/FormField.vue';
import FormInput from '@/Components/Form/FormInput.vue';
import FormButton from '@/Components/Form/FormButton.vue';

const form = useForm({
    email: '',
    password: '',
});

const submit = () => {
    form.post(route('login'), {
        onSuccess: () => form.reset('password'),
        onError: () => form.reset('password'),
    });
};
</script>
```

- **Submit butonunda loading** mutlaka bağlanır: `:loading="form.processing"`.
- Şifre/OTP alanları başarısız submit'ten sonra `form.reset('password')` ile temizlenir.
- Toast geri bildirimi için: `useToast()` composable'ı
  (`@/composables/useToast.js`) — bkz. Login.vue submitForm.

## Checkbox

```vue
<FormCheckbox v-model="form.remember" id="remember" label="Beni hatırla" />
```

- `v-model` direkt; `Components2/Checkbox.vue`'daki `update:checked` deseni
  **legacy** — yeni kodda kullanılmaz.
- Radio grubu için `type="radio"` + `:value="..."` ver, aynı `v-model`'i
  bağla.

## Yapma listesi

- **Yapma:** Yeni form için `Components2/TextInput.vue` veya native `<input>` import etme.
- **Yapma:** `FormField` sarmadan `FormInput` koyma — label/error eksik kalır.
- **Yapma:** `defineModel()` ile yeni v-model deseni başlatma — proje genelinde
  computed getter/setter var, tutarlılığı koru.
- **Yapma:** Tailwind `dark:` prefixleri ekleme — proje dark mode kullanmıyor
  (tailwind config'te konfigüre değil).
- **Yapma:** `FormButton` varken `<button class="btn ...">` yazma.

## Checklist

- [ ] Import `@/Components/Form/...` (Components2 değil)
- [ ] `FormField` sarmalı: `label`, `for-id`, `:error` bağlı
- [ ] `FormInput.id` == `FormField.for-id`
- [ ] İkon `#icon`, sağdaki aksiyon `#trailing` slot'unda
- [ ] `useForm` ile `form.errors.<alan>` → `:error` yolu kurulu
- [ ] Submit butonu `:loading="form.processing"` ve uygun `variant`
- [ ] Hassas alan (`password`, `otp`) `onError`'da reset ediliyor
- [ ] Dark mode sınıfı yok, yeni CSS sınıfı eklenmedi
