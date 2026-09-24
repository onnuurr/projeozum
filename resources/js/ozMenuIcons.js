import { lucideIcon, lucideNames } from '@/lucideIcons.js'

// Menü ikonları (superadmin_menus.icon) Lucide adıyla saklanır (ör. 'shopping-cart'); tüm Lucide seti seçilebilir.
// Eski kayıtlarda menuIcons.js anahtarları olabilir → Lucide karşılığına çevrilir.
// OzAppLayout (rail / mobil menü / palet) ve Menü Yönetimi sayfası bu tek eşlemeyi kullanır.
export const LEGACY_MENU_ICONS = {
	dashboard: 'layout-grid', relations: 'users', orders: 'shopping-cart', reports: 'chart-column',
	shipping: 'send', documents: 'file-text',
}

// Eski anahtar → Lucide adı (zaten Lucide adıysa aynen)
export const menuIconName = (key) => (key ? LEGACY_MENU_ICONS[key] ?? key : '')

// Yüklenirken / bilinmeyen adda boş çizilir (yanlış ikon yanıp sönmesin)
const Blank = () => null

// OzIcon :name için bileşen döner; key yoksa fallback (Lucide adı)
export function ozIconForMenu(key, fallback = 'layers') {
	return lucideIcon(menuIconName(key) || fallback) ?? Blank
}

// OzIconPicker :options — değer ve çizim Lucide adı; :resolve="ozIconForMenu" ile çizilir
// İlk sayfada menülere uygun ikonlar, ardından tüm set alfabetik
const FEATURED = [
	'layout-grid', 'layout-dashboard', 'house', 'users', 'user', 'contact', 'building-2', 'store', 'package', 'boxes',
	'shopping-cart', 'shopping-bag', 'receipt', 'truck', 'send', 'warehouse', 'factory', 'scissors', 'shirt', 'ruler',
	'palette', 'pen-tool', 'layers', 'workflow', 'calendar', 'clock', 'chart-column', 'chart-line', 'chart-pie', 'trending-up',
	'wallet', 'banknote', 'credit-card', 'coins', 'landmark', 'file-text', 'files', 'folder', 'clipboard-list', 'tag',
	'barcode', 'qr-code', 'printer', 'mail', 'message-square', 'bell', 'star', 'sparkles', 'image', 'globe',
	'shield-check', 'key-round', 'database', 'server', 'wrench', 'settings', 'sliders-horizontal', 'circle-help',
].filter((n) => lucideNames.includes(n))
const featured = new Set(FEATURED)
export const menuIconOptions = [...FEATURED, ...lucideNames.filter((n) => !featured.has(n))]
	.map((name) => ({ value: name, icon: name, label: name }))
