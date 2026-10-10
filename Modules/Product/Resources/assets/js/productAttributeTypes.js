// Ürün Ayarları → Özellikler tipleri (ProductAttribute::TYPES ile aynı; Bagisto attribute tipleriyle
// eşleşir, `number` Bagisto'da metin + ondalık doğrulama). Ayar ekranı, kategori penceresi ve ürün
// formu bu tek listeyi kullanır.
export const ATTRIBUTE_TYPES = [
	{ value: 'select', label: 'Seçim (tek değer)', short: 'Seçim', tone: 'accent' },
	{ value: 'multiselect', label: 'Çoklu seçim (liste)', short: 'Çoklu seçim', tone: 'info' },
	{ value: 'checkbox', label: 'Onay kutuları', short: 'Onay kutuları', tone: 'info' },
	{ value: 'text', label: 'Metin (kısa)', short: 'Metin', tone: 'neutral' },
	{ value: 'textarea', label: 'Uzun metin', short: 'Uzun metin', tone: 'neutral' },
	{ value: 'number', label: 'Sayı', short: 'Sayı', tone: 'neutral' },
	{ value: 'price', label: 'Fiyat (₺)', short: 'Fiyat', tone: 'success' },
	{ value: 'boolean', label: 'Evet / Hayır', short: 'Evet / Hayır', tone: 'success' },
	{ value: 'date', label: 'Tarih', short: 'Tarih', tone: 'warning' },
	{ value: 'datetime', label: 'Tarih ve saat', short: 'Tarih-saat', tone: 'warning' },
	{ value: 'image', label: 'Görsel (JPG, PNG, WEBP · 5 MB)', short: 'Görsel', tone: 'danger' },
	{ value: 'file', label: 'Dosya (PDF, görsel · 10 MB)', short: 'Dosya', tone: 'danger' },
]

export const TYPE_LABELS = Object.fromEntries(ATTRIBUTE_TYPES.map((t) => [t.value, t.short]))
export const TYPE_TONES = Object.fromEntries(ATTRIBUTE_TYPES.map((t) => [t.value, t.tone]))

export const OPTION_TYPES = ['select', 'multiselect', 'checkbox']
export const FILTERABLE_TYPES = ['select', 'multiselect', 'checkbox', 'boolean', 'price']
export const UPLOAD_TYPES = ['image', 'file']

export const hasOptions = (type) => OPTION_TYPES.includes(type)
