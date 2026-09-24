// Ret analiz raporu (creative:review-report) ortak yardımcıları — liste, detay ve bölüm bileşeni kullanır.

// Ret oranı yorumu: <%15 iyi · <%30 dikkat · üstü kötü. Düşük değer yeşil (sıfır-tespit, ΔE eşik üstü oranları da aynı yön).
export function rateTone(rate) {
	if (rate === null || rate === undefined) return 'neutral'
	if (rate < 0.15) return 'success'
	if (rate < 0.3) return 'warning'
	return 'danger'
}

export function formatRate(rate) {
	return rate === null || rate === undefined ? '—' : `%${Math.round(rate * 100)}`
}

// PHP boş diziyi [] olarak yazar, dolu olanı {anahtar: değer} — ikisini de [anahtar, değer] listesine çevirir
export function entriesOf(obj) {
	return obj && !Array.isArray(obj) ? Object.entries(obj) : []
}
