# Superadmin Dinamik Menü Sistemi — Tasarım

**Tarih:** 2026-06-11
**Modül:** `Modules/Superadmin`
**Branch:** `feature/creative-image-automation` (mevcut)

## Amaç

Şu an tüm navigasyon `resources/js/Layouts/AppLayout.vue` içinde hardcoded:
`baseNavItems` (header ana menüler + child dropdown) ve `sidebarTopAll` (sol sidebar
ikon butonları). Bu menüler artık veritabanından gelecek ve Superadmin'de
sürükle-bırak ile yönetilecek.

**Yeni navigasyon paradigması:**
- **Sol sidebar** = yalnızca **kök (ana) menüler** — ikonlu.
- **Header** = aktif kök menünün **child'ları**; daha derin torunlar dropdown ile.

## Alınan kararlar

| Karar | Seçim |
|---|---|
| Mevcut hardcoded nav | **Tamamen değiştir** (seeder'a taşınır) |
| Link tipi | **route ismi + url path** (ikisi de; route öncelikli) |
| Görünürlük | **Spatie permission** (server-side filtre, superadmin Gate::before ile bypass) |
| Derinlik | **Sınırsız** (`parent_id` self-referencing) |
| İkon | **Hazır SVG ikon seti + picker** |
| Menü ağacı dağıtımı | **Server-side ağaç + Inertia::share** (mevcut `auth.user`/`cart` deseni) |
| Silinen parent davranışı | **Kademeli sil** (onay diyaloğuyla) |

## 1. Veri modeli — `superadmin_menus`

| kolon | tip | açıklama |
|---|---|---|
| id | bigint | PK |
| parent_id | nullable FK → `superadmin_menus.id` | kök menüler `null` (sidebar) |
| label | string | görünen ad |
| icon | nullable string | ikon seti anahtarı (sadece kökler için anlamlı) |
| route_name | nullable string | Laravel named route |
| url | nullable string | düz path; route varsa o öncelikli |
| permission | nullable string | Spatie permission adı; `null` = herkese açık |
| sort_order | int, default 0 | kardeşler arası sıra (drag-drop) |
| is_active | bool, default true | yayında mı |
| timestamps | | |

**Migration disiplini (CLAUDE.md):**
- `down()` gerçek ters işlemi yapar (`dropIfExists`).
- `parent_id` FK self-referencing tabloda `cascadeOnDelete` ile kurulur — kararla
  (kademeli sil) tutarlı; parent silinince alt ağaç DB seviyesinde de düşer.
  (Controller `destroy` ayrıca onay + temizlik akışını yönetir.)

**Seeder:** `MenuSeeder` mevcut `baseNavItems` + `sidebarTopAll` içeriğini bu tabloya
taşır. Kökler ikonlu (mevcut SVG anahtarlarıyla eşlenir), child'lar mevcut
`to` path'leriyle. `staffOnly` olanlar uygun permission'a bağlanır.

## 2. Backend bileşenleri

### `Modules/Superadmin/Models/Menu.php`
- `protected $table = 'superadmin_menus';`
- İlişkiler: `parent()` (belongsTo self), `children()` (hasMany self, `sort_order` ile ordered).
- Scope'lar: `scopeRoots` (`whereNull('parent_id')`), `scopeOrdered`, `scopeActive`.
- `isVisibleTo(?User $user): bool` — `permission` null ise true; aksi halde
  `$user?->can($permission)`. Superadmin `Gate::before` ile zaten geçer.

### `Modules/Superadmin/Services/MenuTreeBuilder.php`
- `forUser(?User $user): array` — aktif menüleri tek sorguda çeker, bellekte ağaç kurar.
- Her düğüm yalnızca `isVisibleTo` true ise dahil edilir.
- İzinsiz parent çocuklarıyla birlikte düşer (parent görünmüyorsa alt ağaç da render edilmez).
- Çıktı şekli (frontend sözleşmesi):
  ```
  [{ id, label, icon, to, permission, children: [...] }]
  ```
  `to` = route varsa `route($route_name)`, yoksa `url`, ikisi de yoksa `null`.

### `Modules/Superadmin/Http/Controllers/MenuController.php`
- `index()` — Inertia `Superadmin/Menus` sayfası; tüm menüleri (filtresiz, yönetim için)
  ağaç + düz liste olarak, ikon seti anahtarlarını ve mevcut permission listesini props ile döner.
- `store(StoreMenuRequest)` — yeni menü.
- `update(UpdateMenuRequest, Menu)` — düzenleme.
- `destroy(Menu)` — kademeli sil (alt ağacı dahil; controller içinde recursive ya da
  FK cascade). Onay frontend'de.
- `reorder(ReorderMenuRequest)` — `[{ id, parent_id, sort_order }]` toplu günceller;
  **döngü engeli** (bir menü kendi torununa parent yapılamaz) burada doğrulanır.

### FormRequest'ler
- `StoreMenuRequest` / `UpdateMenuRequest`: `label` required; `route_name`/`url` nullable;
  `permission` nullable + mevcut permission'larda var olmalı; `icon` nullable + ikon setinde olmalı.
- `ReorderMenuRequest`: dizi yapısı + her öğe id mevcut + döngü kontrolü.

### Inertia paylaşımı
- `AppServiceProvider` (veya `HandleInertiaRequests`) içinde:
  `Inertia::share('menu', fn () => MenuTreeBuilder::forUser(auth()->user()))`.
- Misafir/null kullanıcıda boş dizi döner.
- Yalnızca aktif + izinli ağaç paylaşılır (gizli menüler payload'a girmez).

### Route'lar (`Modules/Superadmin/routes/web.php`, `role:superadmin` grubu)
```
GET    superadmin/menus            → index   (superadmin.menus)
POST   superadmin/menus            → store   (superadmin.menus.store)
PUT    superadmin/menus/{menu}     → update  (superadmin.menus.update)
DELETE superadmin/menus/{menu}     → destroy (superadmin.menus.destroy)
POST   superadmin/menus/reorder    → reorder (superadmin.menus.reorder)
```

## 3. Frontend — yönetim sayfası `Superadmin/Resources/assets/js/Pages/Menus.vue`

- Ağaç görünümü; `vuedraggable` ile:
  - **sıralama** (kardeşler arası `sort_order`),
  - **nesting** (sürükleyip başka bir menünün altına bırakma → `parent_id` değişir).
  - Sınırsız derinlik (recursive nested draggable bileşeni).
- Satır işlemleri: ekle / düzenle / sil.
  - Düzenle/ekle → drawer veya `AppModal` (mevcut desen).
  - Sil → onay diyaloğu (`$swal.dangerConfirm`), "alt menüler de silinecek" uyarısı.
- Form alanları: `label`, ikon picker (grid; `menuIcons.js`), `route_name`, `url`,
  `permission` (select; props'tan gelen izin listesi), `is_active` toggle.
- Reorder sonrası: `router.post(route('superadmin.menus.reorder'), payload, { preserveScroll: true, preserveState: true })`.
- Erişim: superadmin user menüsünden link (mevcut "Süper Admin Paneli" yanına "Menüler").

### Ortak ikon seti — `Superadmin/Resources/assets/js/menuIcons.js`
- `export default { key: '<path d=...>' }` haritası.
- Hem picker grid'i hem `AppLayout` sidebar render'ı bu tek kaynağı kullanır.
- Mevcut `sidebarTopAll` SVG path'leri buraya anahtarlanarak taşınır.

## 4. Frontend — canlı navigasyon `resources/js/Layouts/AppLayout.vue`

- `baseNavItems` ve `sidebarTopAll` **kaldırılır**.
- `const menu = computed(() => page.props.menu ?? [])`.
- **Sidebar** (`LeftSidebar`):
  - `topButtons` = kök menüler → `{ label, icon: renderIcon(node.icon), to, active }`.
  - `renderIcon` → `menuIcons.js`'ten SVG sarmalar (mevcut `SVG()` helper formatı).
  - Alt aksiyonlar (`sidebarBottom`: Tema/Bildirim) **aynı kalır** (menü değil).
- **Header** (`TopNav`):
  - `navItems` = **aktif kökün** `children`'ı → `{ name, to, active, children: [...] }`.
  - Daha derin torunlar mevcut `nav-dropdown` ile gösterilir (TopNav'da değişiklik minimal).
- **Aktif kök tespiti**:
  - `page.url`'i ağaçta eşleştiren `findActiveRoot(menu, url)` — torunlarda en iyi
    (en uzun prefix) eşleşmeyi bulur, kök ataya yürür.
  - Eşleşme yoksa ilk kök varsayılan aktif.
- Header user/notif/sepet/arama aksiyonları ve user menüsü **aynı kalır** (menü değil).

## 5. Hata yönetimi & test

### Hata yönetimi
- CRUD doğrulaması FormRequest'lerde.
- **Döngü engeli**: reorder/update'te bir menü kendi torununa parent yapılamaz (server-side red).
- **Kademeli silme**: parent silinince alt ağaç da silinir; frontend onay diyaloğu uyarır.
- İzin filtresi her zaman server-side; gizli menüler asla payload'a girmez.
- Misafir kullanıcı / boş menü → sidebar ve header boş ama layout kırılmaz.

### Test (Pest/PHPUnit)
- `MenuTreeBuilder` izin filtresi: izinsiz kullanıcı gizli menüyü görmez; superadmin hepsini görür.
- `MenuTreeBuilder` ağaç kurma: nested yapı doğru, izinsiz parent alt ağacıyla düşer.
- `reorder` feature: `sort_order`/`parent_id` güncellenir; **döngü denemesi reddedilir**.
- `destroy` feature: alt ağaç da silinir.
- `MenuSeeder`: mevcut menüleri (kökler + child'lar) doğru üretir.

## Kapsam dışı (YAGNI)

- Menü başına çoklu-dil / i18n.
- Menü bazlı feature-flag / A-B.
- Sürükle-bırak dışında ayrı "taşı" butonları.
- Frontend tarafı izin filtresi (kasıtlı olarak yalnızca server-side).
