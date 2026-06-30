---
name: laravel-form-request
description: Use when creating or editing a Laravel Form Request in this project (under app/Http/Requests/ or Modules/*/Http/Requests/). Covers Store/Update<Model>Request naming, spatie/permission authorize(), rules() with Rule::exists/unique, and where Form Requests are preferred vs inline validate().
---

# Laravel Form Request — Proje Konvansiyonu

## Konum & Adlandırma

- `Modules/<Mod>/Http/Requests/Store<Model>Request.php`
- `Modules/<Mod>/Http/Requests/Update<Model>Request.php`
- Paylaşılan / app-level (örn. profil) → `app/Http/Requests/...`
- **İsim deseni:** `Store<Model>Request` (POST), `Update<Model>Request` (PUT/PATCH).
  Resmi olmayan tek-alanlı toggle action'lar için yeni Request açmaya gerek yok;
  controller'da `validate()` yeterli.

## Ne zaman Form Request, ne zaman inline?

| Kullanım | Tercih |
|---|---|
| API endpoint POST/PUT/PATCH | **Form Request** |
| Inertia POST'u resmi bir kayıt oluşturuyor / güncelliyor | **Form Request** |
| Tek alan toggle (`is_active`, sort) | `inline $request->validate()` |
| Esnek admin formu (alanlar runtime'da değişiyor) | inline + private helper |

## İskelet

`Modules/Tenant/Http/Requests/StoreTenantRequest.php`'ye uygun:

```php
namespace Modules\Tenant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('tenant.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'code'  => 'required|string|max:32|unique:tenants,code',
            'name'  => 'required|string|max:191',
            'slug'  => 'nullable|string|max:191|unique:tenants,slug',
            // ...
        ];
    }
}
```

## `authorize()` her zaman

> **ÖNCE `laravel-authorization` skill'ini çalıştır.** `hasPermissionTo(...)`
> içine string yazmadan önce "hangi yetki?" kararı net olmalı — mevcut listeden
> seç ya da yeni yetkiyi PermissionSeeder'a ekle. Aynı string controller
> middleware'inde de bulunmalı.

- **`return true;` yazma.** Bu projede yetki `spatie/laravel-permission` ile yürür.
- `hasPermissionTo('<resource>.<action>')` veya `hasRole('superadmin')`.
- Null-safe okuma: `$this->user()?->hasPermissionTo(...) ?? false`.
- Authorize başarısız olunca otomatik 403 dönecektir.

## `rules()` desenleri

- **Update**'te `unique` için mevcut kaydı dışla:
  ```php
  use Illuminate\Validation\Rule;
  'code' => ['required','string','max:32', Rule::unique('tenants','code')->ignore($this->tenant)],
  ```
  Route model binding değişkenine dikkat: `Route::apiResource('tenants', ...)` →
  `$this->tenant`.
- FK referansı için **`Rule::exists`** (string `exists:` yerine):
  ```php
  'tenant_type_id' => ['nullable', Rule::exists('tenant_types','id')],
  ```
- Para → `numeric|min:0`; oran → `numeric|min:0|max:100`.
- Boolean alanlarda **`'boolean'`** kuralı yeterli (controller `validated()`
  çıktısında `true/false` döner).

## Türkçe mesaj override

İhtiyaç olursa request içine ekle:

```php
public function messages(): array
{
    return [
        'code.required' => 'Tenant kodu zorunludur.',
        'code.unique'   => 'Bu kod başka bir tenant\'ta kullanılıyor.',
    ];
}
```

App-wide çevirilere bağımlı yapma; mesajlar Request'in **kendi** dosyasında dursun.

## `prepareForValidation()`

Slug üretmek, trimlemek, boş string'i null yapmak için:

```php
protected function prepareForValidation(): void
{
    $this->merge([
        'slug' => $this->slug ?: Str::slug($this->name ?? ''),
    ]);
}
```

## Controller'a aktarım

```php
public function store(StoreTenantRequest $request): JsonResponse
{
    $tenant = $this->service->create($request->validated());
    // ...
}
```

`$request->validated()`, `safe()`, veya tek alan için `$request->safe()->only(['x'])`
kullan. **`$request->all()` ile yazma** — onaylanmamış alan sızdırır.

## Checklist

- [ ] İsim `Store<Model>Request` / `Update<Model>Request`
- [ ] Dizin ilgili modülün `Http/Requests/` dizininde
- [ ] `authorize()` spatie permission veya role ile doluyor; `return true` yok
- [ ] Update'te `Rule::unique(...)->ignore(...)` doğru kaydı dışlıyor
- [ ] FK'ler `Rule::exists` ile doğrulandı
- [ ] Controller `validated()` / `safe()` kullanıyor, `all()` değil
- [ ] Türkçe mesajlar gerekiyorsa `messages()` içinde yazıldı
