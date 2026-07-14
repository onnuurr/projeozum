<template>
	<Head title="Üretim İş Akışları" />
	<div class="page-workflow">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'İş Emirleri' },
				{ label: 'Üretim İş Akışları' },
			]"
		/>
		<h1 class="page-title">Üretim İş Akışları</h1>

		<WorkflowBoard :team="workflowTeam" :columns="workflowColumns" />

		<div class="bottom-grid">
			<ProductionTable :rows="productionRows" @toggle-star="toggleProductionStar" />
			<OrderStatusCharts :items="chartItems" />
		</div>

		<OrderForm
			@submit="handleOrderSubmit"
			@cancel="showToast({ type: 'info', title: 'İptal Edildi', message: 'Form değişiklikleri kaydedilmedi.' })"
			@clear="showToast({ type: 'warning', title: 'Form Temizlendi', message: 'Tüm alanlar sıfırlandı.' })"
		/>

		<ButtonShowcase
			@open-modal="openModal"
			@open-drawer="drawer.open()"
		/>

		<!-- Modal: Bilgi -->
		<AppModal
			v-model="infoModalOpen"
			variant="info"
			title="Yeni Kumaş Türü Ekle"
			subtitle="Kumaş kataloğuna yeni bir tür ekleyebilirsiniz"
		>
			<div class="modal-info-row">
				<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
					<circle cx="12" cy="12" r="10" />
					<line x1="12" y1="8" x2="12" y2="12" />
					<line x1="12" y1="16" x2="12.01" y2="16" />
				</svg>
				Eklediğiniz kumaş türü anında tüm iş emirlerinde kullanıma açılır.
			</div>
			<div style="display: flex; flex-direction: column; gap: 12px">
				<div>
					<label class="modal-label">Kumaş Adı</label>
					<input v-model="newFabric.name" class="form-input" type="text" placeholder="örn. Pamuk Dokuma" style="width: 100%" />
				</div>
				<div style="display: flex; gap: 10px">
					<div style="flex: 1">
						<label class="modal-label">Kategori</label>
						<input v-model="newFabric.category" class="form-input" type="text" placeholder="örn. Doğal" style="width: 100%" />
					</div>
					<div style="flex: 1">
						<label class="modal-label">Birim</label>
						<input v-model="newFabric.unit" class="form-input" type="text" placeholder="örn. metre" style="width: 100%" />
					</div>
				</div>
				<div>
					<label class="modal-label">Açıklama</label>
					<textarea v-model="newFabric.description" class="form-textarea" placeholder="Kumaş hakkında kısa açıklama..." style="width: 100%; resize: vertical; min-height: 72px"></textarea>
				</div>
			</div>
			<template #footer="{ close }">
				<button class="btn btn-ghost" @click="close">İptal</button>
				<button class="btn btn-primary btn-with-icon" @click="submitFabric(close)">
					<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
					Ekle
				</button>
			</template>
		</AppModal>

	</div>
</template>

<script setup>
import { ref, reactive, inject } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import WorkflowBoard from '@/Components/WorkflowBoard.vue'
import ProductionTable from '@/Components/ProductionTable.vue'
import OrderStatusCharts from '@/Components/OrderStatusCharts.vue'
import OrderForm from '@/Components/OrderForm.vue'
import ButtonShowcase from '@/Components/ButtonShowcase.vue'
import AppModal from '@/Components/AppModal.vue'

defineOptions({ layout: AppLayout })

const showToast = inject('showToast')
const drawer = inject('drawer')
const $swal = inject('$swal')

/* ── Workflow ── */
const workflowTeam = ref([
	{ id: 1, initials: 'YK', color: '#4a7ff4', count: 2, countColor: '#4a7ff4' },
	{ id: 2, initials: 'MA', color: '#f4894a', count: 3, countColor: '#f4894a' },
	{ id: 3, initials: 'FK', color: '#f44a7f', count: 2 },
	{ id: 4, initials: 'AY', color: '#7f4af4', count: 1 },
	{ id: 5, initials: '+', color: '#e0e0ea', textColor: '#888' },
	{ id: 6, initials: 'BÇ', color: '#4af4a0', count: 1 },
	{ id: 7, initials: 'HT', color: '#f4d44a', textColor: '#555', count: '+', countColor: '#aaa' },
	{ id: 8, initials: 'SD', color: '#4af4f4', textColor: '#333', count: '+', countColor: '#aaa' },
])

const workflowColumns = ref([
	{
		label: 'Sipariş Atama',
		variant: 'card',
		items: [
			{ id: 1, initials: 'YK', color: '#4a7ff4', text: 'Siparişi Üretim Hattına Ata!' },
			{ id: 2, initials: 'AY', color: '#7f4af4', text: 'Müşteriye Sipariş Onayı Gönder!' },
		],
	},
	{
		label: 'Kumaş Analizi',
		variant: 'list',
		items: [
			{ id: 3, initials: 'YK', color: '#4a7ff4', text: 'Kumaş Türünü Belirle' },
			{ id: 4, initials: 'MA', color: '#f4894a', text: 'Kumaş Kalitesini Kontrol Et' },
			{ id: 5, initials: 'FK', color: '#f44a7f', text: 'Renk Kodlarını Onayla' },
			{ id: 6, initials: 'BÇ', color: '#4af4a0', textColor: '#1a7a50', text: 'Tedarikçiye İş Emri Ver', menu: true },
			{ id: 7, initials: 'AY', color: '#7f4af4', text: 'Müşteriye Analiz Raporu Gönder', bold: true, menu: true },
		],
	},
	{
		label: 'Üretim Süreci',
		variant: 'list',
		items: [
			{ id: 8, text: 'Üretim Bağımlılıklarını Belirle', alert: true },
			{ id: 9, initials: 'YK', color: '#4a7ff4', text: 'Tahmini Üretim Süresini Hesapla', highlight: true, menu: true },
			{ id: 10, initials: 'FK', color: '#f44a7f', text: 'Müşteriye Süre Bildirimi Yap', menu: true },
			{ id: 11, text: 'Müşteriye Üretim Durumu Bildir', menu: true },
		],
	},
	{
		label: 'Yeni Görevler',
		variant: 'cta',
		cta: { text: 'İş Emri<br />Oluştur' },
		mini: ['Kalite<br />Kontrol', 'Sevkiyat<br />Planla', 'Müşteri<br />Bildirimi', 'Müşteri<br />Onayı'],
	},
])

/* ── Üretim planı tablosu ── */
const productionRows = ref([
	{ id: 1, starred: false, subject: 'Kumaş Dokuma', status: 'Tamamlandı', statusClass: 'done', start: '2024-04-20 08:00', end: '2024-04-20 17:00', owner: 'Ahmet Yıldız' },
	{ id: 2, starred: false, subject: 'Boya Süreci', status: 'Planlandı', statusClass: 'scheduled', start: '2024-04-21 09:00', end: '2024-04-21 14:00', owner: 'Fatma Kaya' },
	{ id: 3, starred: false, subject: 'Kalite Kontrol', status: 'Devam Ediyor', statusClass: 'progress', start: '2024-04-22 08:30', end: '2024-04-22 12:00', owner: 'Mehmet Arslan' },
	{ id: 4, starred: false, subject: 'Kesim & Dikim', status: 'Gecikti', statusClass: 'active', start: '2024-04-23 07:00', end: '2024-04-23 16:00', owner: 'Büşra Çelik' },
	{ id: 5, starred: false, subject: 'Paketleme', status: 'Planlandı', statusClass: 'scheduled', start: '2024-04-25 10:00', end: '2024-04-25 15:00', owner: 'Hakan Tekin' },
])

function toggleProductionStar(id) {
	const r = productionRows.value.find((x) => x.id === id)
	if (r) r.starred = !r.starred
}

/* ── Chart verileri ── */
const chartItems = ref([
	{ label: 'Tamamlandı', value: 12, color: '#4a7ff4' },
	{ label: 'Aktif', value: 7, color: '#f44a7f' },
	{ label: 'Gecikti', value: 4, color: '#f4894a' },
	{ label: 'Planlandı', value: 9, color: '#4af4a0' },
])

/* ── Form ── */
function handleOrderSubmit() {
	showToast({ type: 'success', title: 'İş Emri Oluşturuldu', message: 'Yeni iş emri başarıyla sisteme eklendi.' })
}

/* ── Modal state ── */
const infoModalOpen = ref(false)
const newFabric = reactive({ name: '', category: '', unit: '', description: '' })

async function openModal(variant) {
	if (variant === 'danger') {
		const ok = await $swal.dangerConfirm({
			title: 'İş Emrini Sil',
			html: '<strong>IE-2024-0847</strong> numaralı iş emrini silmek üzeresiniz. Bu işlem kalıcıdır ve tüm ilgili üretim kayıtları, görev atamaları ve raporlar silinecektir.',
			confirmText: 'Sil',
			cancelText: 'Vazgeç',
		})
		if (ok) {
			showToast({ type: 'error', title: 'İş Emri Silindi', message: 'IE-2024-0847 kalıcı olarak silindi.' })
		}
	} else {
		infoModalOpen.value = true
	}
}

function submitFabric(close) {
	showToast({ type: 'success', title: 'Kumaş Eklendi', message: 'Yeni kumaş türü başarıyla kataloğa eklendi.' })
	Object.assign(newFabric, { name: '', category: '', unit: '', description: '' })
	close()
}
</script>

<style scoped>
.page-title {
	font-size: 22px;
	font-weight: 700;
	color: #1a1a2e;
	margin-bottom: 16px;
}

.bottom-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 14px;
}

.modal-label {
	font-size: 12.5px;
	font-weight: 500;
	color: #555;
	display: block;
	margin-bottom: 5px;
}

@media (max-width: 900px) {
	.bottom-grid { grid-template-columns: 1fr; }
}
</style>
