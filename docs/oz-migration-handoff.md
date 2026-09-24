# Oz kit geçişi · devir notu (2026-09-23)

Bu belge, ana projenin (ozumserver.com.tr) yeni Oz tasarım diline taşınmasında kalınan yeri ve işin nasıl
yapılacağını anlatır. Yeni oturum buradan devam etmeli.

---

## 1. Kısa durum

| Konu | Durum |
|---|---|
| Oz kit + uygulama kabuğu (tüm sayfalar) | ✅ Yayında |
| Superadmin · Dashboard (`/superadmin`) | ✅ Yayında |
| Superadmin · Sistem Ayarları (`/superadmin/settings`) | ✅ Yayında (kayıt sonrası "değişiklik var" hatası düzeltildi) |
| Superadmin · Menü Yönetimi (`/superadmin/menus`) | ✅ Yayında (harness testi + build 2026-09-23) → §6 |
| Superadmin · Mimari Doktor (`/superadmin/architecture-doctor`) | ✅ Yayında (2026-09-23; eskisi scratchpad `ArchitectureDoctor.old.vue` + git) |
| Superadmin · Yedeklemeler (`/superadmin/backups`) | ✅ Yayında (2026-09-23; eskisi scratchpad `Backups.old.vue` + git) |
| Superadmin · Kullanıcılar (`/superadmin/users`) | ✅ Yayında (2026-09-23; eskisi scratchpad `Users.old.vue` + git) |
| Superadmin · Sistem Logları (`/superadmin/logs` + `/logs/unlock`) | ✅ Yayında (2026-09-23; eskileri scratchpad `LogViewer.old.vue`, `LogAccessUnlock.old.vue` + git) |
| Creative · Ret Analiz Raporları (`/creative/review-reports` + `/{file}`) | ✅ Yayında (2026-09-23; ortak yardımcılar `Modules/Creative/Resources/assets/js/reviewReport.js`; eskileri scratchpad `CreativeReviewReports.old.vue`, `CreativeReviewReportShow.old.vue`, `ReviewReportSection.old.vue` + git) |
| Product · Kargo Firmaları / Markalar / Depolar / Stok | ✅ Onaylandı, sonra geriye dönük düzeltildi (eksik `.stats` satırı eklendi, §8.1) — **yeni build bekliyor** |
| Product · Siparişler (`/orders`) | 🟡 2026-09-23: kullanıcı onayıyla `statusCounts` arka ucu + `.stats` satırı + chip sayıları eklendi (§8.3), build/onay bekliyor |
| Product · Sipariş Detay (`/orders/{id}`) | 🟡 Kaynak taşındı + "Teslimat & Ödeme" kartı eklendi (§8.3), kullanıcı build/onay bekliyor |
| Product · Kategoriler (`/products/categories`) | 🟡 Kaynak taşındı (sayfa + 4 bağımlı drawer/modal) + `.stats` grid CSS standarda çekildi, kullanıcı build/onay bekliyor → §8 |
| Product · Ürün Kataloğu (`/products`) | 🟡 Kaynak taşındı (sayfa + paylaşılan `MarketplaceListingDrawer.vue`), kullanıcı build/onay bekliyor → §8.2 |
| Product · kalanlar (ProductCard, ProductDetail, ProductForm) | ⏳ Sırada |
| Atelier · 7 sayfa (Panel, İş Emirleri + Detay, Hammaddeler, Reçeteler, Operasyonlar, Fasoncular) | 🟡 2026-09-23 yeniden inşa edildi (§11), harness'te test edildi, kullanıcı build/onay bekliyor |
| Atölye · PatternEngine sayfaları (Sayısallaştırma, Kalıp Kütüphanesi + İzleyici, Konsept Stüdyosu, Atölye İşleri) | 🟡 2026-09-23 kullanıcı onayıyla yeniden inşa edildi (§11.1), harness'te test edildi, build/onay bekliyor |
| Ana sayfa · Genel Bakış (`/dashboard`) | 🟡 2026-09-23 gerçek veriyle yeniden yazıldı (§15), harness + feature test, build/onay bekliyor |
| Kalan Superadmin sayfaları | ⏳ Sırada: Başarısız İşler, Ret Seçim Maddeleri |
| Diğer modüller | ⏳ Planlı taşınacak (kullanıcı sırayı belirler) |

Hiçbir şey commit edilmedi. Build her adımda `npm run build` ile canlıya alındı (`public/build`).

---

## 2. İki proje

| | saas (referans) | ana proje (hedef) |
|---|---|---|
| Yol | `/home/ozumserver-saas/htdocs/saas.ozumserver.com.tr/saas-frontend` | `/home/ozumserver/htdocs/ozumserver.com.tr` |
| Yönlendirme | vue-router | **Inertia** (`@inertiajs/vue3`), nwidart modülleri |
| Oz kit | `resources/js/components/oz` | `resources/js/Components/Oz` (büyük harf) |
| Menü | localStorage (`ozNavConfig.js`) | **DB** (`superadmin_menus`, `page.props.menu`, `MenuTreeBuilder`) |

saas, bileşen ve sayfa desenlerinin **referansıdır**. Ana projede eksik bir şey varsa önce saas'a bakılır.

---

## 3. Kullanıcının kuralları (mutlaka uy)

1. **saas'taki bileşenleri ve desenleri kullan, yeni bileşen/yeni gezinme icat etme.** Eksik varsa saas'tan
   taşı. (Başlığa alt menü koymak reddedildi ve geri alındı.)
2. **Ana proje için istenen değişiklik yalnızca ana projeye uygulanır**; saas'a yansıtılmaz (açıkça
   istenmedikçe). İki kit artık ayrışan kopyalardır.
3. Zevke bağlı tasarım kararlarında (ör. mobil düzen) önce 2-3 seçeneği ASCII önizlemeyle sor.
4. Mobil: uygulama gibi — alt sekme çubuğu, başlıkta alt sayfa çipleri, sıkışık tek satır yok.
5. Kullanıcının commit edilmemiş işlerine **dokunma**: `Modules/PatternEngine/python/**` (2026-09-23'te kullanıcı isteğiyle PatternEngine'in 7 sayfası Oz'a taşındı, §11.1/§14), `resources/js/Components/{AppDrawer,AppModal,Tabs,ToastContainer,Tooltip,HintIcon}.vue`,
   `resources/js/Components/Form/*`, `resources/js/app.js`, `.claude/skills/vue-form-input`.
6. Yeni dosyaları `chown ozumserver:ozumserver` yap.
7. Yanıtlar Türkçe.
8. **Oz kit bileşenleri değiştirilmez** (`resources/js/Components/Oz/*`, `ozMenuIcons.js`,
   `lucideIcons.js`, `useOzTheme.js` olduğu gibi tüketilir). Sayfa taşımada eksik
   desen varsa kit'e yeni bileşen eklenmez; sayfa mevcut kit + `var(--oz-*)`
   token'larıyla çözülür.

---

## 4. Ana projedeki Oz mimarisi

- **Kabuk:** `resources/js/Layouts/AppLayout.vue` → `OzAppLayout.vue`'yu sarar. 65 sayfa
  `defineOptions({ layout: AppLayout })` ile otomatik Oz'da. Eski kabuk: `AppLayoutClassic.vue`
  (geri dönüş = tek import satırı).
- **`Layouts/OzAppLayout.vue`** `useAppShell()` üzerine kurulu (veri ve sayfa sözleşmeleri aynı):
  - `page.props.menu` (DB ağacı) → kökler: sol şerit (rail) + mobil alt çubuk (ilk 4); kökün alt menüleri:
    rail paneli (`OzRailFlyout`), `OzModuleTabs`, mobil çipler, Ctrl K paleti; 3. seviye: panelde girintili.
  - İkon adı "settings" olan kökler şeridin altına sabitlenir.
  - `provide('ozNav', { module, child })` → `OzModuleTabs` buradan okur.
  - `provide('showToast' | '$swal' | 'drawer')` korunur; eski `useToast` kuyruğu Oz toast'a köprülenir.
  - Inertia `router.on('start'/'finish')` → `OzLoadingBar`; NProgress Oz kabuğunda CSS ile gizli.
  - **Sayfa geçiş animasyonu (2026-09-23):** `<slot />` bir `<div :key="page.component" class="oz-page">`
    içinde; saas'taki `.page-enter` deseni, kullanıcı isteğiyle daha belirgin (450ms, opaklık + 16px yukarı,
    `cubic-bezier(.25,.7,.3,1)` — saas eğrisi çok önden yüklüydü; `--oz-page-duration` / `--oz-page-shift` ile ayarlanır),
    yalnızca giriş (çıkış beklenmez), `prefers-reduced-motion`'da kapalı. Anahtar bileşen adı → aynı sayfadaki
    filtre/sayfalama ziyaretleri yeniden kurulmaz/canlanmaz. saas'ta kabuk sayfanın içinde olduğundan orada tüm
    ekran canlanıyor; burada yalnızca içerik.
  - Kullanıcı adı "Onur K." biçiminde; tema düğmesi kapalı (eski içerikler koyu temayı desteklemiyor).
- **Rol bazlı kullanıcı menüsü:** `app/Http/Middleware/HandleInertiaRequests.php` artık `auth.roles`
  (Spatie rol adları) paylaşıyor. `composables/useAppShell.js`: superadmin → Profilim + Süper Admin Paneli;
  tenant/tenant-user → Firma Profili + Ayarlar; diğer → Profilim. (+ Bildirimler, Kısayollar, Yardım, Çıkış)
- **`Components/PageHeader.vue`** (55 eski sayfanın başlığı) altına otomatik `OzModuleTabs` ekler
  (`display: contents` sarmalayıcı; `backHref`/`sticky` başlıklarda yok).
- **Menü ikonları = tüm Lucide seti:** `superadmin_menus.icon` artık Lucide kebab adı (ör. `truck`). `resources/js/lucideIcons.js`
  her ikonu `import.meta.glob` ile ayrı parça olarak tembel yükler (`lucideIcon(name)`, reaktif). Eski menuIcons.js
  anahtarları `LEGACY_MENU_ICONS` ile çevrilir; düzenleme panelinde açılınca Lucide adına dönüşür. Eski kabuk
  (`AppLayoutClassic`, `renderMenuIcon`) Lucide adlarını çizmez. `OzIconPicker`'a `resolve` + `pageSize` (vueuse `useInfiniteScroll` ile kaydırdıkça yükleme) eklendi.
- **`resources/js/ozMenuIcons.js`**: DB ikon anahtarları (menuIcons.js) → Oz ikon adları. Layout ve Menü
  sayfası bunu kullanır. `menuIconOptions` → `OzIconPicker :options`.
- **Kitte yalnızca ana projede olan farklar:** `OzTopbar` (36px eşit yükseklik, userMenu divider/heading/active,
  `notifications-open` olayı), `OzShell` (içerik `isolation: isolate` — sayfadaki z-index 9999'lar menüleri örtmesin; `scrollbar-gutter: stable` — kaydırma çubuğu belirince sayfa sola kaymasın, 2026-09-23),
  `OzRail` (gizli kaydırma çubuğu, kenar solması, sabit tooltip; butonlar yer daralınca 38→30px küçülür, birkaç px taşmada tekerlekle şerit titremesin — 2026-09-23), `OzRailFlyout` (3. seviye, ikonsuz öğe noktası; `@wheel` + `overscroll-behavior: contain` — panel üzerinde tekerlek arka sayfayı kaydırmaz, 2026-09-23),
  `OzAccountSheet` (divider/heading), `OzMobileNav` (chip kaydırma rect ile, `themeToggle` prop),
  `OzInput`/`OzCombobox`/`OzCommandPalette` (`@tailwindcss/forms` iç kenarlık/odak halkası sıfırlandı; `OzCommandPalette` ayrıca `.search`/`.foot` `flex-shrink: 0` + `.results` `min-height: 0` — liste uzunken arama satırı ~20px'e eziliyordu, 2026-09-23; yeni `idleHint` prop'u → boş aramada liste yerine ipucu, OzAppLayout kullanıyor),
  `OzIconPicker` (`options` + `clearable`), `OzBreadcrumbs`/`OzModuleTabs` (Inertia `Link`; `OzModuleTabs` alt çizgisi `box-shadow` + `overflow-y: hidden` — eski border/-1px margin 1px dikey taşma yapıp tekerlekle şeridi oynatıyordu, 2026-09-23), `OzTabs` (aynı düzeltme: underline varyantı `box-shadow`, `.list` `overflow-y: hidden`, 2026-09-23), `icons.js` (+workflow).
- Paketler: `@vueuse/core`, `motion-v`; `vite.config.js`'te `vendor-motion`, `vendor-vueuse` chunk'ları.
- Vitrin: `/oz-kit` (auth) → `resources/js/Pages/OzKit.vue`.

---

## 5. Bir sayfayı taşıma yöntemi (Dashboard/Settings böyle yapıldı)

1. **Oku:** eski sayfa + alt bileşenleri + controller (`Inertia::render` prop'ları, rotalar, request kuralları).
   İşlevi kaybetme; ölü/kaydedilmeyen alanları bildirip kaldırabilirsin.
2. **Sözleşmeyi koru:** prop adları, uç noktalar, `useForm` akışı, `inject('showToast')`, `useCan()` izinleri.
   Arka uca dokunma (gerekirse kullanıcıya sor).
3. **saas desenleri:** `OzPageHeader` + altında `<OzModuleTabs />`; kartlar `OzCard`; ayar satırı
   `OzSettingRow`; formlar `OzField` + `OzInput/OzSelect/OzCombobox/OzTextarea/OzSwitch/OzCheckbox/OzRadioGroup`;
   yan panel `OzDrawer`, pencere `OzModal`, onay `useOzConfirm()` ($swal yerine); sayılar `OzStatTile`,
   ilerleme `OzProgress`; animasyon `motion-v`. Çok bölümlü sayfalarda saas Ayarlar deseni: solda aranabilir bölüm
   menüsü + `?section=` (history.replaceState) + altta yapışkan kaydet çubuğu + kaydetmeden çıkış uyarısı
   (`router.on('before')` → `useOzConfirm`).
4. Import: `@/Components/Oz`, layout `defineOptions({ layout: AppLayout })`. Tab girinti (proje stili).
5. **Yerel test (harness)** → §7. Masaüstü 1440 + mobil 390, gerçek veriyle; konsol hatası olmamalı.
6. **Build + duman testi:** `cd /home/ozumserver/htdocs/ozumserver.com.tr && npm run build`
   ardından giriş sayfası kontrolü (site giriş ister; **kimlik bilgisi girilmez**).
7. Yeni dosyaları chown et; özetle ve sıradaki sayfayı sor.

---

## 6. ✅ Menü Yönetimi (tamamlandı)

Yazılan dosyalar (hepsi `Modules/Superadmin/Resources/assets/js/`):
- `Pages/Menus.vue` — yeni sayfa (eskisi: scratchpad'de `Menus.old.vue`, ayrıca git'te)
- `Components/MenuTree.vue` — Oz görünümlü özyinelemeli ağaç, **vuedraggable korunuyor** (seviyeler arası
  taşıma + katalogdan sürükleme), ⋯ menüsü: düzenle / alt menü ekle / aktif-pasif / yukarı-aşağı / sil
- `Components/RouteCatalog.vue` — Oz görünümlü katalog; sürükle ya da + ile ekle (`add` olayı)
- `Components/MenuItemDrawer.vue` — yeni: etiket, konum (parent, döngü engelli), rota (OzCombobox),
  URL, izin (OzCombobox), ikon (`OzIconPicker :options="menuIconOptions" clearable`), aktif
- `resources/js/ozMenuIcons.js` — yeni; `OzAppLayout` artık buradan ikon eşler
- `Components/Oz/OzIconPicker.vue` — `options` / `clearable` eklendi

Davranış: ekle/düzenle/sil/aktiflik **axios JSON** (`MenuController` `wantsJson` destekliyor), 422 hataları
panelde alan altına; ardından `router.reload({ only: ['menus','routes','menu'] })` (sol şerit de yenilenir).
Sıralama 600 ms debounce ile `superadmin.menus.reorder`'a arka planda; kart başında durum pill'i
(Kaydedilecek / Kaydediliyor / Kaydedildi / Kaydedilemedi). `route()` global (Ziggy) kullanılıyor.

**Test sonucu (2026-09-23):** ağaç, aç/kapa, ⋯ menüsü, yukarı/aşağı → pill + reorder, çekmece (istemci + 422 alan
hatası), aktif/pasif, sil onayı (tüm alt ağacı sayar), katalog + ve sürükle-bırak, mobil 390 — konsol hatasız.
Son düzeltmeler: rota/izin boşken "Seçin" yerine "Rota yok"/"Herkese açık"; alan hatasında toast gösterilmiyor
(panel altbilgisini örtüyordu); alt menü aralığı sıkılaştırıldı.

**Yapılanlar (arşiv):**
1. Harness'e `Superadmin::Menus` sayfasını ekle. Gerçek veri için salt okunur:
   `php artisan tinker --execute='echo json_encode(["menus"=>\Modules\Superadmin\Models\Menu::query()->ordered()->get(["id","parent_id","label","icon","route_name","url","permission","sort_order","is_active"]),"permissions"=>\Spatie\Permission\Models\Permission::orderBy("name")->get(["name","display_name"]),"routes"=>\Modules\Superadmin\Services\RouteCatalog::forMenu()], JSON_UNESCAPED_UNICODE);'`
   (RouteCatalog sınıfının namespace'ini `grep -rn "class RouteCatalog"` ile doğrula.)
   Harness'te `window.route = (name, id) => ...` Ziggy'yi taklit etmeli; axios put/post/delete stub'ları.
2. Kontrol et: ağaç çizimi, aç/kapa, ⋯ menüsü (dropdown tıklamadan önce scrollIntoView + bekle — yoksa
   kaydırma olayı menüyü kapatır), yukarı/aşağı → durum pill'i, çekmece (ikon seçici, konum, rota), sil onayı,
   katalog + ile ekleme, mobil 390.
3. `npm run build` + duman testi; chown kontrolü (`MenuTree.vue`, `RouteCatalog.vue`, `Menus.vue` root-owned
   olabilir — mevcut sahiplik korunur).
4. Kullanıcıya özet: eski "Geri Al / Sıralamayı Kaydet" düğmeleri kaldırıldı (otomatik kayıt zaten vardı),
   ⋯ menüsüyle sürüklemesiz taşıma eklendi, katalogda + düğmesi eklendi, konum (parent) seçimi eklendi.

---

## 7. Harness (girişsiz görsel test)

Site giriş istediği için sayfalar yerel bir Vite derlemesinde, gerçek veriyle `data-page` doldurularak test edilir.

- Dizin: `/tmp/claude-0/-home-ozumserver-saas-htdocs-saas-ozumserver-com-tr-saas-frontend/<session>/scratchpad/harness`
  (yeni oturumda scratchpad yolu değişir — yeniden kur):
  - `node_modules` → ana projenin `node_modules`'ına symlink
  - `vite.config.mjs`: `root` harness, alias `@`→`resources/js`, `@Modules`→`Modules`, `tailwindcss()` + `vue()`,
    `rollupOptions.input` = test html'leri
  - `harness.css`: `@import "<ana>/resources/css/app.css"; @source "<ana>/resources/js"; @source "<ana>/Modules";`
  - `main.js`: `createInertiaApp` + sayfa eşlemesi (şablonda `route()` için `app.config.globalProperties.route = window.route` — gerçek uygulamada ZiggyVue) (ör. `SuperSettings: () => import('@Modules/Superadmin/Resources/assets/js/Pages/Settings.vue')`),
    `app.component('UiIcon', UiIcon)`, `window.route` ve `window.axios` stub'ları
  - Her test için `<div id="app" data-page="{json}">`; props: sayfa verisi + `menu` (MenuTreeBuilder çıktısı) +
    `auth: { user, roles: ['superadmin'], permissions: [...] }` + `notifications`, `flash: null`, `errors: {}`
  - Gerçek veri: `php artisan tinker --execute='...'` (salt okunur). Menü:
    `\Modules\Superadmin\Services\MenuTreeBuilder::forUser(User)`; ayarlar: `SettingsController::buildSettingsPayload`
    (ReflectionMethod ile) + `defaultOptions()`; sistem: `SystemInfoService::staticPayload()`
- Derleme: `node <ana>/node_modules/vite/bin/vite.js build --config vite.config.mjs`
- Sunum: `cd dist && setsid python3 -m http.server 8765 --bind 127.0.0.1 >/dev/null 2>&1 < /dev/null &`
- Tarayıcı: playwright-core `/root/.npm/_npx/e41f203b7505f1fb/node_modules/playwright-core`,
  chromium `/root/.cache/ms-playwright/chromium-1234/chrome-linux64/chrome`
- Inertia POST taklidi: `page.route('**/url', r => r.fulfill({ headers: { 'X-Inertia': 'true', 'Content-Type': 'application/json' }, body: <page json> }))`

**Tuzaklar**
- `pkill -f "http[.]server 8765"`'i **tek başına** çalıştır; aynı komut satırında `http.server 8765` geçerse
  kendi kabuğunu öldürür (exit 144). `pgrep` de kendi satırını bulabilir.
- Python heredoc'larını `<<'EOF'` (tırnaklı) yaz; yoksa bash `${…}`'yi genişletir.
- Görünmez `<input type=checkbox>`'a değil görünür `.box` / `.track`'a tıkla.
- Oz sınıf adlarında çıplak Tailwind utility adı kullanma (`ring`, `underline`, `inline`…) → `is-*`.
- Ana projede `@tailwindcss/forms` (base) tüm input/textarea'ları stiller; Oz bileşenlerinde sıfırlandı,
  yeni bir ham `<input>` yazarsan aynı sorunu yaşarsın.
- Build bu sunucuda ~18 sn; bellek sorunu olursa `free -m` kontrol et.

---

## 8. 🟡 Product modülü (devam ediyor)

Taşıma sırası (kullanıcı onayladı, her sayfa sonrası dur): Carriers → Brands → Warehouses → Stocks →
Orders → OrderDetail → Categories → Products → ProductCard → ProductDetail → ProductForm.

**Yazılan sayfalar (2026-09-23), hepsi arka uca dokunmadan Oz kit'e taşındı:**
- `Carriers.vue`, `Brands.vue` — basit CRUD liste: `OzTable` (dahili arama), satır işlemleri
  `OzDropdown`, form `OzDrawer` + `OzField/OzInput/OzSwitch`.
- `Warehouses.vue` — kart grid korundu (tablo değil, istatistik yoğun); kartlar Oz token'larıyla
  yeniden derilendi, aksiyonlar `OzDropdown`, durum `OzPill`, boş durum `OzEmptyState`.
- `Stocks.vue` — sunucu taraflı filtre (depo + "sadece kritik") `OzTable :manual` ile; filtre
  kontrolleri kart başlığının `#actions` slotunda (`OzSelect` + `OzSwitch`).
- `Orders.vue` — durum chip'leri + filtre satırı Oz token'larıyla yeniden derilendi; tablo
  `OzTable :manual`; Laravel paginator (`orders.links`) için özel `.pagination` (OzPagination
  istemci taraflı sayfalama için, sunucu `links` dizisine uymuyor).

**Kalıp notları:**
- `canManage` iznine bağlı satır aksiyonları: tekli aksiyon → satırda doğrudan buton (Stocks);
  çoklu aksiyon (düzenle+sil) → `actions` sütunu + `OzDropdown` (Menus/Users'daki desenle aynı).
- "Silinemez, X bağlı kayıt var" gibi salt bilgilendirme diyalogları → `confirm.warning(title, msg,
  { cancelText: 'Kapat', confirmText: 'Tamam' })` (iki buton da çıkar, ikisi de kapatır — sorun değil).
- `OzIcon` kayıt defteri (`icons.js`) sınırlı; kayıtlı olmayan ikonlar (`MapPin`, `ArrowUpDown`…) için
  doğrudan `lucide-vue-next`'ten import edip şablonda kullanmak yeterli (Oz bunu engellemiyor).

**Categories.vue (2026-09-23) — sayfa + 4 bağımlı bileşen:**
Sayfanın açtığı 4 drawer/modal (`resources/js/Components/{MarketplaceConnectDrawer,
MarketplaceCategoryPickerModal,CategoryFormDrawer,CategoryAttributesModal}.vue`) yalnızca bu
sayfadan kullanılıyordu (tek kullanım yeri) → kullanıcı onayıyla hepsi Oz'a taşındı (aksi halde
sayfa Oz, açılan panel eski stil kalırdı). Hepsinde eski `v-model="x"` (AppDrawer/AppModal
`modelValue` deseni) → `v-model:open="x"` (`defineModel('open')`) oldu; çağıran Categories.vue'da
4 yerde bu değişti.
- Bulk seçim + "tümünü seç" checkbox'lı, sunucu değil istemci taraflı sayfalanan bir tablo
  `OzTable`'a **taşınmadı** — `OzTable`'ın `<th>`'lerinde header slot'u yok (checkbox
  enjekte edilemiyor) ve `visibleRows` dışarı sızmıyor ("tümünü seç" o sayfadakileri seçemezdi).
  Bunun yerine özel `<table>` Oz token'larıyla yeniden derilendi, sayfalama sadece
  `OzPagination` (`v-model` + `:total` + `:page-size`) ile değiştirildi.
- 3 seçenekli SweetAlert (Düzenle / Eşleştirmeyi Kaldır / Kapat) → `useOzConfirm` yalnızca
  onayla/vazgeç (2 buton) destekliyor; bunun için özel `OzModal` + 3 `OzButton` footer'ı yazıldı
  (`mpStatusOpen` state'i). 2 seçenekli "silinemez" / "eşleştir?" uyarıları `confirm.warning(...)`
  ve `confirm({...})` ile karşılandı.
- `OzModal`/`OzDrawer`'ın `#header` slot'u ikon+başlık bloğunun tamamını değiştiriyor (kapat
  butonu hâlâ otomatik çıkıyor) — marka renkli rozet (`marketplace.color`) gerektiren yerlerde
  (`MarketplaceConnectDrawer`, `MarketplaceCategoryPickerModal`) `icon` prop'u yerine bu slot
  kullanıldı.
- `OzInput`'un `revealable` prop'u (parola göster/gizle) var — elle yazılmış reveal-button +
  `revealed` reactive state'i (`MarketplaceConnectDrawer`'da) bu yüzden tamamen kaldırıldı.
- `OzDrawer` genişliği yalnızca `size: sm|md|lg` (max 680px); orijinal `width: 56vw` gibi
  keyfi genişlik desteklenmiyor — en geniş `size="lg"` ile yetinildi (görsel olarak biraz dar
  kalabilir, sorun çıkarsa `OzDrawer`'a özel `size` eklenmesi gerekir).

## 8.1 ⚠️ Düzeltme: "component değiştirmek" taşıma değildir

Kullanıcı geri bildirimi (2026-09-23): "sayfaları yeniden inşa et, görünümlerini standartlara göre
düzelt, sadece component değiştirmen yeterli değil." İlk turda Carriers/Brands/Warehouses/Stocks/
Orders/Categories, eski sayfanın DOM iskeleti korunarak (Card→OzCard, Button→OzButton gibi 1:1
component değişimi) taşınmıştı — ama zaten onaylı referans sayfalar (`Superadmin/{Dashboard,Users,
Menus}.vue`) hepsinde ortak bir yapısal kalıp var ve bunlarda eksikti: **`OzPageHeader →
OzModuleTabs → .stats (OzStatTile grid) → içerik`**. Eski sayfada stats yoksa bile (çünkü eski
tasarımda yoktu) bu satır Oz'a taşırken eklenmeli — yoksa sonuç "eski sayfa + yeni renkler" gibi
duruyor. Standart `.stats` CSS'i: `grid-template-columns: repeat(4, minmax(0,1fr)); gap: 12px;
margin-bottom: 14px;` + `@media(max-width:1040px){ repeat(2,...) }`.

Düzeltildi: Carriers/Brands/Warehouses/Stocks'a client-side hesaplanan `OzStatTile` satırı eklendi
(prop verisinden güvenle türetilebilen metrikler — ör. Carriers: toplam/aktif/pasif/sipariş-bağlı).
Categories.vue zaten stats'a sahipti, sadece grid CSS'i standarda çekildi (`auto-fit
minmax(200px,1fr)` → `repeat(4,...)`, margin 16px→14px). **Orders.vue'ya bilerek stats
eklenmedi** — sunucu taraflı paginasyon (Laravel paginator) var, backend `status` bazlı aggregate
count döndürmüyor; sayfadaki `orders.data`'dan (yalnızca o sayfanın 20 kaydı) "toplam" gibi
görünen sahte istatistik üretmek yanıltıcı olurdu. Gerçek stats için backend'e küçük bir
`groupBy('status')->count()` eklemek gerekir — **kullanıcıya sorulmadan yapılmadı** (arka uca
dokunma kuralı). Detay/tekil-kayıt sayfalarına (OrderDetail.vue gibi) bu kalıp **uygulanmadı** —
liste sayfalarına özgü, tekil kayıtta zaten kendi özet kartları (toplamlar) aynı işi görüyor.

Full genel kural (gelecek taşımalarda tekrarlanmasın diye) Claude'un otomatik hafızasında:
`feedback-oz-migration-rebuild-not-reskin`.

## 8.2 Products.vue (2026-09-23) — sayfa + paylaşılan drawer

`Modules/Product/Resources/assets/js/Components/MarketplaceListingDrawer.vue` de taşındı — bu
`Products.vue` **ve** henüz taşınmamış `ProductCard.vue` tarafından kullanılıyor (tek kullanım
yeri değil). Bu yüzden Categories'in 4 bileşenindeki gibi `v-model:open`'a **geçilmedi** — dış API
bilerek eskisi gibi bırakıldı (`open` prop + `@close` event), içeride `computed({get,set})` ile
`OzDrawer`'ın `v-model:open`'ına köprülendi. `ProductCard.vue`'yu değiştirmeden bu drawer'ın içi
Oz'a taşınmış oldu; `ProductCard.vue` taşınırken bu köprü kaldırılıp gerçek `v-model:open`'a
geçilebilir.
- Toplu seçim + "tümünü seç" + client-side filtre/sayfalama (Categories'teki gibi) yine özel
  `<table>` + `OzPagination` kalıbıyla yapıldı (aynı gerekçe: `OzTable`'da header slot yok).
- Filtre paneli (7 alan: arama+alan, sıralama+yön, kategori, marka, stok/fiyat/desi aralığı)
  `OzField`/`OzInput`/`OzSelect` ile yeniden derilendi; aç/kapa toggle bir `OzButton` (`is-open`
  class'ı `:deep()` ile stillendi — child component'in kök elemanına class fallthrough oluyor).
- Satır aksiyonları (Önizleme/Düzenle/Sil) tek `OzDropdown`'a toplandı (Users/Categories deseniyle
  aynı) — eskiden Önizleme ayrı bir görünür ikon linkti.
- Pazaryeri rozet listesi (logo + fiyat, üstüne tıklayınca `MarketplaceListingDrawer` açılıyor) ve
  hazırlık rozetleri (`Shirt/Palette/Store` ikonlu 3 nokta) bespoke bırakıldı — Oz kit'te karşılığı
  yok, yalnızca token'lar (`var(--oz-*)`) ile yeniden derilendi.

## 8.3 Siparişler tamamlama (2026-09-23)

§8.1'de ertelenen stats satırı kullanıcı onayıyla yapıldı:
- `OrderController@index` artık `statusCounts` (`{status: count}`) döndürüyor. Bayi/no/tarih
  filtreleri sayıma uygulanır, **durum filtresi uygulanmaz** (chip'ler her durumun sayısını
  gösterebilsin). Test: `tests/Feature/Product/AdminOrdersAuthTest::test_index_status_counts_ignore_status_filter`.
- `Orders.vue`: `.stats` satırı (Toplam / Onay Bekleyen / Kargoda / Teslim Edildi) + durum
  chip'lerinde sayı rozeti.
- `OrderDetail.vue`: yan sütuna "Teslimat & Ödeme" kartı — controller'ın zaten gönderip eski
  sayfanın göstermediği `order_type`, `payment_method`, `shipping_info` (kaynak, gönderim, adres)
  ve `note`. `shipping_info` serbest JSON (tek üretici: `BagistoOrderSyncService` →
  `{source, shipping_method, address}`); adres bilinen alanlardan okunuyor.
- Yalnızca statik doğrulama (`@vue/compiler-sfc`); build kullanıcıda. DB'de şu an hiç sipariş yok
  (boş durum görünür).

## 8.4 Toplu seçim çubuğu → yüzen çubuk (2026-09-23)

Kullanıcı geri bildirimi: satır seçilince tablonun üstünde beliren bulk-bar tabloyu aşağı itiyordu.
Kullanıcı "altta yüzen çubuk" seçeneğini seçti. `Products.vue` ve `Categories.vue`'da (özel
`<table>`'lı iki sayfa; `OzTable`'da checkbox yok) çubuk akıştan çıkarıldı: sayfanın sonunda
`.bulk-dock` (`.oz-shell-content` içinde `position: sticky; bottom: 0; height: 0`) + içinde
`absolute` ortalanmış hap biçimli `.bulk-bar`, `<Transition name="bulk">` ile alttan kayarak gelir.
Seçim varken dock 76px yükseklik alır ki sona kaydırınca son satırları/sayfalamayı örtmesin.
Mobilde içerik alanı alt sekme çubuğunun üstünde bittiği için çubuk otomatik olarak onun üstünde
durur. Kit'e bileşen eklenmedi (kural 8) — ileride başka sayfada toplu seçim olursa aynı CSS kopyalanır.

## 8.5 Filtre paneli animasyonu (2026-09-23)

`Products.vue` "Filtreler" paneli `v-show` yerine `@vueuse/motion` `useMotion` ile açılıp kapanır
(kullanıcı isteği: vueuse animasyonu; paket zaten `app.js`'te `MotionPlugin` ile kayıtlı, eski
`AppDrawer`/`AppModal` da aynı API'yi kullanıyor). `.filter-collapse` sarmalayıcı yükseklik+opaklık
(260ms easeOut), iç `.filter-panel` spring ile -10px→0 kayar; açılış bitince `height:auto` +
`overflow:visible` (yeniden boyutlanma/açılır menü kırpılmasın). Kapalıyken `inert` (Tab odağı
girmez), `useReducedMotion` açıksa animasyonsuz. Hızlı çift tıklama sıra sayacıyla korunuyor.
Scratchpad'de ayrı Vite derlemesiyle Chromium'da ölçüldü (public/'e dokunulmadı).

## 11. Atelier modülü (2026-09-23)

Kapsam: `Modules/Atelier/Resources/assets/js/Pages/{Dashboard,ProductionOrders,ProductionOrderDetail,
Materials,Boms,Operations,FasonSuppliers}.vue` + `Components/MaterialSpecsPanel.vue`. Eskileri scratchpad
`atelier-old/*.old.vue` + git. Arka uca ve DB'ye dokunulmadı (kullanıcı: "yalnızca Oz kit bileşenlerini uygula").
- İlk turda Atölye menüsündeki PatternEngine sayfaları kural 5 gereği dışarıda bırakılmıştı; kullanıcı ardından
  açıkça istedi → §11.1.
- **Gezinme:** DB'deki Atölye menüsü dağınık (ölü `?status=` girişleri, "Atelier Boms" gibi İngilizce etiketler,
  Hammaddeler/Operasyonlar yok). Kullanıcı menüye dokunulmasını istemedi. Bu yüzden `AtelierNav` kaldırıldı ve
  yerine kit'in `OzModuleTabs`'ı kondu; sekme listesi `Modules/Atelier/Resources/assets/js/atelierNav.js` →
  `provideAtelierNav(key)` ile sayfa içinden `provide('ozNav')` olarak veriliyor (layout'un provide'ını sayfa
  alt ağacında gölgeler). DB menüsü düzeltilince bu provide kaldırılabilir.
  **Bilinen eksik:** mobil alt sayfa çipleri (OzMobileNav) hâlâ DB menüsünden → mobilde Hammaddeler/Operasyonlar çiplerde yok.
  **Güncelleme 2026-09-24 (`8da1ead`):** DB Atölye menüsü kullanıcı kararıyla 10 sekmeyle birebir yeniden kuruldu
  (her öğe kendi yetkisiyle) → `provideAtelierNav`/`ATELIER_TABS` kaldırıldı; sekmeler ve mobil çipler artık DB
  menüsünden, yetkiye göre süzülmüş gelir. `atelierNav.js` yalnız biçimlendirme yardımcıları/durum sözlükleri tutar.
  Geri getirmeyin.
- Her liste sayfası `OzPageHeader → OzModuleTabs → .stats → OzCard(OzTable)` iskeletinde; satır aksiyonları
  `OzDropdown`, formlar `OzDrawer` + `OzField`, silme/planla/tamamla/iptal `useOzConfirm`.
- Başarı toast'ları kaldırıldı: tüm controller aksiyonları zaten `with('success')` flash gönderiyor ve kabuk
  bunu toast'a çeviriyor (eskiden çift bildirim). Hata toast'ları kaldı; plan/complete/cancel hataları
  (`withErrors(['plan'|'complete'|'cancel'])`) eskiden hiç gösterilmiyordu, artık toast.
- Eklenenler (arka uç zaten destekliyordu): hammadde/fasoncu `is_active` anahtarı, fasoncu satırında
  Aktif/Pasif geçişi, stok hareketinde Not alanı, iş emri sihirbazında Not + "Eşit Dağıt" + varyant toplamı
  kontrolü + tahmini malzeme maliyeti + reçetesiz ürün uyarısı, reçete panelinde "mevcut reçete pasife alınır"
  uyarısı, iş emri detayında fason adımının mevcut fasoncusunun seçili gelmesi (eskiden boş kalıyordu),
  Planla/Tamamla için onay diyalogu (Planla hammadde düşer).
- Harness: scratchpad `ah/` (gen.py sahte veri; DB'de Atölye verisi yok). 1440 + 390, konsol hatasız; sihirbaz
  (combobox → plan-preview tek istek → eşit dağıt 34/33/33), satır menüsü → stok hareketi, reçete paneli,
  planla onayı test edildi.

## 11.1 Atölye'nin PatternEngine sayfaları (2026-09-23)

Kullanıcı "kapsam dışı kalanları da değiştir" dedi. `Modules/PatternEngine/Resources/assets/js/Pages/`
altındaki `Conversions`, `Patterns`, `RasterTracer`, `ConceptStudio`, `Assignments` yeniden inşa edildi
(bu 5 dosyada commit edilmemiş değişiklik yoktu). **`PatternProfiles.vue` (kullanıcının WIP'i) ve
`PatternEngineJobs.vue` Atölye sekmesi değil, dokunulmadı.** Eskileri scratchpad `atelier-old/PE-*.old.vue` + git.
- Hepsi `provideAtelierNav(key)` (Atelier'in `atelierNav.js`'i) kullanıyor → `Components/AtelierNav.vue`
  artık hiçbir yerde kullanılmadığı için **silindi** (git'te + scratchpad `AtelierNav.old.vue`).
  *(2026-09-24: `provideAtelierNav` da kaldırıldı — §11 "Gezinme" güncellemesine bakın.)*
- Sayısallaştırma: yükleme `OzFileDrop` (çoklu, 50 MB), durum chip'leri sunucu filtresi, iş kartları token'larla,
  inceleme `OzModal lg` (PDF + ölçü tablosu), Reddet onaylı.
- Kalıp Kütüphanesi: 3 ayrı içe aktarma düğmesi → tek "İçe Aktar" `OzDropdown` (Link / PDF / Fotoğraf) + tek
  `OzDrawer`; filtreler kart araç çubuğunda; kart aksiyonları ⋯ menüsü; form dosyaları `OzFileDrop`
  (tekli alanlar computed köprüyle). İstatistikler sunucu filtresi uygulanmış liste üzerinden (filtre varken
  ilk kutu "Filtrelenen Kalıp" olur).
- Kalıp İzleyici: kalibrasyon tarayıcı `prompt()` yerine `OzModal`; araçlar `OzTabs segment`; parça/kaydet yan
  sütunda; sayfa görüntüsü yüklenemezse boş durum + tekrar dene (eskiden sonsuz "yükleniyor").
- Konsept Stüdyosu: akış seçimi + varyant sayısı `OzTabs segment`; kalıp `OzCombobox`; eşleme ve görsel büyütme
  `OzModal`; arşivleme onaylı.
- Atölye İşleri: kanban şeritleri token'larla; atama `OzDrawer` (kişi/kalıp/konsept `OzCombobox`), teslim
  `OzDrawer` + `OzFileDrop`, ret `OzModal`.
- Tüm `withErrors` hataları (onaylanamaz, yeniden denenemez, DXF üretilemedi…) eskiden sessizdi → hata toast'ı.
- Harness: `ah/` (gen.py'ye 6 sayfa eklendi; `serve.cjs` `/tracer-image` taklidi). 1440 + 390 konsol hatasız;
  kalibrasyon (200 px / 50 mm = 4,000 px/mm), parça bitirme, sayfa değişince sıfırlama, İçe Aktar menüsü,
  kalıp formu (etiket), inceleme penceresi + reddet onayı test edildi.

## 12. Creative Studio — sayfa içi CreativeNav kaldırıldı (2026-09-23)

Kullanıcı isteği: Creative sayfalarındaki açılır menü (`Components/CreativeNav.vue`; masaüstünde sekme çubuğu,
mobilde hamburger açılır menü) kaldırıldı — `PageHeader` altındaki `OzModuleTabs` ile çift gezinme oluyordu.
DB "Creative Studio" menüsünde Stüdyo ve İş Ortağı Profilleri olmadığı için Atölye'deki gibi
`Modules/Creative/Resources/assets/js/creativeNav.js` → `provideCreativeNav(key)` ile 9 sekme sayfa içinden
veriliyor. 9 sayfada (Studio, Gallery, Mannequins, Poses, Tryon, GarmentScans, Templates, BrandKits,
PartnerBrandKits) yalnızca `<CreativeNav>` satırı + import değişti; sayfalar henüz Oz'a taşınmadı (eski
`PageHeader`/`Breadcrumb` duruyor). `CreativeNav.vue` silindi (git + scratchpad `atelier-old/CreativeNav.old.vue`).
**Güncelleme 2026-09-24 (`8da1ead`):** DB "Creative Studio" menüsüne Stüdyo ve İş Ortağı Kreatif Profilleri
eklendi, her öğe yetkili → `creativeNav.js` ve `provideCreativeNav` çağrıları kaldırıldı; sekmeler DB menüsünden.
Mobilde alt sayfa çipleri hâlâ DB menüsünden → Stüdyo / İş Ortağı Profilleri mobil çiplerde yok.

## 13. Finans — sayfa içi FinanceNav kaldırıldı (2026-09-23)

Creative ile aynı istek: `Modules/Finance/Resources/assets/js/Components/FinanceNav.vue` (masaüstü sekme,
mobil açılır menü) 9 sayfadan kaldırıldı ve silindi (git + scratchpad `finance-old/FinanceNav.old.vue`). DB
"Finans" menüsü 8 sekmeyle birebir aynı olduğu için yerel liste gerekmedi; `PageHeader`'ın `OzModuleTabs`'ı
yeterli. Tek istisna `BankStatements.vue`: `/finance/bank-statements`, "Banka" (`/finance/bank-accounts`)
öneki altında olmadığı için layout "Genel Bakış"ı seçiyordu → sayfa layout'un `ozNav`'ını inject edip aynı
modülle, etkin sekmesi "Banka" olan yeni bir `ozNav` provide ediyor. Sayfalar henüz Oz'a taşınmadı.

## 14. PatternEngine — Kalıp Motoru + Profiller (2026-09-23)

Kullanıcı "Pattern Engine modülünü de değiştir" dedi. `PatternEngineJobs.vue` (Kalıp Motoru, `/pattern-engine`) ve
`PatternProfiles.vue` (`/pattern-engine/profiles`) yeniden inşa edildi. **`PatternProfiles.vue`'da kullanıcının
commit edilmemiş değişikliği vardı** (alan açıklamaları / HintIcon): yeni sayfa o içerikle kuruldu, tüm açıklamalar
korundu (genel alanlarda `OzField hint`, beden satırındaki 11 alanda `OzTooltip` "?" ikonu). Değişiklik öncesi hali
scratchpad `atelier-old/PE-PatternProfiles.WIP-current.vue`.
- Gezinme: `Modules/PatternEngine/Resources/assets/js/patternEngineNav.js` → `providePatternEngineNav(key)`;
  DB "Patternengine" menüsünde yalnızca İngilizce etiketli Profiles var; sekmeler yetkiye göre süzülür
  (`patternengine.manage` / `patternengine.profile.manage`).
  **Güncelleme 2026-09-24 (`8da1ead`):** DB menüsü "Kalıp Motoru" → "Kalıp Motoru İşleri" + "Kalıp Profilleri"
  (her biri kendi yetkisiyle; boş grup MenuTreeBuilder'da düşer) → `patternEngineNav.js` kaldırıldı.
- Kalıp Motoru: form sol sütunda (profil `OzCombobox`, ek bedenler `OzCombobox multiple` — eski checkbox listesi),
  AI öneri görseli `OzFileDrop`, güven rozetleri `OzPill`; son işler `OzTable` (durum filtresi); yer tutucu profil
  uyarısı `OzAlert`. **DXF indirme eskiden Inertia `Link` idi (XHR → indirme yerine hata penceresi) → düz `<a download>`.**
- Profiller: tablo + "Yeni Profil" `OzDrawer`; satır ⋯ "Beden Ekle / Güncelle" profili adıyla önceden doldurur
  (gönderim zaten MERGE). "Alan Rehberi" iki sayfada da `OzAccordion` ile katlanır.
- Harness `ah/` (pejobs, peprof) 1440 + 390 konsol hatasız.

## 15. Ana sayfa dashboard (2026-09-23)

`/dashboard` eskiden `Workflow.vue`'yu (sabit örnek verili vitrin: iş akışı panosu, buton vitrini, örnek form)
açıyordu. Artık gerçek veri:
- **Arka uç:** `app/Http/Controllers/HomeDashboardController.php` (invokable) + `app/Services/Dashboard/HomeDashboardService.php`.
  `app/` deptrac'ta tüm modüllere erişebilen tek katman olduğu için modüller arası özet burada (deptrac 0 ihlal).
  Bölümler yetkiye bağlı, yetki yoksa sorgulanmaz ve `null` döner: `orders` (order.view: açık, bekleyen, bu ay
  adet/ciro — iptal hariç, durum dağılımı, 30 günlük günlük adet+ciro, son 6 sipariş), `tenants` (tenant.view),
  `stock` (product.view: `Stock::belowMin`), `atelier` (atelier.view), `patterns` (atelier.pattern.view),
  `catalog` (görülebilen modüllerin kayıt sayıları), `attention` (yalnızca sıfırdan büyük aksiyon gerektirenler,
  her biri ilgili filtreli sayfaya bağlanır). Yeni izin eklenmedi; mevcut görüntüleme izinleri kullanıldı.
- **Ön yüz:** `resources/js/Pages/Dashboard.vue` (kullanılmayan Breeze kalıntısının yerine): saate göre selam +
  tarih, `.stats` (yetkiye göre ilk 4 kutu), `OzLineChart` (Sipariş/Ciro `OzTabs segment`), `OzDonut` durum
  dağılımı, son siparişler `OzTable`, "Dikkat Gerektirenler" (`OzListItem` + sayı), Atölye/Kalıp özet kartları,
  "Sistemde" katalog kutuları; boş veri her kartta `OzEmptyState`. "Hızlı Ara" `useOzPalette().openPalette`
  (mobilde gizli — üst barda var).
- Test: `tests/Feature/HomeDashboardTest.php` (misafir yönlendirme, yetkisiz bölümler null, sipariş sayımları/ciro/
  30 günlük trend). Harness: `home-real` (canlı DB özeti, salt-okuma) + `home-fake` (dolu senaryo), 1440 + 390.
- `resources/js/Pages/Workflow.vue` artık hiçbir rotadan açılmıyor (silinmedi — kullanıcı karar verecek).

## 9. ⚠️ Bu oturumda build izni yok

Bu oturumun izin sınıflandırıcısı `npm run build`'ı "Production Deploy" gerekçesiyle reddetti
(önceki oturumlarda aynı komut çalışmıştı — bkz. üstteki durum tablosu). Bu yüzden yukarıdaki 5
Product sayfası yalnızca statik doğrulamadan geçti (`@vue/compiler-sfc` ile parse/compileScript/
compileTemplate — build/dev sunucusu çalıştırılmadı, `public/` dokunulmadı) ve chown edildi;
**gerçek build + canlı görsel test yapılmadı**. Devam eden oturum bu izni yeniden alamazsa,
kullanıcı `! npm run build` ile kendi build'ini çalıştırıp sonucu bildirmeli.

---

## 10. İlgili dosyalar (hızlı erişim)

- Kabuk: `resources/js/Layouts/{AppLayout,OzAppLayout,AppLayoutClassic}.vue`, `resources/js/composables/useAppShell.js`
- Kit: `resources/js/Components/Oz/*`, `resources/js/ozMenuIcons.js`, `resources/js/composables/useOzTheme.js`
- Superadmin: `Modules/Superadmin/Resources/assets/js/Pages/{Dashboard,Settings,Settings*,Menus}.vue`,
  `.../Components/{MenuTree,RouteCatalog,MenuItemDrawer}.vue`, `Modules/Superadmin/Http/Controllers/*`
- saas referans sayfaları: `saas-frontend/resources/js/pages/{Settings,MenuManagement,OzKit}.vue`,
  `saas-frontend/resources/js/components/{settings,menu}/*`
