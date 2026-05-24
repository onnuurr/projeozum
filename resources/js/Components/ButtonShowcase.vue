<template>
	<div class="form-card">
		<div class="form-card-header">
			<h3>Buton Varyasyonları</h3>
		</div>
		<div class="form-card-body">
			<!-- Renk varyasyonları -->
			<div class="btn-section">
				<div class="btn-section-label">Renk Varyasyonları</div>
				<div class="btn-row">
					<button class="btn btn-primary" @click="toast('info', 'Primary', 'Birincil aksiyon butonu tıklandı.')">Primary</button>
					<button class="btn btn-secondary" @click="toast('info', 'Secondary', 'İkincil aksiyon butonu tıklandı.')">Secondary</button>
					<button class="btn btn-success" @click="toast('success', 'Başarılı!', 'İşlem başarıyla tamamlandı.')">Success</button>
					<button class="btn btn-warning" @click="toast('warning', 'Uyarı', 'Bu işlem geri alınamaz, dikkatli ol.')">Warning</button>
					<button class="btn btn-danger" @click="toast('error', 'Hata', 'İşlem sırasında bir hata oluştu.')">Danger</button>
					<button class="btn btn-info" @click="toast('info', 'Bilgi', 'Yeni güncellemeler mevcut.')">Info</button>
					<button class="btn btn-ghost" @click="toast('info', 'Ghost', 'Sessiz aksiyon tetiklendi.')">Ghost</button>
				</div>
			</div>

			<!-- Outlined -->
			<div class="btn-section">
				<div class="btn-section-label">Outlined</div>
				<div class="btn-row">
					<button class="btn btn-outline-primary" @click="toast('info', 'Outline Primary')">Outline Primary</button>
					<button class="btn btn-outline-success" @click="toast('success', 'Outline Success')">Outline Success</button>
					<button class="btn btn-outline-danger" @click="toast('error', 'Outline Danger')">Outline Danger</button>
					<button class="btn btn-outline-info" @click="toast('info', 'Outline Info')">Outline Info</button>
				</div>
			</div>

			<!-- Boyutlar -->
			<div class="btn-section">
				<div class="btn-section-label">Boyutlar</div>
				<div class="btn-row" style="align-items: center">
					<button class="btn btn-primary btn-xs" @click="toast('info', 'XS Buton')">XSmall</button>
					<button class="btn btn-primary btn-sm" @click="toast('info', 'SM Buton')">Small</button>
					<button class="btn btn-primary" @click="toast('info', 'MD Buton')">Medium</button>
					<button class="btn btn-primary btn-lg" @click="toast('info', 'LG Buton')">Large</button>
				</div>
			</div>

			<!-- İkon ile -->
			<div class="btn-section">
				<div class="btn-section-label">İkon ile</div>
				<div class="btn-row">
					<button class="btn btn-primary btn-with-icon" @click="toast('success', 'İş Emri Oluşturuldu', 'Yeni iş emri sisteme eklendi.')">
						<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
						Yeni Ekle
					</button>
					<button class="btn btn-success btn-with-icon" @click="toast('success', 'Kaydedildi', 'Değişiklikler başarıyla kaydedildi.')">
						<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" /></svg>
						Kaydet
					</button>
					<button class="btn btn-danger btn-with-icon" @click="toast('error', 'Silindi', 'Kayıt kalıcı olarak silindi.')">
						<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6" /><path d="M19 6l-1 14H6L5 6" /><path d="M10 11v6M14 11v6" /></svg>
						Sil
					</button>
					<button class="btn btn-info btn-with-icon" @click="toast('info', 'Paylaşıldı', 'Bağlantı panoya kopyalandı.')">
						<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="18" cy="5" r="3" /><circle cx="6" cy="12" r="3" /><circle cx="18" cy="19" r="3" /><path d="M8.59 13.51l6.83 3.98M15.41 6.51l-6.82 3.98" /></svg>
						Paylaş
					</button>
				</div>
			</div>

			<!-- Sadece ikon -->
			<div class="btn-section">
				<div class="btn-section-label">Sadece İkon</div>
				<div class="btn-row" style="align-items: center">
					<button class="btn btn-primary btn-icon btn-sm" @click="toast('info', 'Düzenle')">
						<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" /></svg>
					</button>
					<button class="btn btn-success btn-icon" @click="toast('success', 'Onaylandı')">
						<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" /></svg>
					</button>
					<button class="btn btn-danger btn-icon" @click="toast('error', 'Silindi')">
						<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12" /></svg>
					</button>
					<button class="btn btn-warning btn-icon btn-lg" @click="toast('warning', 'Uyarı', 'Dikkat gerektiren bir durum var.')">
						<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" /><line x1="12" y1="9" x2="12" y2="13" /><line x1="12" y1="17" x2="12.01" y2="17" /></svg>
					</button>
					<button class="btn btn-icon btn-outline-primary" @click="toast('info', 'Filtreler', 'Filtre paneli açıldı.')">
						<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="4" y1="6" x2="20" y2="6" /><line x1="8" y1="12" x2="16" y2="12" /><line x1="11" y1="18" x2="13" y2="18" /></svg>
					</button>
				</div>
			</div>

			<!-- Modal tetikleyici -->
			<div class="btn-section">
				<div class="btn-section-label">Modal</div>
				<div class="btn-row">
					<button class="btn btn-primary btn-with-icon" @click="$emit('open-modal', 'info')">
						<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="3" /><path d="M9 9h6M9 12h6M9 15h4" /></svg>
						Modal Aç
					</button>
					<button class="btn btn-outline-primary btn-with-icon" @click="$emit('open-modal', 'danger')">
						<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" /><line x1="12" y1="9" x2="12" y2="13" /><line x1="12" y1="17" x2="12.01" y2="17" /></svg>
						Onay Modalı
					</button>
				</div>
			</div>

			<!-- Drawer tetikleyici -->
			<div class="btn-section">
				<div class="btn-section-label">Drawer</div>
				<div class="btn-row">
					<button class="btn btn-primary btn-with-icon" @click="$emit('open-drawer')">
						<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" /><path d="M15 3v18" /></svg>
						Filtreleri Aç
					</button>
				</div>
			</div>

			<!-- Özel durumlar -->
			<div class="btn-section">
				<div class="btn-section-label">Özel Durumlar</div>
				<div class="btn-row" style="align-items: center">
					<button class="btn btn-primary btn-pill" @click="toast('info', 'Pill Buton')">Pill Buton</button>
					<button class="btn btn-success btn-pill btn-with-icon" @click="toast('success', 'Onaylandı', 'İşlem onaylandı.')">
						<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" /></svg>
						Onayla
					</button>
					<button class="btn btn-primary btn-loading btn-with-icon">
						<span class="btn-spinner"></span>
						Yükleniyor...
					</button>
					<button class="btn btn-primary" disabled>Devre Dışı</button>
					<button class="btn btn-danger" disabled>Devre Dışı</button>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup>
import { inject } from 'vue'

defineEmits(['open-modal', 'open-drawer'])

const showToast = inject('showToast')

function toast(type, title, message = '') {
	showToast?.({ type, title, message })
}
</script>

<style scoped>
.form-card {
	background: #fff; border-radius: 16px;
	border: 1px solid #ebebf0;
	box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
	margin-top: 14px;
	overflow: visible;
}
.form-card-header {
	padding: 14px 18px; border-bottom: 1px solid #f0f0f5;
	display: flex; align-items: center; justify-content: space-between;
}
.form-card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }
.form-card-body { padding: 18px; }

.btn-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.btn-section { margin-bottom: 20px; }
.btn-section:last-child { margin-bottom: 0; }
.btn-section-label {
	font-size: 10.5px; font-weight: 700; color: #aaa;
	text-transform: uppercase; letter-spacing: .06em;
	margin-bottom: 8px;
}
</style>
