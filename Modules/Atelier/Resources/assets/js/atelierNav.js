// Atölye sayfalarının ortak biçimlendirme yardımcıları ve üretim emri durum sözlükleri.
// (Sekme listesi/provideAtelierNav 2026-09-24'te kaldırıldı: sekmeler artık DB menüsünden,
// yetkiye göre süzülmüş olarak gelir.)

// Üretim emri durumları (ProductionOrder::STATUS_*)
export const ORDER_STATUS = { draft: 'Taslak', planned: 'Planlandı', in_progress: 'Üretimde', completed: 'Tamamlandı', cancelled: 'İptal' }
export const ORDER_TONE = { draft: 'neutral', planned: 'info', in_progress: 'accent', completed: 'success', cancelled: 'danger' }

export function formatMoney(v) {
	return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 2 }).format(Number(v ?? 0))
}

export function formatNumber(v, digits = 2) {
	return new Intl.NumberFormat('tr-TR', { maximumFractionDigits: digits }).format(Number(v ?? 0))
}
