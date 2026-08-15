# SaasKit — saas-frontend'den bu projeye "yeni versiyon" incelemesi (2026-08-14)

Kaynak: `git@github.com:onnuurr/saas-frontend.git`, yerel klon `/home/ozumserver/htdocs/saas-frontend`
(10 commit `origin/main`'in önünde, `feat(ui-kit): Faz 1-4` serisi + `docs/design-system/PORTING_GUIDE.md`).

Bu klasör **tamamen paralel ve unwired**'dır — hiçbir sayfa buradan import etmiyor, mevcut
`resources/js/Components/**`'e dokunulmadı. Entegrasyon kararı ayrı bir konuşma.

## Neden "45 dosyanın hepsi" değil

İlk istek saas-frontend'in tüm `App.vue`/`app.css`/component kit'inin yeni bir versiyonunu
üretmekti. İnceleme sırasında ortaya çıktı ki bu kit'in büyük kısmı zaten **bu projeden**
geliyor ya da **bu projeye zaten portlanmış** (Direction 1: saas-frontend→buraya, Direction 2:
buradan→saas-frontend — iki yönlü geçmiş, bkz. proje hafızası). Kör bir "hepsini tekrar kopyala"
neredeyse tamamen kendi kendinin kopyasını üretecekti. Onun yerine:

1. **Gerçekten yabancı olan tek katman — `layout/*` — port edildi** (aşağıda).
2. **Geri kalan ~40 dosya tek tek diff'lendi**, sadece anlamlı fark bulunanlar aşağıda listelendi.

## 1. Yeni port edilen: `layout/` (bu projede daha önce hiç yoktu)

`SaasKit/layout/{AppLayout,TopBar,MainNav,ActionBar,SearchModal,AppLogo}.vue` — saas-frontend'in
kendi orijinal header/sidebar/topbar tasarımı (MD3 döneminden kalma, bu projeden hiç türememiş).
Mantık birebir taşındı, sadece:
- `<UiIcon>` artık `@/Components/UiIcon.vue`'dan açıkça import ediliyor (saas-frontend'de
  `app.js`'te global component — bu projede öyle bir mekanizma yok, sessizce kaybolurdu).
- `Offcanvas` bu projenin **mevcut** `Components/Offcanvas.vue`'sundan geliyor (Batch 2'de zaten
  taşınmıştı, API birebir uyumlu — `v-model`/`position`/`title`/`width` — yeniden yazılmadı).
- `AppLogo.vue`'nun SVG gradient'i saas-frontend'in turuncusundan bu projenin teal marka
  renklerine çevrildi (Tailwind class değil, ham SVG stop-color olduğu için elle).
- `@/lib/icons.js` (bu projede zaten var) saas-frontend'in `ui/icons.js`'iyle **byte-birebir
  aynı** çıktı — yeni bir icon dosyası gerekmedi.

Tümü `@vue/compiler-sfc` (parse+compileScript+compileTemplate) ile statik doğrulandı, hatasız.
Görsel doğrulama yapılmadı (bu repo'da vite dev/build çalıştırılmıyor — [[feedback_no_local_vite_build]]).

## 2. Token/CSS katmanı — yeni dosya gerekmedi

saas-frontend'in `.btn*`/`.form-*` CSS sözleşmesi **bu projenin `resources/css/app.css`'inden
birebir kopya** (kaynak dosyada "ozumserver.com.tr'den taşındı" yorumu var). Tek gerçek fark:

- **`.btn-outline-warning`** — saas-frontend'de var (Faz 4'te eklenmiş), bu projede yok.
  Canlı `app.css`'e eklenmedi (entegrasyon kararı bekliyor), sadece burada not düşülüyor.

## 3. Diff sonucu **tamamen aynı** (fark yok, aksiyon gerekmiyor)

`Chart.vue`, `FilterSection.vue`, `Offcanvas.vue`, `AccordionItem.vue`, `Alert.vue`, `Avatar.vue`,
`Badge.vue`, `Divider.vue`, `MaskedInput.vue`, `ProgressBar.vue`, `Skeleton.vue`, `Spinner.vue`,
`StarRating.vue`, `Tabs.vue`, `Tag.vue`, `FormTextarea.vue` (`ui/Textarea.vue`), `TimePicker.vue`,
`Toggle.vue`, `Tooltip.vue`, `StatusIndicator.vue` — 20 dosya, satır satır identik.

**Sadece import-path farkı olan (`@/components/x` → `@/Components/x`), davranış/görsel olarak
identik** — `BarChart`, `LineChart`, `PieChart`, `DoughnutChart`, `MiniChart`, `ChartCard`,
`Accordion`, `NotificationWidget`, `ProgressWidget`, `QuickActionWidget`, `UserProfileWidget`,
`MiniChartWidget`, `Button` (+birkaç kozmetik: saas-frontend'de ölü `is-loading` class'ı var, bu
projede kaldırılmış — düzeltme, kayıp değil).

## 4. Diff sonucu **gerçek fark var** (entegrasyon konuşulacaksa değerlendirilmeli)

| Component | saas-frontend | Bu proje | Yön |
|---|---|---|---|
| `DataTable.vue` | Arama kutusu dahili (`searchQuery`), `create` event, satır seçim daha basit | `rowKeyField`, `perPageOptions`, `emptyIcon/Title/Hint`, `#actions` slot kontrolü (`hasActions`) | İki yönlü — her ikisinde diğerinde olmayan özellik var, birleştirme gerektirir |
| `TablePagination.vue` | perPage default 10, ok ikonları yok | `UiIcon` ile ok butonları, perPage default 20 | Bu projeninki biraz daha gelişmiş |
| `Breadcrumbs.vue`/`Breadcrumb.vue` | `maxVisible` ile orta segmentleri "…" olarak toplama | Yok (ama 137 satır — muhtemelen icon/route entegrasyonu daha zengin, tam incelenmedi) | İki yönlü |
| `Modal.vue` | `closable`/`persistent`/`size="full"`, Escape+backdrop kapatma, `close` event | Daha sade API | saas-frontend'inki daha zengin |
| `FormField.vue` (ui) vs `Form/FormField.vue` | Tek amaçlı, `variant` yok | `variant="auth"` modu + `link` prop (login sayfası için) | Bu projeninki daha zengin (bilerek — Direction 2'de saas-frontend bu kısmı YAGNI diye atlamıştı) |
| `Checkbox.vue` vs `Form/FormCheckbox.vue` | `type="radio"` dahil, array-modelValue (checkbox grubu) desteği, `disabled` | Sadece tekil checkbox, radio/array yok | saas-frontend'inki daha zengin |
| `DatePicker.vue` | `time` (datetime), `min`/`max` sınırları, `clearable`, `error` prop (343 satır) | Daha temel (245 satır) | saas-frontend'inki daha zengin |
| `StatWidget.vue` | Standart ikon+değer, `iconColorClasses` | Hayalet dekoratif ikon + gradyan zemin + renkli chip (`newdashboard/dashboard` kaynaklı, [[project_admin_ui_theme_system]] ile ilgisiz ayrı bir kaynak) | **Bu projeninki zaten daha zengin** — tersine örnek |
| `Select.vue` vs `CustomSelect.vue` | — | — | Prop listesi pratikte aynı, incelenen kısımda anlamlı fark bulunmadı |

## 5. Sonraki adım (kullanıcı ile konuşulacak)

Entegrasyon istenirse en olası sıralama: (a) `.btn-outline-warning`'i canlı `app.css`'e ekle
(bedava, risksiz), (b) `layout/*`'ı gerçek bir sayfada/route'ta deneyip görsel onay al (bu
projede `npm run dev` YASAK — [[feedback_no_local_vite_build]], test için ya saas-frontend
klonunda ya da başka bir ortamda görülmeli), (c) tablo 4'teki iki-yönlü farklardan hangisinin
"kazanan" versiyon olacağına karar ver (muhtemelen özellik birleşimi, düz kopya değil).
