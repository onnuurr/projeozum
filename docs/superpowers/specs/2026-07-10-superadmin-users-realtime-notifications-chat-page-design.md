# Superadmin Kullanıcı Yönetimi + Anlık Bildirim + Chatbot Tam Sayfa — Tasarım

**Tarih:** 2026-07-10
**Modüller:** `Modules/Superadmin`, `Modules/Creative`, `app/` (broadcasting altyapısı)
**Durum:** Onaylandı (brainstorming), plan aşamasına geçiliyor

## Amaç

Bu spec, Creative modülündeki manken/ürün-giydirme onay akışı (bkz.
`[[project_creative_mannequin_review_workflow]]`) canlıda test edilirken ortaya çıkan üç
bağımsız eksikliği kapatır:

1. **Onay için "başka bir yönetici hesabı" yok** — `creative.approve` izni sadece
   `superadmin` rolüne atanmış; kullanıcı Ayarlar → Roller'dan bir rol (`yonetim`)
   oluşturabildi ama o role izin verecek/kullanıcıya rolü atayacak bir arayüz yok.
   → **A. Superadmin Kullanıcı Yönetimi**.
2. **Bildirimler anlık gelmiyor** — bildirim çanı yalnızca Inertia sayfa/response ile
   güncelleniyor; başka bir kullanıcının tetiklediği bildirim sayfa yenilenmeden görünmüyor.
   → **B. Anlık Bildirim Altyapısı**.
3. **Chatbot arayüzü yetersiz** — reddedilen görsellerde gömülü küçük panel, gerçek bir
   sohbet deneyimi sunmuyor. → **C. Chatbot Tam Sayfa**.

Üçü bağımsızdır, sırayla uygulanacaktır (A → B → C) ama aynı spec altında toplanmıştır
çünkü aynı oturumda, aynı kullanıcı isteğiyle onaylandı.

## Bağlam (mevcut durum, araştırmayla doğrulandı)

- Roller: `superadmin`, `tenant`, `tenant-user`, ve kullanıcının UI'dan oluşturduğu `yonetim`
  (Ayarlar → Roller sekmesi, `RoleController`). Yeni role **otomatik izin atanmıyor**.
- `users` tablosu: `id, name, email, email_verified_at, password, remember_token,
  created_at, updated_at, tenant_id, is_active, deleted_at` — `is_active` ve `deleted_at`
  (SoftDeletes) **zaten var** (2026-07-02 tarihli Tenant kullanıcı yönetimi işiyle geldi).
  `phone`, `job_title` `User::$fillable`'da tanımlı ama DB'de **yok**.
- Kullanıcıya rol atayan hiçbir UI yok; sidebar'daki `/users` linki **Tenant Portal'a ait**
  (`PortalUserController`, tenant'ın kendi alt-kullanıcılarını yönettiği ayrı bir akış) —
  buna dokunulmayacak.
- `Modules/Superadmin/Http/Controllers/RoleController.php`: rol CRUD + `syncPermissions`
  var, `SettingsController::buildRolesPayload()` rol listesini Settings.vue'ya besliyor.
- `Modules/Superadmin/database/seeders/SuperadminPermissionSeeder.php`: kısa-isim izin
  geleneği (`logs.view`, `settings.manage`, `menu.manage`, `rbac.manage`), `RolePermissionSeeder`
  tarafından zaten çağrılıyor.
- Sağ üst kullanıcı menüsü (`AppLayout.vue:133-156`, `userMenu` computed) — "Süper Admin
  Paneli" / "Menü Yönetimi" gibi `role==='superadmin'` şartlı linkler burada.
- **Broadcasting:** `laravel/reverb` composer'da kurulu, `.env`'de `BROADCAST_CONNECTION=reverb`
  + `REVERB_*` tanımlı, frontend `laravel-echo`+`pusher-js` kurulu ve `resources/js/bootstrap.js`
  içinde `window.Echo` zaten yapılandırılmış. **Ama** `config/broadcasting.php` yok,
  `routes/channels.php` yok, hiçbir Notification `ShouldBroadcast` implement etmiyor, hiçbir
  Vue dosyası `Echo.private().listen()` çağırmıyor. `SystemInfoService::reverbStatus()` gerçek
  bir `fsockopen` TCP probe'u ile `reverb:start` sürecinin ayakta olup olmadığını kontrol
  ediyor (config varlığı değil).
- Bildirim çanı: `HandleInertiaRequests.php` her istekte `notifications` prop'unu DB'den taze
  çekiyor; `AppLayout.vue` bunu `computed` ile okuyor. Polling yok.
- `Modules/Creative/Notifications/{ImagePendingReviewNotification,ImageReviewDecisionNotification}`
  şu an sadece `database` kanalı kullanıyor (`via()`).
- `Modules/Creative/Resources/assets/js/Components/ReviewChatPanel.vue` — liste kartlarına
  gömülü küçük sohbet paneli (mesaj listesi + textarea + gönder/uygula butonu).
  `Modules/Creative/Http/Controllers/ReviewChatController.php` — `sendMannequin/sendTryon/
  applyMannequin/applyTryon`, hepsi POST, sayfa render eden bir `show()` yok.

## A. Superadmin Kullanıcı Yönetimi

### Kapsam
Tüm kullanıcılar (staff + tenant kullanıcıları), `tenant_id` formda opsiyonel.

### Veri modeli
Migration — `users` tablosuna `phone` (string, nullable), `job_title` (string, nullable).
(`is_active`/`deleted_at` zaten var, dokunulmaz.)

### İzinler
`SuperadminPermissionSeeder`'ın mevcut `$permissions` dizisine eklenir:
`users.view` → "Kullanıcıları Görüntüle", `users.manage` → "Kullanıcı Yönet"
(kısa-isim geleneğine uyar, superadmin rolüne otomatik atanır).

### Backend
`Modules/Superadmin/Http/Controllers/UserController.php`:
- `index()` — `User::with('roles','tenant')->get()`, `can:users.view`. Props: `users`
  (id/name/email/phone/job_title/is_active/role/tenant/created_at), `roles` (`Role::all()`),
  `tenants` (`Tenant::where('is_active',true)->get(['id','name'])`).
- `store()` — validate (name, email unique, password required|min:8|confirmed, role
  exists:roles,name, tenant_id nullable|exists, phone, job_title). `User::create` +
  `assignRole`. `can:users.manage`.
- `update()` — aynı validasyon, şifre boşsa korunur, `syncRoles` (tek rol). **Guard:** hedef
  kullanıcı zaten `superadmin` rolündeyse, rol bu ekrandan değiştirilemez (kilitlenmeyi
  önler — bilerek değiştirmek tinker gerektirir). `can:users.manage`.
- `destroy()` — soft delete (`$user->delete()`, trait zaten var). Kendini silemez, son
  superadmin'i silemez. `can:users.manage`.
- `toggleActive()` — `is_active` tersine çevrilir. Kendini pasifleştiremez. `can:users.manage`.

Route'lar `Modules/Superadmin/routes/web.php`'ye eklenir (mevcut `auth+verified` grubunda),
prefix `/superadmin/users`.

### Frontend
`Modules/Superadmin/Resources/assets/js/Pages/Users.vue` — `Tenants.vue` kalıbı: tablo +
arama + rol/durum filtre dropdown'ları, `AppModal` form (reactive state + `watch(formOpen)`
reset), silme `$swal.dangerConfirm`, bildirim `showToast`. Rol seçimi `roles` prop'undan
(kullanıcının oluşturduğu `yonetim` dahil tüm roller otomatik listelenir).

`AppLayout.vue` kullanıcı menüsüne (`userMenu` computed, satır ~141'den sonra) yeni satır:
`{ label: 'Kullanıcı Yönetimi', icon: '...', to: '/superadmin/users', visible: role === 'superadmin' }`.

## B. Anlık Bildirim Altyapısı

Genel amaçlı — Creative'e özel değil, ileride herhangi bir Notification bundan faydalanabilir.

### Backend
1. `php artisan install:broadcasting` (veya elle) — `config/broadcasting.php` publish edilir
   (driver `reverb`), `routes/channels.php` oluşturulur: Laravel'in varsayılan özel kullanıcı
   kanalı `App.Models.User.{id}`, yetki `fn ($user, $id) => (int) $user->id === (int) $id`.
2. `Modules/Creative/Notifications/ImagePendingReviewNotification` ve
   `ImageReviewDecisionNotification` → `implements ShouldBroadcast`. `via()` →
   `['database','broadcast']`. Mevcut `toArray()` payload'ı `toBroadcast()` için de kullanılır
   (Laravel varsayılanı — ek metod gerekmez, `Illuminate\Notifications\Notification`'ın
   `BroadcastMessage` fallback'i `toArray()`'i sarar).
3. `.env.example`'a `REVERB_*`/`BROADCAST_CONNECTION` satırları eklenir (yeni dev ortamları
   için — şu an eksik).

### Frontend
`AppLayout.vue`'da tek seferlik genel dinleyici (mevcut `notifications` ref'ine ekler):

```js
onMounted(() => {
  const id = currentUser.value.id
  if (id && window.Echo) {
    window.Echo.private(`App.Models.User.${id}`)
      .notification((notification) => {
        notifications.value.unshift(notification)
      })
  }
})
```

Bu, `ShouldBroadcast` implement eden HERHANGİ bir Notification için otomatik çalışır —
Creative'in iki sınıfı ilk kullanıcısı olur, ileride `AssignmentNotification` gibi başkaları
da tek satır (`implements ShouldBroadcast`) ile anlık hale gelebilir.

### Doğrulama
`reverb:start` çalışırken iki farklı tarayıcı oturumunda: kullanıcı A onaya gönderir →
kullanıcı B'nin çanı sayfa yenilenmeden anlık güncellenir.

## C. Chatbot Tam Sayfa

### Backend
`ReviewChatController`'a `showMannequin(Mannequin $mannequin)` / `showTryon(TryonResult $result)`
(GET) eklenir — aynı `authorizeChat()` guard'ı, `Inertia::render('Creative::ReviewChat', [...])`
ile subject bilgisi + `ReviewChatService::history()` + `latestSuggestion()` geçilir. Yeni
route'lar: `GET /creative/mannequins/{mannequin}/review-chat`, `GET /creative/tryon/{result}/review-chat`
(mevcut POST'larla aynı path, farklı HTTP metodu — çakışma yok).

### Frontend
Yeni sayfa `Modules/Creative/Resources/assets/js/Pages/ReviewChat.vue`:
- Üst bağlam kartı: küçük görsel önizleme + ret gerekçesi banner'ı + "← Geri dön" linki
  (kaynağa göre `/creative/mannequins` veya `/creative/tryon`).
- Orta: kaydırılabilir mesaj akışı, kullanıcı mesajları sağda / asistan mesajları solda
  balon, zaman damgası, gönderirken "yazıyor…" göstergesi (`sending` state).
- Öneri hazır olduğunda üstte sabit (sticky) CTA banner: "Önerilen düzeltme: … [Bu talimatla
  yeniden üret]".
- Alt: sabit input çubuğu, otomatik büyüyen `textarea`, Enter=gönder / Shift+Enter=yeni satır.

`CreativeMannequins.vue`/`CreativeTryon.vue`'daki gömülü `ReviewChatPanel` kullanımı,
reddedilen kayıtlarda basit bir **"💬 AI ile Konuş (N mesaj)"** linkine indirgenir (yeni
sayfaya yönlendirir); `ReviewChatPanel.vue` bileşeni kaldırılır (artık kullanılmıyor).

### Doğrulama
Reddedilen bir mankende linke tıkla → tam sayfa açılır → mesaj gönder (Gemini mock/gerçek) →
öneri banner'ı görünür → "yeniden üret" → job kuyruğa girer, `/creative/mannequins`'e döner.

## Sıralama ve bağımsızlık

A, B, C birbirinden bağımsız modüllerde çalışır ve ayrı ayrı test edilebilir. Sırayla
uygulanacak (A → B → C); B'nin frontend kısmı C'den önce bitmiş olursa, C sayfasında da
(gerekirse) aynı genel Echo altyapısı kullanılabilir ama zorunlu değildir (C kendi başına
POST/GET ile de tam çalışır, anlık bildirim C'nin bir parçası değildir).

## Test kapsamı

- **A:** Pest feature testi — `users.manage` izniyle store/update/destroy/toggle, kendini
  silememe, son superadmin korunması, superadmin rolü değiştirilememesi.
- **B:** `Notification::fake()` ile `ShouldBroadcast` implement edildiğini doğrulayan birim
  test (`assertSentTo` zaten var olan testleri bozmaz). Gerçek WebSocket akışı otomatik
  testle doğrulanamaz — manuel doğrulama (iki tarayıcı) gerekir.
- **C:** `show()` action'larının `authorizeChat()` guard'ını (403/422) doğrulayan feature
  testleri (mevcut `ReviewChatTest.php`'ye eklenir).
