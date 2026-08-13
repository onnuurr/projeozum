---
name: admin-page-ui
description: Use when building or editing an admin/dashboard-style Inertia page (headers, stat tiles, status pills, empty states, buttons) in resources/js/ or Modules/*/Resources/assets/js/Pages/. Covers which shared Components/ to reach for per UI shape, PageHeader/EmptyState usage, the Button component convention, the lucide-icon-not-emoji rule, and the hex→token color rule.
---

# Admin Page UI — Görsel/Component Konvansiyonu

## Bu skill ile `vue-inertia-page`'in farkı

`vue-inertia-page` sayfa **iskeletini** kapsar: layout seçimi, `defineProps`,
`useForm`, composable'lar, import alias'ları. Bu skill ise aynı sayfanın
**görünümünü** kapsar: header nasıl kurulur, bir sayı/durum/liste hangi
paylaşılan component'le gösterilir, buton/renk/ikon konvansiyonu ne.
İkisi birbirini tamamlar — yeni bir admin sayfası açarken önce
`vue-inertia-page`'i, içeriği doldururken bu skill'i oku.

Bu envanter Superadmin modülünün 9 sayfası (`Modules/Superadmin/Resources/
assets/js/Pages/`) yeni component kit'ine geçirilirken çıkarıldı — aynı
`.page-header` deseni Tenant/Atelier/Finance/Creative/Product modüllerinde
de tekrarlanıyor (grep ile doğrulandı, 40+ sayfa), yani buradaki kurallar
Superadmin'e özel değil, genel bir "admin sayfası" sözleşmesi.

**Ürün seviyesinde onaylanmış tam standart:** `docs/page-layout-ui-standard.md`.
O doküman breadcrumb yerleşimini (zaten tutarlı, sadece kodlanıyor), page
header'ı, ve — bu skill'in ilk sürümünde eksik olan — **kaydet/iptal
butonunun iki kanonik yerleşimini** (modal footer vs. tam-sayfa "form
topbar") tanımlıyor. Herhangi bir modülde form/kaydet akışı içeren bir
sayfa açıyorsan önce o dokümanın §3'ünü oku.

## Page header

Ham `<div class="page-header">...<h1 class="page-title">...` kopyalama —
`resources/js/Components/PageHeader.vue` kullan:

```vue
<PageHeader title="Kullanıcılar" subtitle="X kullanıcı kayıtlı">
  <template #actions>
    <Button variant="primary" @click="openNew">Yeni Kullanıcı</Button>
  </template>
</PageHeader>
```

- `badge` prop (string) **opsiyonel** — sadece sayfanın gerçek bir kimlik/
  bağlam rozetine ihtiyacı varsa ekle (ör. "Süper Admin"). Daha önce
  badge'i olmayan bir sayfaya "tutarlılık için" badge ekleme; bu bilinçli
  bir tasarım kararı, otomatik davranış değil.
- `subtitle` düz string yeterli değilse (`<code>`, `<strong>`, dinamik
  sayaç gibi zengin içerik) `#subtitle` slot'unu kullan — prop yerine
  slot geçersiz kılar.
- Sağ taraftaki buton/durum alanı için tek slot adı `#actions` — eski
  sayfalarda görülen `.header-meta` gibi isim farklarını tekrar etme.

## Kaydet / İptal butonu yerleşimi

`docs/page-layout-ui-standard.md`'nin §3'ünde tanımlı, iki sanctioned şekil —
başka bir yerleşim **icat etme**:

1. **Modal içi CRUD** (liste sayfasında "+ Yeni"/"Düzenle" modalı): `AppModal`'ın
   `#footer` slot'u — İptal solda (`variant="ghost"`), Kaydet sağda
   (`variant="primary"`).
2. **Tam-sayfa form** (kendi route'u olan, uzun/çok bölümlü form — `ProductForm.vue`
   örneği): sayfanın kendi sticky "form topbar"ı, sağ üstte İptal + Kaydet.
   Liste-sayfası `PageHeader`'ından farklı bir desen — badge yok, genelde bir
   geri-oku var; `PageHeader.vue` henüz bunu üretmiyor (`backHref`/`sticky`
   prop'u yok), o yüzden bu durumda component'i zorlama, sayfanın kendi
   topbar'ını (mevcut `ProductForm.vue`'daki gibi) kullan.

Sayfa-içi form kartının altına gömülü bir Kaydet butonu (header'dan bağımsız)
veya header-actions içine "gizlice" bir kaydet işlevi koymak yeni kodda
**kullanılmaz**.

## "Hangi UI şekli → hangi component" tablosu

| Şekil | Component | Not |
|---|---|---|
| Sayı kutusu (icon + değer + başlık) | `StatWidget` | `icon` (lucide component) + `value` + `title` + `color` zorunlu; ikincil "hint" metni için slot **yok** — zengin içerik gerekiyorsa (Badge/ProgressBar barındıran kart) bunun yerine `Card` kullan |
| Canlı/servis durumu (yeşil nokta + etiket) | `StatusIndicator` | `status` önceden tanımlı bir enum (`online`/`busy`/`error`/...), serbest metin değil |
| Yüzde/ilerleme çubuğu | `ProgressBar` | `:value` + `:color` (`success`/`warning`/`danger`) |
| Durum etiketi/pill | `Badge` | `color` + `variant` (`filled`/`tonal`/`outlined`) |
| Panel/bölüm kapsayıcı | `Card` | `title` + `#actions` slot + `body-class` |
| Aksiyon kutusu (tıklanabilir kart) | `QuickActionWidget` | kendisi `<button>` — link davranışı için `@click="router.visit(href)"` (attrs fallthrough ile root butona bağlanır), `<Link>` ile sarmalama |
| Client-side sayfalanan tablo | `DataTable` | **sadece client-side pagination** — bkz. "Bilinen sınırlamalar" |
| Boş/veri-yok durumu | `EmptyState` | `icon` + `title` + `hint`; zengin içerik (ör. `<code>` içeren ipucu) için default slot |
| Herhangi bir tıklanabilir `<button>` | `Button` | ham `.btn btn-primary btn-sm` class string'i **yazma** |

## Buton konvansiyonu

`resources/js/Components/Button.vue` bu modülün kanonik seçimi (aynı işi
yapan `Components/Form/FormButton.vue` var ama form-context'e özel isim
taşıyor ve app genelinde ikisi de az kullanılıyordu — tek component'e
sadeleştirmek için `Button.vue` seçildi):

```vue
<Button variant="primary" size="sm" :loading="form.processing" :disabled="!form.isDirty" @click="save">
  Kaydet
</Button>
```

- `variant`: `primary`/`secondary`/`ghost`/`danger`/`success`/`warning`/`info`
  (`app.css`'teki global `.btn-*` sınıflarıyla birebir eşleşir).
- `loading` dahili spinner'ı gösterir (`.btn-spinner`, global CSS'te tanımlı)
  — sayfa bazlı "Kaydediliyor…" gibi metin-değiştirme mantığı **kurma**,
  `loading` prop'u yeterli; buton metni sabit kalabilir. Bu, sadece
  Superadmin'in değil (denetimde Tenant/Atelier/Finance/Creative/Product'ın
  hemen hepsinin hâlâ metin-değiştirme veya sadece `:disabled` kullandığı
  görüldü) — `docs/page-layout-ui-standard.md`'nin resmen kararlaştırdığı
  **tüm modüller için geçerli** standart budur.
- `#leading`/`#trailing` slot + `with-icon` prop ikonlu butonlar için.
- **İstisna:** `<Link>` bir butona benziyorsa (`class="btn ..."` ile
  stillenmiş navigasyon linki) `Button`'a zorlama — `Button`'ın href/link
  modu yok, `<Link class="btn ...">` olarak kalmalı.
- Segmented control / toggle-group gibi buton-olmayan özel etkileşim
  şekillerini (ör. görünüm değiştirici, kopyala-butonu varyantları)
  `Button`'a zorlamak yerine bilinçli olarak özel bırakmak da geçerli bir
  karar — her `<button>` mutlaka `Button` component'i olmak zorunda değil,
  ama sıradan bir tıklama-aksiyonu butonu her zaman öyle olmalı.

## İkonlar

Sadece `lucide-vue-next`, **emoji yok**. Superadmin geçişinde bulunan
gerçek örnekler:

| Emoji | lucide karşılığı |
|---|---|
| ✎ | `Pencil` |
| ＋ | `Plus` |
| 🗑 / 🗑️ | `Trash2` |
| ⋮⋮ | `GripVertical` |
| ✏️ | `Pencil` |
| ⏸️ | `Pause` |
| ▶️ | `Play` |

Named import + `<ComponentName :size="14" />` şeklinde kullan; string
icon-name registry'sine (`UiIcon`) yeni bağımlılık **ekleme** — bu proje
zaten `lucide-vue-next`'i doğrudan import ederek kullanıyor.

## Renk token kuralı

`<style scoped>` içinde ham hex **yazma** — `resources/css/app.css`'teki
token'lara bağlan (`rgb(var(--color-x))`, opaklık için
`rgb(var(--color-x) / .N)`):

| Ham hex | Token |
|---|---|
| `#1a1a2e` | `rgb(var(--color-ink))` |
| `#888`, `#6b7280` | `rgb(var(--color-muted))` |
| `#e5e7eb`, `#e8e8f0`, `#ebebf0`, `#f0f0f5` | `rgb(var(--color-border))` |
| `#fafafe`, `#f5f5f8`, `#f5f5fa`, `#f5f5fb` | `rgb(var(--color-bg))` (genelde `/ .5` ile) |
| `#16a34a` | `rgb(var(--color-success))` |
| `#dc2626` | `rgb(var(--color-danger))` |
| `#f59e0b`, `#ca8a04` | `rgb(var(--color-warning))` |
| `#2563eb` | `rgb(var(--color-info))` |

**İstisna:** JS/canvas'a beslenen gerçek hex string'leri (`Chart.js`
config'leri, `lib/colorVariants.js`'in kendi tanımı, bir role/kategori
paleti dizisi gibi >6 farklı vurgu rengi gereken durumlar — token seti
sadece `primary/success/warning/danger/info/neutral` sunuyor) ve bilinçli
"koyu terminal/log paneli" gibi tasarım sisteminde karşılığı olmayan
tek-seferlik yüzeyler (ör. bir JSON/stack-trace görüntüleyicinin koyu
arka planı) bu kuralın dışında — zorla token'a bağlama.

## Bilinen sınırlamalar (düzeltilmeyecek, etrafından tasarla)

- **`DataTable` sadece client-side pagination.** Tüm `data` prop'unu alıp
  kendi içinde dilimliyor. Laravel'in server-side paginator'ından gelen
  (`links` dizili) bir sonuç varsa `DataTable`'a zorlama — ya backend'i
  tam veri gönderecek şekilde değiştir (genelde kötü fikir, büyük
  tablolarda) ya da mevcut hand-rolled `<table>` + basit bir
  `PaginationLinks` yardımcısıyla devam et.
- **`Breadcrumb`'ın `icon` prop'u sadece `'home'`'u özel işliyor.** Başka
  bir icon değeri sessizce yok sayılır — bunu "bug" sanıp düzeltmeye
  çalışma, bilinen bir sınırlama.

## Checklist

- [ ] `<Breadcrumb>` var ve 3 seviyeli desene uyuyor (Ana Sayfa → modül → sayfa) — kök/Dashboard sayfaları dahil (Portal hariç)
- [ ] Header `PageHeader` component'i ile kuruldu (badge sadece gerçekten gerekiyorsa) — tam-sayfa form ise yerine sticky form-topbar deseni (bkz. yukarı)
- [ ] Kaydet/İptal `docs/page-layout-ui-standard.md` §3'teki iki yerleşimden birinde (modal footer / form topbar), üçüncü bir yerleşim icat edilmedi
- [ ] Sayı/durum/ilerleme/panel/boş-durum için yukarıdaki tablodan doğru component seçildi
- [ ] Her sıradan `<button>` `Button` component'i (Link-as-button istisnası hariç), kaydet butonu `:loading` prop'u kullanıyor (metin-değiştirme yok)
- [ ] Hiç emoji ikon yok, hepsi `lucide-vue-next`
- [ ] `<style scoped>`'da yeni eklenen hiçbir kural ham hex içermiyor (JS/chart istisnası hariç)
- [ ] Değişiklik `@vue/compiler-sfc` ile statik doğrulandı (`vite build`/`dev` bu repoda **yasak** — `public/` canlı docroot)
