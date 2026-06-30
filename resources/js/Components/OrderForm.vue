<template>
	<div class="form-card">
		<div class="form-card-header">
			<h3>{{ title }}</h3>
			<div style="display: flex; gap: 6px">
				<button class="wf-action-btn" title="Taslak Kaydet">
					<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z" />
						<polyline points="17 21 17 13 7 13 7 21" />
						<polyline points="7 3 7 8 15 8" />
					</svg>
				</button>
				<button class="wf-action-btn" title="Kapat">✕</button>
			</div>
		</div>

		<div class="form-card-body">
			<div class="form-grid">
				<div class="form-group">
					<label class="form-label">Sipariş No</label>
					<div class="form-input-wrap">
						<svg class="form-input-icon" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
							<rect x="3" y="3" width="18" height="18" rx="2" /><path d="M3 9h18M9 21V9" />
						</svg>
						<input v-model="form.orderNo" class="form-input" type="text" placeholder="#SIP-2024-" />
					</div>
				</div>

				<div class="form-group">
					<label class="form-label">Müşteri</label>
					<div class="form-input-wrap">
						<svg class="form-input-icon" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
							<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" /><circle cx="12" cy="7" r="4" />
						</svg>
						<input v-model="form.customer" class="form-input" type="text" placeholder="Müşteri adı ara..." />
					</div>
				</div>

				<CustomSelect
					label="Ürün Türü"
					v-model="form.urun"
					:options="urunOptions"
					placeholder="Seçiniz..."
				/>

				<CustomSelect
					label="Kumaş Tipi"
					v-model="form.kumas"
					:options="kumasOptions"
					placeholder="Seçiniz..."
				/>

				<div class="form-group">
					<label class="form-label">Miktar</label>
					<div style="display: flex; gap: 6px">
						<input v-model="form.miktar" class="form-input" type="number" placeholder="0" min="0" style="flex: 1" />
						<CustomSelect
							v-model="form.birim"
							:options="birimOptions"
							:show-label="false"
							style="width: 110px"
						/>
					</div>
				</div>

				<div class="form-group">
					<label class="form-label">Teslim Tarihi</label>
					<DatePicker v-model="form.teslimTarihi" />
				</div>

				<CustomSelect
					label="Öncelik"
					v-model="form.oncelik"
					:options="oncelikOptions"
					placeholder="Seçiniz..."
				/>

				<CustomSelect
					label="Durum"
					v-model="form.durum"
					:options="durumOptions"
					placeholder="Seçiniz..."
				/>

				<div class="form-group span2">
					<label class="form-label">Ek Hizmetler</label>
					<div class="form-check-group">
						<label v-for="hizmet in hizmetler" :key="hizmet.key" class="form-check">
							<input type="checkbox" v-model="form.hizmetler[hizmet.key]" />
							<span class="form-check-box"></span>
							<span class="form-check-label">{{ hizmet.label }}</span>
						</label>
					</div>
				</div>

				<div class="form-group span2">
					<label class="form-label">Teslimat Yöntemi</label>
					<div class="form-check-group">
						<label v-for="t in teslimat" :key="t.value" class="form-check form-check-radio">
							<input type="radio" name="teslimat" :value="t.value" v-model="form.teslimat" />
							<span class="form-check-box"></span>
							<span class="form-check-label">{{ t.label }}</span>
						</label>
					</div>
				</div>

				<div class="form-group span2">
					<label class="form-label">Notlar</label>
					<textarea v-model="form.notlar" class="form-textarea" placeholder="İş emri ile ilgili ek notlar..."></textarea>
				</div>

				<div class="form-group span2">
					<label class="form-label">Dosya Ekle</label>
					<div
						class="dropzone"
						:class="{ dragover: isDragging }"
						@click="fileInput?.click()"
						@dragover.prevent="isDragging = true"
						@dragleave.prevent="isDragging = false"
						@drop.prevent="handleDrop"
					>
						<input ref="fileInput" type="file" multiple style="display: none" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.png" @change="handleFileChange" />
						<div class="dz-icon">
							<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
								<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12" />
							</svg>
						</div>
						<div class="dz-title">Sürükle & bırak veya <span>dosya seç</span></div>
						<div class="dz-sub">PDF, Word, Excel, JPG, PNG — maks. 10 MB</div>
						<div class="dz-file-list">
							<div v-for="(f, idx) in files" :key="idx" class="dz-file-item" @click.stop>
								<div class="dz-file-icon">{{ fileIcon(f.name) }}</div>
								<span class="dz-file-name">{{ f.name }}</span>
								<span class="dz-file-size">{{ formatBytes(f.size) }}</span>
								<button class="dz-file-remove" title="Kaldır" @click.stop="removeFile(idx)">✕</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="form-actions">
			<button class="btn btn-danger" @click="onClear">Temizle</button>
			<div style="flex: 1"></div>
			<button class="btn btn-secondary" @click="onCancel">İptal</button>
			<button class="btn btn-primary" @click="onSubmit">İş Emri Oluştur</button>
		</div>
	</div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import CustomSelect from './CustomSelect.vue'
import DatePicker from './DatePicker.vue'

defineProps({
	title: { type: String, default: 'Yeni İş Emri Oluştur' },
})

const emit = defineEmits(['submit', 'cancel', 'clear'])

const urunOptions = [
	{ value: 'dokuma', label: '🧵 Dokuma Kumaş' },
	{ value: 'orgu', label: '🪡 Örme Kumaş' },
	{ value: 'boya', label: '🎨 Boyalı Kumaş' },
	{ value: 'konfeksiyon', label: '👕 Konfeksiyon' },
	{ value: 'aksesuar', label: '🔩 Aksesuar' },
]

const kumasOptions = [
	{ value: 'pamuk', label: 'Pamuk %100' },
	{ value: 'polyester', label: 'Polyester' },
	{ value: 'keten', label: 'Keten' },
	{ value: 'yun', label: 'Yün' },
	{ value: 'karisim', label: 'Pamuk / Poly Karışım' },
]

const birimOptions = [
	{ value: 'metre', label: 'Metre' },
	{ value: 'kg', label: 'Kilogram' },
	{ value: 'adet', label: 'Adet' },
	{ value: 'top', label: 'Top' },
]

const oncelikOptions = [
	{ value: 'dusuk', label: 'Düşük', dot: '#4af4a0' },
	{ value: 'normal', label: 'Normal', dot: '#4a7ff4' },
	{ value: 'yuksek', label: 'Yüksek', dot: '#f4894a' },
	{ value: 'kritik', label: 'Kritik', dot: '#f44a7f' },
]

const durumOptions = [
	{ value: 'taslak', label: 'Taslak', dot: '#aaa' },
	{ value: 'planlandi', label: 'Planlandı', dot: '#f4d44a' },
	{ value: 'aktif', label: 'Aktif', dot: '#4a7ff4' },
	{ value: 'tamamlandi', label: 'Tamamlandı', dot: '#4af4a0' },
]

const hizmetler = [
	{ key: 'kalite', label: 'Kalite Kontrol' },
	{ key: 'paket', label: 'Paketleme' },
	{ key: 'etiket', label: 'Etiketleme' },
	{ key: 'sevk', label: 'Sevkiyat' },
	{ key: 'bildirim', label: 'Müşteri Bildirimi' },
]

const teslimat = [
	{ value: 'depo', label: 'Depodan Teslim' },
	{ value: 'kargo', label: 'Kargo' },
	{ value: 'musteri', label: 'Müşteri Alır' },
]

const form = reactive({
	orderNo: '',
	customer: '',
	urun: null,
	kumas: null,
	miktar: null,
	birim: 'metre',
	teslimTarihi: null,
	oncelik: null,
	durum: null,
	hizmetler: { kalite: true, paket: false, etiket: false, sevk: true, bildirim: false },
	teslimat: 'depo',
	notlar: '',
})

const fileInput = ref(null)
const files = ref([])
const isDragging = ref(false)

const fileIcons = { pdf: '📄', doc: '📝', docx: '📝', xls: '📊', xlsx: '📊', jpg: '🖼️', png: '🖼️' }

function fileIcon(name) {
	const ext = name.split('.').pop().toLowerCase()
	return fileIcons[ext] || '📎'
}

function formatBytes(b) {
	if (b < 1024) return b + ' B'
	if (b < 1048576) return (b / 1024).toFixed(1) + ' KB'
	return (b / 1048576).toFixed(1) + ' MB'
}

function handleFileChange(e) {
	addFiles(e.target.files)
}

function handleDrop(e) {
	isDragging.value = false
	addFiles(e.dataTransfer.files)
}

function addFiles(list) {
	[...list].forEach((f) => files.value.push(f))
}

function removeFile(idx) {
	files.value.splice(idx, 1)
}

function onSubmit() { emit('submit', { ...form, files: [...files.value] }) }
function onCancel() { emit('cancel') }
function onClear() {
	Object.assign(form, {
		orderNo: '', customer: '', urun: null, kumas: null,
		miktar: null, birim: 'metre', teslimTarihi: null,
		oncelik: null, durum: null, notlar: '',
	})
	form.hizmetler = { kalite: false, paket: false, etiket: false, sevk: false, bildirim: false }
	form.teslimat = 'depo'
	files.value = []
	emit('clear')
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

.form-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 14px;
}
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group.span2 { grid-column: span 2; }

.form-input-wrap { position: relative; }
.form-input-wrap .form-input { padding-left: 34px; width: 100%; }
.form-input-icon {
	position: absolute; left: 10px; top: 50%;
	transform: translateY(-50%); color: #bbb; pointer-events: none;
}

/* Checkbox & Radio */
.form-check-group { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 2px; }
.form-check {
	display: flex; align-items: center; gap: 7px;
	cursor: pointer; user-select: none;
}
.form-check input[type=checkbox],
.form-check input[type=radio] { display: none; }
.form-check-box {
	width: 16px; height: 16px; border-radius: 4px;
	border: 1.5px solid #d0d0e0; background: #fafafe;
	display: flex; align-items: center; justify-content: center;
	flex-shrink: 0; transition: all .15s;
}
.form-check-radio .form-check-box { border-radius: 50%; }
.form-check input:checked + .form-check-box {
	background: rgb(var(--color-primary)); border-color: rgb(var(--color-primary));
}
.form-check input:checked + .form-check-box::after {
	content: '';
	display: block; width: 4px; height: 7px;
	border: 2px solid #fff; border-top: none; border-left: none;
	transform: rotate(45deg) translate(-1px, -1px);
}
.form-check-radio input:checked + .form-check-box::after {
	width: 6px; height: 6px; border: none;
	background: #fff; border-radius: 50%; transform: none;
}
.form-check-label { font-size: 12.5px; color: #444; font-weight: 500; }

/* Dropzone */
.dropzone {
	border: 2px dashed #d8d8ea; border-radius: 12px;
	background: #fafafe; padding: 28px 20px;
	display: flex; flex-direction: column; align-items: center; gap: 8px;
	cursor: pointer; transition: border-color .2s, background .2s;
	text-align: center;
}
.dropzone:hover, .dropzone.dragover {
	border-color: rgb(var(--color-primary)); background: rgb(var(--color-primary-soft));
}
.dropzone.dragover .dz-icon { transform: translateY(-4px) scale(1.1); }
.dz-icon {
	width: 44px; height: 44px; border-radius: 12px;
	background: rgb(var(--color-primary-soft)); display: flex; align-items: center; justify-content: center;
	color: rgb(var(--color-primary)); transition: transform .2s;
}
.dz-title { font-size: 13px; font-weight: 600; color: #333; }
.dz-title span { color: rgb(var(--color-primary)); text-decoration: underline; }
.dz-sub { font-size: 11px; color: #aaa; }
.dz-file-list {
	display: flex; flex-direction: column; gap: 6px;
	width: 100%; margin-top: 4px;
}
.dz-file-item {
	display: flex; align-items: center; gap: 8px;
	background: #fff; border: 1px solid #e8e8f0;
	border-radius: 8px; padding: 6px 10px;
}
.dz-file-icon {
	width: 26px; height: 26px; border-radius: 6px;
	display: flex; align-items: center; justify-content: center;
	font-size: 13px; flex-shrink: 0;
}
.dz-file-name { font-size: 12px; font-weight: 500; color: #333; flex: 1; text-align: left; }
.dz-file-size { font-size: 11px; color: #aaa; }
.dz-file-remove {
	background: none; border: none; cursor: pointer;
	color: #ccc; font-size: 13px; padding: 0 2px;
	transition: color .15s;
}
.dz-file-remove:hover { color: #f05a5a; }

.form-actions {
	display: flex; align-items: center; gap: 8px;
	padding: 14px 18px; border-top: 1px solid #f0f0f5;
	justify-content: flex-end;
}
</style>
