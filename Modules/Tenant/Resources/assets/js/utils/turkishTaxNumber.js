// VKN (10 hane) ve TCKN (11 hane) resmi checksum algoritmaları — dış servise gitmeden
// anlık biçim doğrulaması için. Backend karşılığı (otoriter kaynak):
// Modules/Tenant/Support/TurkishTaxNumberValidator.php — ikisi senkron tutulmalı.
// Unvan/kimlik eşleşmesini DOĞRULAMAZ, sadece yazım hatalarını yakalar.

export function isValidVkn(vkn) {
	if (!/^\d{10}$/.test(vkn)) return false
	const d = vkn.split('').map(Number)
	let sum = 0
	for (let i = 0; i < 9; i++) {
		let tmp = (d[i] + 9 - i) % 10
		tmp = (tmp * Math.pow(2, 9 - i)) % 9
		if (d[i] !== 0 && tmp === 0) tmp = 9
		sum += tmp
	}
	const check = (10 - (sum % 10)) % 10
	return check === d[9]
}

export function isValidTckn(tckn) {
	if (!/^[1-9]\d{10}$/.test(tckn)) return false
	const d = tckn.split('').map(Number)
	const oddSum = d[0] + d[2] + d[4] + d[6] + d[8]
	const evenSum = d[1] + d[3] + d[5] + d[7]
	const d10 = (((oddSum * 7 - evenSum) % 10) + 10) % 10
	if (d10 !== d[9]) return false
	const d11 = d.slice(0, 10).reduce((a, b) => a + b, 0) % 10
	return d11 === d[10]
}

/** Ayraç/boşluk fark etmeksizin uzunluğa göre VKN ya da TCKN olarak doğrular. */
export function isValidTaxNumber(raw) {
	const digits = String(raw ?? '').replace(/\D/g, '')
	if (digits.length === 10) return isValidVkn(digits)
	if (digits.length === 11) return isValidTckn(digits)
	return false
}
