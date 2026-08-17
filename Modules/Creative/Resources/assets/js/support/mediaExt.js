// Görsel üretim formatı (webp/jpg/png) sunucu tarafında config ile değişebildiği
// için indirme dosya adı önerisinde sabit ".png" varsayılamaz; gerçek uzantı
// medya URL'sinden okunur.
export function extOf(url, fallback = 'png') {
	if (!url) return fallback
	const clean = url.split('#')[0].split('?')[0]
	const match = clean.match(/\.([a-zA-Z0-9]+)$/)
	return match ? match[1].toLowerCase() : fallback
}
